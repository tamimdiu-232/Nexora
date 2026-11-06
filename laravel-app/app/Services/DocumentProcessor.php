<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentMetadata;
use App\Models\DocumentPage;
use App\Models\ExtractedEntity;
use App\Models\ProcessingJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentProcessor
{
    public function __construct(protected AiClient $aiClient)
    {
    }

    public function process(Document $document): bool
    {
        @set_time_limit(900);

        // 1. Record processing job
        $job = ProcessingJob::create([
            'document_id' => $document->id,
            'status' => 'running',
            'attempt' => $document->processingJobs()->count() + 1,
            'started_at' => now(),
        ]);

        $document->update([
            'processing_status' => 'processing',
            'processing_error' => null,
        ]);

        // Resolve local file path on private disk
        $filePath = Storage::disk('private')->path($document->stored_path);

        try {
            // 2. Call AI Microservice
            $result = $this->aiClient->processDocument(
                documentId: $document->uuid,
                title: $document->title,
                filePath: $filePath
            );

            // 3. Atomically persist extracted data in MySQL transaction
            DB::transaction(function () use ($document, $result, $job) {
                // Remove previous extraction data (safe to reprocess)
                $document->pages()->delete();
                $document->chunks()->delete();
                $document->entities()->delete();

                // Save pages
                $pagesData = [];
                foreach ($result['pages'] ?? [] as $page) {
                    $pagesData[] = [
                        'document_id' => $document->id,
                        'page_number' => $page['page_number'],
                        'text' => $page['text'] ?? '',
                        'extraction_method' => $page['extraction_method'] ?? 'text',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($pagesData)) {
                    DocumentPage::insert($pagesData);
                }

                // Save chunk references
                $chunksData = [];
                foreach ($result['chunks'] ?? [] as $chunk) {
                    $chunksData[] = [
                        'document_id' => $document->id,
                        'chunk_id' => $chunk['chunk_id'],
                        'page_number' => $chunk['page_number'],
                        'chunk_index' => $chunk['chunk_index'],
                        'char_count' => $chunk['char_count'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($chunksData)) {
                    DocumentChunk::insert($chunksData);
                }

                // Save extracted entities
                $entitiesData = [];
                foreach ($result['entities'] ?? [] as $entity) {
                    $entitiesData[] = [
                        'document_id' => $document->id,
                        'type' => $entity['type'],
                        'value' => $entity['value'],
                        'page_number' => $entity['page_number'],
                        'evidence' => $entity['evidence'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($entitiesData)) {
                    ExtractedEntity::insert($entitiesData);
                }

                // Save metadata
                $info = $result['extracted_info'] ?? [];
                DocumentMetadata::updateOrCreate(
                    ['document_id' => $document->id],
                    [
                        'issuing_organization' => $info['issuing_organization'] ?? null,
                        'reference_number' => $info['reference_number'] ?? null,
                        'document_date' => $info['document_date'] ?? null,
                        'project_name' => $info['project_name'] ?? null,
                        'budget' => $info['budget'] ?? null,
                        'deadline' => $info['deadline'] ?? null,
                        'keywords' => $info['keywords'] ?? [],
                        // Invalidate cached summary upon reprocessing
                        'summary' => null,
                        'summary_generated_at' => null,
                    ]
                );

                // Update document attributes
                $updateFields = [
                    'page_count' => (int) ($result['page_count'] ?? count($pagesData)),
                    'processing_status' => 'completed',
                    'processing_error' => null,
                    'processed_at' => now(),
                ];

                // AI never overwrites manually corrected category
                if ($document->category_source !== 'manual') {
                    $classification = $result['classification'] ?? [];
                    $updateFields['category'] = $classification['category'] ?? 'Other';
                    $updateFields['category_confidence'] = (float) ($classification['confidence'] ?? 0.0);
                    $updateFields['category_source'] = 'ai';
                }

                $document->update($updateFields);

                // Mark job completed
                $job->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);
            });

            AuditLogger::log('document.processed', $document, [
                'document_uuid' => $document->uuid,
                'page_count' => $document->page_count,
                'category' => $document->category,
            ]);

            return true;
        } catch (\Exception $e) {
            $errorCode = ($e instanceof AiServiceException) ? $e->getErrorCode() : 'processing_failed';
            $errorMessage = $e->getMessage();

            Log::error("Document processing failed for {$document->uuid}: [{$errorCode}] {$errorMessage}");

            $document->update([
                'processing_status' => 'failed',
                'processing_error' => "[{$errorCode}] {$errorMessage}",
            ]);

            $job->update([
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'finished_at' => now(),
            ]);

            AuditLogger::log('document.processing_failed', $document, [
                'document_uuid' => $document->uuid,
                'error_code' => $errorCode,
            ]);

            return false;
        }
    }
}

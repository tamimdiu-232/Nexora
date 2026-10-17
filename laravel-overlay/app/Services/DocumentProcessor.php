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
use Illuminate\Support\Facades\Storage;

class DocumentProcessor
{
    public function __construct(private AiClient $ai) {}

    /** Safe to run repeatedly: old pages, chunks and entities are replaced inside a transaction. */
    public function process(Document $doc): bool
    {
        $job = ProcessingJob::create([
            'document_id' => $doc->id, 'status' => 'processing',
            'attempt' => $doc->jobs()->count() + 1, 'started_at' => now(),
        ]);
        $doc->update(['processing_status' => 'processing', 'processing_error' => null]);

        try {
            if (!Storage::disk('local')->exists($doc->stored_path)) {
                throw new AiServiceException('Stored file is missing.', 'file_missing');
            }
            $res = $this->ai->process($doc->uuid, $doc->title, Storage::disk('local')->path($doc->stored_path));

            DB::transaction(function () use ($doc, $job, $res) {
                DocumentPage::where('document_id', $doc->id)->delete();
                DocumentChunk::where('document_id', $doc->id)->delete();
                ExtractedEntity::where('document_id', $doc->id)->delete();

                foreach ($res['pages'] as $p) {
                    DocumentPage::create(['document_id' => $doc->id, 'page_number' => $p['page'],
                        'text' => $p['text'], 'extraction_method' => $p['method']]);
                }
                foreach ($res['chunks'] as $c) {
                    DocumentChunk::create(['document_id' => $doc->id, 'chunk_id' => $c['chunk_id'],
                        'page_number' => $c['page'], 'chunk_index' => $c['index'], 'char_count' => $c['chars']]);
                }
                foreach (array_slice($res['info']['entities'] ?? [], 0, 200) as $e) {
                    ExtractedEntity::create(['document_id' => $doc->id, 'type' => $e['type'],
                        'value' => mb_substr($e['value'], 0, 500), 'page_number' => $e['page'] ?? null,
                        'evidence' => mb_substr($e['evidence'] ?? '', 0, 500)]);
                }
                $i = $res['info'];
                DocumentMetadata::updateOrCreate(['document_id' => $doc->id], [
                    'issuing_organization' => $i['organization'] ?? null, 'reference_number' => $i['reference_number'] ?? null,
                    'document_date' => $i['document_date'] ?? null, 'project_name' => $i['project_name'] ?? null,
                    'budget' => $i['budget'] ?? null, 'deadline' => $i['deadline'] ?? null,
                    'keywords' => $i['keywords'] ?? [], 'summary' => null, 'summary_generated_at' => null,
                ]);

                $update = ['processing_status' => 'completed', 'page_count' => count($res['pages']),
                    'processed_at' => now(), 'processing_error' => null];
                if ($doc->category_source !== 'manual') { // a human correction is never overwritten by the AI
                    $update += ['category' => $res['classification']['category'],
                        'category_confidence' => $res['classification']['confidence'], 'category_source' => 'ai'];
                }
                $doc->update($update);
                $job->update(['status' => 'completed', 'finished_at' => now()]);
            });
            AuditLogger::log('document.processed', $doc, ['pages' => count($res['pages']), 'chunks' => count($res['chunks'])]);
            return true;
        } catch (AiServiceException $e) {
            $doc->update(['processing_status' => 'failed', 'processing_error' => mb_substr($e->getMessage(), 0, 250)]);
            $job->update(['status' => 'failed', 'error_code' => $e->errorCode,
                'error_message' => mb_substr($e->getMessage(), 0, 250), 'finished_at' => now()]);
            AuditLogger::log('document.processing_failed', $doc, ['error_code' => $e->errorCode]);
            return false;
        }
    }
}

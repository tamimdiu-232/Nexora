<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AiClient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SummaryController extends Controller
{
    public function show(Document $document, AiClient $aiClient)
    {
        Gate::authorize('view', $document);

        $metadata = $document->metadata;

        // Check cache
        if ($metadata && !empty($metadata->summary)) {
            return view('documents.summary', [
                'document' => $document,
                'summary' => $metadata->summary,
                'cached' => true,
            ]);
        }

        // Generate summary from AI service
        try {
            $resp = $aiClient->summarize($document->uuid, [$document->uuid]);
            $summaryData = $resp['summary'] ?? [];

            if ($metadata) {
                $metadata->update([
                    'summary' => $summaryData,
                    'summary_generated_at' => now(),
                ]);
            }

            AuditLogger::log('document.summary_generated', $document, [
                'document_uuid' => $document->uuid,
            ]);

            return view('documents.summary', [
                'document' => $document,
                'summary' => $summaryData,
                'cached' => false,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Could not generate summary: ' . $e->getMessage()]);
        }
    }
}

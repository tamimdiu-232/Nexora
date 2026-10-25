<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Models\Document;
use App\Services\AiClient;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;

class DocumentAiController extends Controller
{
    public function summarize(Document $document, AiClient $ai)
    {
        Gate::authorize('view', $document);
        if ($document->processing_status !== 'completed') {
            return back()->withErrors(['summary' => 'The document has not been processed yet.']);
        }
        try {
            $summary = $ai->summarize($document->uuid); // only this one authorized document is sent as scope
        } catch (AiServiceException $e) {
            return back()->withErrors(['summary' => $e->getMessage()]);
        }
        $document->metadata()->updateOrCreate([], ['summary' => $summary, 'summary_generated_at' => now()]);
        AuditLogger::log('document.summarized', $document);
        return redirect()->route('documents.show', $document)->with('success', 'Summary generated (AI-generated, not verified).');
    }

    public function related(Document $document, AiClient $ai)
    {
        Gate::authorize('view', $document);
        $user = auth()->user();
        $items = collect(); $error = null;
        if ($document->processing_status === 'completed') {
            $authorized = Document::visibleTo($user)->active()->completed()->pluck('uuid')->all();
            try {
                $results = $ai->related($document->uuid, $authorized, 6);
                // Re-validate every returned document against the user's scope before display.
                $docs = Document::visibleTo($user)->active()->completed()
                    ->whereIn('uuid', collect($results)->pluck('document_id'))->get()->keyBy('uuid');
                $items = collect($results)->filter(fn ($r) => $docs->has($r['document_id']))
                    ->map(fn ($r) => $r + ['doc' => $docs[$r['document_id']]]);
            } catch (AiServiceException $e) {
                $error = $e->getMessage();
            }
        }
        AuditLogger::log('document.related_viewed', $document);
        return view('documents.related', ['document' => $document, 'items' => $items, 'error' => $error]);
    }
}

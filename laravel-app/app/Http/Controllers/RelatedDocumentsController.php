<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RelatedDocumentsController extends Controller
{
    public function show(Document $document, AiClient $aiClient)
    {
        $user = Auth::user();
        Gate::authorize('view', $document);

        // Fetch authorized documents scope
        $authorizedUuids = Document::visibleTo($user)
            ->active()
            ->completed()
            ->pluck('uuid')
            ->all();

        try {
            $resp = $aiClient->related($document->uuid, $authorizedUuids, topK: 5);
            $rawRelated = $resp['related_documents'] ?? [];

            // Re-validate returned documents against user's scope
            $relatedUuids = array_column($rawRelated, 'document_id');
            $validDocs = Document::visibleTo($user)
                ->whereIn('uuid', $relatedUuids)
                ->with('department')
                ->get()
                ->keyBy('uuid');

            $relatedList = [];
            foreach ($rawRelated as $item) {
                $uuid = $item['document_id'];
                if (isset($validDocs[$uuid])) {
                    $relatedList[] = [
                        'document' => $validDocs[$uuid],
                        'similarity' => $item['similarity'] ?? 0.0,
                        'explanation' => $item['explanation'] ?? '',
                        'shared_keywords' => $item['shared_keywords'] ?? [],
                    ];
                }
            }

            return view('documents.related', [
                'document' => $document,
                'relatedList' => $relatedList,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Could not retrieve related documents: ' . $e->getMessage()]);
        }
    }
}

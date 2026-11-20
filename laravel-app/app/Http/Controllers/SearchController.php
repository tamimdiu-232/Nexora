<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentPage;
use App\Services\AiClient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function index(Request $request, AiClient $aiClient)
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            abort(403, 'Platform administrators do not have access to organizational search.');
        }

        $queryStr = trim($request->input('q', ''));
        $mode = $request->input('mode', 'keyword'); // keyword | semantic
        $category = $request->input('category');
        $departmentId = $request->input('department_id');

        $results = [];
        $paginatedResults = null;

        // Base scoped builder
        $baseDocQuery = Document::visibleTo($user)->active()->completed();

        if ($category) {
            $baseDocQuery->where('category', $category);
        }
        if ($departmentId) {
            $baseDocQuery->where('department_id', $departmentId);
        }

        if (!empty($queryStr)) {
            // Audit log query length and mode only - NEVER the query text
            AuditLogger::log('search.performed', null, [
                'mode' => $mode,
                'query_length' => mb_strlen($queryStr),
            ]);

            if ($mode === 'semantic') {
                // Semantic Mode
                $authorizedUuids = (clone $baseDocQuery)->pluck('uuid')->all();

                if (!empty($authorizedUuids)) {
                    $aiResp = $aiClient->search($queryStr, $authorizedUuids, topK: 20);
                    $rawResults = $aiResp['results'] ?? [];

                    // Re-validate returned document IDs against user's scope
                    $returnedUuids = array_column($rawResults, 'document_id');
                    $validDocs = Document::visibleTo($user)
                        ->whereIn('uuid', $returnedUuids)
                        ->with('department')
                        ->get()
                        ->keyBy('uuid');

                    foreach ($rawResults as $item) {
                        $docUuid = $item['document_id'];
                        if (isset($validDocs[$docUuid])) {
                            $doc = $validDocs[$docUuid];
                            $results[] = [
                                'document' => $doc,
                                'page_number' => $item['page_number'],
                                'snippet' => $item['snippet'],
                                'score' => $item['score'],
                            ];
                        }
                    }
                }
            } else {
                // Keyword Mode: exact lexical search across metadata and page text
                $cleanTerm = str_replace(['%', '_'], '', $queryStr);

                // Find matching pages
                $visibleDocIds = (clone $baseDocQuery)->pluck('id');

                $pageMatches = DocumentPage::whereIn('document_id', $visibleDocIds)
                    ->where('text', 'like', "%{$cleanTerm}%")
                    ->with('document.department')
                    ->paginate(12)
                    ->withQueryString();

                $paginatedResults = $pageMatches;
            }
        }

        $departments = Department::where('organization_id', $user->organization_id)->active()->get();

        return view('search.index', compact(
            'queryStr',
            'mode',
            'results',
            'paginatedResults',
            'departments'
        ));
    }
}

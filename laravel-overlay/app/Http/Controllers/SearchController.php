<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Models\Department;
use App\Models\Document;
use App\Services\AiClient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, AiClient $ai)
    {
        $u = $request->user();
        $request->validate(['q' => ['nullable', 'string', 'max:300'], 'mode' => ['nullable', 'in:keyword,semantic'],
            'category' => ['nullable', 'in:' . implode(',', config('nexora.categories'))],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'department_id' => ['nullable', 'integer']]);
        $q = trim((string) $request->input('q', ''));
        $mode = $request->input('mode', 'keyword');

        // Access control happens FIRST: only documents this user may see are ever searched or sent to the AI service.
        $base = Document::visibleTo($u)->active()->completed()
            ->when($request->filled('category'), fn ($w) => $w->where('category', $request->input('category')))
            ->when($request->filled('department_id'), fn ($w) => $w->where('department_id', (int) $request->input('department_id')))
            ->when($request->filled('from'), fn ($w) => $w->whereDate('documents.created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($w) => $w->whereDate('documents.created_at', '<=', $request->input('to')));

        $results = null; $semantic = null; $error = null;
        if ($q !== '') {
            AuditLogger::log('search.performed', null, ['mode' => $mode, 'length' => mb_strlen($q)]);
            if ($mode === 'semantic') {
                $ids = (clone $base)->pluck('uuid')->all();
                try {
                    $hits = $ids ? $ai->search($q, $ids, 20) : [];
                    $docs = Document::visibleTo($u)->active()->completed()->whereIn('uuid', collect($hits)->pluck('document_id'))->get()->keyBy('uuid');
                    $semantic = collect($hits)->filter(fn ($h) => $docs->has($h['document_id']))->map(fn ($h) => $h + ['doc' => $docs[$h['document_id']]]);
                } catch (AiServiceException $e) {
                    $error = $e->getMessage();
                }
            } else {
                $like = '%' . str_replace(['%', '_'], ' ', $q) . '%';
                $results = (clone $base)->with('metadata')->where(function ($w) use ($like) {
                    $w->where('title', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('description', 'like', $like)
                        ->orWhereHas('metadata', fn ($m) => $m->where('project_name', 'like', $like)->orWhere('reference_number', 'like', $like))
                        ->orWhereHas('pages', fn ($p) => $p->where('text', 'like', $like));
                })->latest('documents.created_at')->paginate(10)->withQueryString();
                $results->getCollection()->transform(function ($doc) use ($q, $like) {
                    $page = $doc->pages()->where('text', 'like', $like)->orderBy('page_number')->first();
                    $doc->hit_page = $page?->page_number;
                    $doc->hit_snippet = $page ? $this->snippet($page->text, $q) : null;
                    return $doc;
                });
            }
        }

        return view('search.index', [
            'q' => $q, 'mode' => $mode, 'results' => $results, 'semantic' => $semantic, 'error' => $error,
            'departments' => $u->isOrgAdmin()
                ? Department::where('organization_id', $u->organization_id)->get()
                : Department::where('id', $u->department_id)->get(),
        ]);
    }

    private function snippet(string $text, string $q): string
    {
        $pos = mb_stripos($text, $q);
        $start = max(0, ($pos === false ? 0 : $pos) - 60);
        return ($start > 0 ? '…' : '') . mb_substr($text, $start, 220) . '…';
    }
}

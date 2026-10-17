<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\ProcessDocumentJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Services\AiClient;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $q = Document::visibleTo($u)->with('department');
        $request->boolean('archived') ? $q->whereNotNull('archived_at') : $q->active();

        $q->when($request->filled('q'), function ($w) use ($request) {
            $like = '%' . str_replace(['%', '_'], ' ', $request->input('q')) . '%';
            $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('description', 'like', $like));
        })
            ->when($request->filled('category'), fn ($w) => $w->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($w) => $w->where('processing_status', $request->input('status')))
            ->when($request->filled('department_id'), fn ($w) => $w->where('department_id', (int) $request->input('department_id')));

        return view('documents.index', [
            'documents' => $q->latest()->paginate(15)->withQueryString(),
            'departments' => $this->departmentsFor($u),
        ]);
    }

    public function create(Request $request)
    {
        return view('documents.create', [
            'departments' => $this->departmentsFor($request->user()),
            'organizations' => $request->user()->isSuper() ? Organization::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(StoreDocumentRequest $request, DocumentService $service)
    {
        $u = $request->user();
        [$orgId, $deptId] = $service->resolveScope($u, $request->integer('organization_id') ?: null, $request->integer('department_id') ?: null);
        $file = $request->file('file');
        $doc = $service->store($file, $u, $orgId, $deptId, $request->validated(), $file->getClientOriginalName());
        AuditLogger::log('document.uploaded', $doc, ['size' => $doc->size_bytes]);
        ProcessDocumentJob::dispatch($doc->id);

        return redirect()->route('documents.show', $doc)->with('success', 'Document uploaded. Processing has been started.');
    }

    public function show(Document $document)
    {
        Gate::authorize('view', $document);
        $document->load(['metadata', 'department', 'uploader', 'entities']);
        return view('documents.show', [
            'document' => $document,
            'lastJob' => $document->jobs()->latest('id')->first(),
            'canModify' => auth()->user()->can('update', $document),
        ]);
    }

    public function edit(Document $document)
    {
        Gate::authorize('update', $document);
        $document->load('metadata');
        return view('documents.edit', ['document' => $document, 'departments' => $this->departmentsFor(auth()->user(), $document->organization_id)]);
    }

    public function update(Request $request, Document $document)
    {
        Gate::authorize('update', $document);
        $u = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(config('govdoc.categories'))],
            'tags' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer'],
            'issuing_organization' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'string', 'max:50'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:100'],
            'deadline' => ['nullable', 'string', 'max:100'],
        ]);

        $changes = [];
        if ($data['category'] !== $document->category) {
            $changes['category'] = [$document->category, $data['category']];
            $document->category_source = 'manual'; // human correction
        }
        // Only organization/global admins may move a document between departments.
        if (($u->isSuper() || $u->isOrgAdmin()) && array_key_exists('department_id', $data)) {
            $deptId = $data['department_id'] ?: null;
            if ($deptId && !Department::where('id', $deptId)->where('organization_id', $document->organization_id)->exists()) {
                throw ValidationException::withMessages(['department_id' => 'Invalid department.']);
            }
            if ($deptId !== $document->department_id) $changes['department_id'] = [$document->department_id, $deptId];
            $document->department_id = $deptId;
        }
        $document->fill(['title' => $data['title'], 'description' => $data['description'] ?? null,
            'category' => $data['category'], 'tags' => $data['tags'] ?? null])->save();
        $document->metadata()->updateOrCreate([], collect($data)->only(['issuing_organization', 'reference_number',
            'document_date', 'project_name', 'budget', 'deadline'])->all());

        AuditLogger::log('document.updated', $document, ['changes' => $changes]);
        return redirect()->route('documents.show', $document)->with('success', 'Document updated.');
    }

    public function destroy(Document $document, AiClient $ai)
    {
        Gate::authorize('delete', $document);
        try { $ai->remove($document->uuid); } catch (AiServiceException $e) { /* index is already excluded by status filter */ }
        AuditLogger::log('document.deleted', $document, ['title' => $document->title]);
        $document->delete(); // soft delete; file kept on private disk for recovery
        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }

    public function archive(Document $document)
    {
        Gate::authorize('update', $document);
        $document->update(['archived_at' => $document->archived_at ? null : now()]);
        AuditLogger::log($document->archived_at ? 'document.archived' : 'document.unarchived', $document);
        return back()->with('success', $document->archived_at ? 'Document archived.' : 'Document restored.');
    }

    public function reprocess(Document $document)
    {
        Gate::authorize('reprocess', $document);
        AuditLogger::log('document.reprocess_requested', $document);
        ProcessDocumentJob::dispatch($document->id);
        return back()->with('success', 'Processing restarted.');
    }

    private function departmentsFor($user, ?int $orgId = null)
    {
        $q = Department::with('organization')->where('is_active', true)->orderBy('name');
        if ($user->isSuper()) return $orgId ? $q->where('organization_id', $orgId)->get() : $q->get();
        if ($user->isOrgAdmin()) return $q->where('organization_id', $user->organization_id)->get();
        return $q->where('id', $user->department_id)->get();
    }
}

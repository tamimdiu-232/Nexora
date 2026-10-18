<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\ProcessDocumentJob;
use App\Models\Department;
use App\Models\Document;
use App\Services\AiClient;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    /** Document library: only documents the signed-in user may see (Document::visibleTo). */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Document::visibleTo($user)->with('department');
        $request->boolean('archived') ? $query->whereNotNull('archived_at') : $query->active();

        $query->when($request->filled('q'), function ($w) use ($request) {
            $like = '%' . str_replace(['%', '_'], ' ', $request->input('q')) . '%';
            $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('description', 'like', $like));
        })
            ->when($request->filled('category'), fn ($w) => $w->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($w) => $w->where('processing_status', $request->input('status')))
            ->when($request->filled('department_id'), fn ($w) => $w->where('department_id', (int) $request->input('department_id')));

        return view('documents.index', [
            'documents' => $query->latest()->paginate(15)->withQueryString(),
            'departments' => $this->departmentsFor($user),
        ]);
    }

    public function create(Request $request)
    {
        return view('documents.create', ['departments' => $this->departmentsFor($request->user())]);
    }

    public function store(StoreDocumentRequest $request, DocumentService $service)
    {
        $user = $request->user();
        [$orgId, $deptId] = $service->resolveScope($user, $request->integer('department_id') ?: null);

        $file = $request->file('file');
        $document = $service->store($file, $user, $orgId, $deptId, $request->validated(), $file->getClientOriginalName());
        AuditLogger::log('document.uploaded', $document, ['size' => $document->size_bytes]);
        ProcessDocumentJob::dispatch($document->id);

        return redirect()->route('documents.show', $document)->with('success', 'Document uploaded. Processing has been started.');
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
        return view('documents.edit', ['document' => $document, 'departments' => $this->departmentsFor(auth()->user())]);
    }

    public function update(Request $request, Document $document)
    {
        Gate::authorize('update', $document);
        $user = $request->user();
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
            $document->category_source = 'manual'; // a human correction is never overwritten by the AI
        }
        // Only Organization Admins may move a document between departments.
        if ($user->isOrgAdmin() && array_key_exists('department_id', $data)) {
            $deptId = $data['department_id'] ?: null;
            if ($deptId && !Department::where('id', $deptId)->where('organization_id', $document->organization_id)->exists()) {
                throw ValidationException::withMessages(['department_id' => 'Invalid department.']);
            }
            if ($deptId !== $document->department_id) $changes['department_id'] = [$document->department_id, $deptId];
            $document->department_id = $deptId;
        }
        $document->fill(['title' => $data['title'], 'description' => $data['description'] ?? null,
            'category' => $data['category'], 'tags' => $data['tags'] ?? null])->save();
        $document->metadata()->updateOrCreate([], collect($data)->only([
            'issuing_organization', 'reference_number', 'document_date', 'project_name', 'budget', 'deadline'])->all());

        AuditLogger::log('document.updated', $document, ['changes' => $changes]);
        return redirect()->route('documents.show', $document)->with('success', 'Document updated.');
    }

    public function destroy(Document $document, AiClient $ai)
    {
        Gate::authorize('delete', $document);
        try { $ai->remove($document->uuid); } catch (AiServiceException $e) { /* already excluded from search by its status */ }
        AuditLogger::log('document.deleted', $document); // the title is deliberately not copied into the log
        $document->delete(); // soft delete; the file stays on the private disk for recovery
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

    /** Departments the user may upload into (all of the organization for admins, otherwise only their own). */
    private function departmentsFor($user)
    {
        $query = Department::where('is_active', true)->orderBy('name');
        return $user->isOrgAdmin()
            ? $query->where('organization_id', $user->organization_id)->get()
            : $query->where('id', $user->department_id)->get();
    }
}

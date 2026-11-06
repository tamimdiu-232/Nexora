<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Department;
use App\Models\Document;
use App\Services\AiClient;
use App\Services\AuditLogger;
use App\Services\DocumentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            abort(403, 'Platform administrators do not have access to organizational documents.');
        }

        $showArchived = $request->boolean('archived');
        $query = Document::visibleTo($user);

        if ($showArchived) {
            $query->archived();
        } else {
            $query->active();
        }

        // Filters
        if ($search = $request->input('search')) {
            $cleanSearch = str_replace(['%', '_'], '', $search);
            $query->where(function ($q) use ($cleanSearch) {
                $q->where('title', 'like', "%{$cleanSearch}%")
                  ->orWhere('original_name', 'like', "%{$cleanSearch}%")
                  ->orWhere('description', 'like', "%{$cleanSearch}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('processing_status', $status);
        }

        if ($deptId = $request->input('department_id')) {
            $query->where('department_id', $deptId);
        }

        $documents = $query->with(['department', 'uploader'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $departments = Department::where('organization_id', $user->organization_id)->active()->get();

        return view('documents.index', compact('documents', 'departments', 'showArchived'));
    }

    public function create()
    {
        $user = Auth::user();
        Gate::authorize('create', Document::class);

        $departments = Department::where('organization_id', $user->organization_id)->active()->get();

        return view('documents.create', compact('departments'));
    }

    public function store(StoreDocumentRequest $request, DocumentProcessor $processor)
    {
        $user = $request->user();
        Gate::authorize('create', Document::class);

        $file = $request->file('file');
        $uuid = (string) Str::uuid();

        // Enforce department scoping
        $deptId = $user->department_id;
        if ($user->isOrgAdmin() && $request->filled('department_id')) {
            // Verify chosen department belongs to own org
            $chosen = Department::where('organization_id', $user->organization_id)
                ->where('id', $request->input('department_id'))
                ->first();
            $deptId = $chosen?->id;
        }

        // Store file with generated uuid on private disk
        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $storedFilename = "documents/{$uuid}.{$extension}";
        $rawBytes = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $rawBytes);

        Storage::disk('private')->put($storedFilename, $rawBytes);

        $tags = null;
        if ($request->filled('tags')) {
            $tags = array_map('trim', explode(',', $request->input('tags')));
        }

        $category = $request->input('category', 'Other');
        $categorySource = $request->filled('category') ? 'manual' : 'pending';

        $document = Document::create([
            'uuid' => $uuid,
            'organization_id' => $user->organization_id,
            'department_id' => $deptId,
            'uploaded_by' => $user->id,
            'title' => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $request->input('description'),
            'category' => $category,
            'category_confidence' => $request->filled('category') ? 1.0 : 0.0,
            'category_source' => $categorySource,
            'tags' => $tags,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedFilename,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($rawBytes),
            'sha256' => $sha256,
            'processing_status' => 'pending',
        ]);

        AuditLogger::log('document.uploaded', $document, [
            'document_uuid' => $document->uuid,
            'size_bytes' => $document->size_bytes,
            'sha256' => $document->sha256,
        ]);

        // Process immediately (or via queue if queue driver is database)
        $processor->process($document);

        return redirect()->route('documents.show', $document)
            ->with('status', 'Document uploaded and processing initialized.');
    }

    public function show(Document $document)
    {
        $user = Auth::user();
        Gate::authorize('view', $document);

        $document->load([
            'department',
            'uploader',
            'metadata',
            'pages' => fn ($q) => $q->orderBy('page_number'),
            'entities',
            'processingJobs' => fn ($q) => $q->latest()->take(5),
        ]);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $user = Auth::user();
        Gate::authorize('update', $document);

        $departments = [];
        if ($user->isOrgAdmin()) {
            $departments = Department::where('organization_id', $user->organization_id)->active()->get();
        }

        return view('documents.edit', compact('document', 'departments'));
    }

    public function update(Request $request, Document $document)
    {
        $user = Auth::user();
        Gate::authorize('update', $document);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'tags' => ['nullable', 'string'],
        ]);

        $tags = null;
        if (!empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
        }

        $fields = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'tags' => $tags,
        ];

        // If human changes the category, it becomes 'manual' and AI never overwrites it
        if ($validated['category'] !== $document->category) {
            $fields['category'] = $validated['category'];
            $fields['category_source'] = 'manual';
            $fields['category_confidence'] = 1.0;
        }

        // Only Org Admin may move document between departments
        if ($user->isOrgAdmin() && array_key_exists('department_id', $validated)) {
            $fields['department_id'] = $validated['department_id'];
        }

        $document->update($fields);

        AuditLogger::log('document.updated', $document, [
            'document_uuid' => $document->uuid,
            'category' => $document->category,
            'category_source' => $document->category_source,
        ]);

        return redirect()->route('documents.show', $document)->with('status', 'Document details updated.');
    }

    public function destroy(Document $document, AiClient $aiClient)
    {
        Gate::authorize('delete', $document);

        $uuid = $document->uuid;
        $docId = $document->id;

        // Delete from vector index
        $aiClient->deleteDocument($uuid);

        // Soft delete document
        $document->delete();

        // Security rule: Audit log stores the ID, NOT the title
        AuditLogger::log('document.deleted', null, [
            'document_id' => $docId,
            'document_uuid' => $uuid,
        ]);

        return redirect()->route('documents.index')->with('status', 'Document removed from library.');
    }

    public function preview(Document $document)
    {
        Gate::authorize('preview', $document);

        AuditLogger::log('document.previewed', $document, [
            'document_uuid' => $document->uuid,
        ]);

        $path = Storage::disk('private')->path($document->stored_path);
        if (!file_exists($path)) {
            abort(404, 'Document file not found.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function download(Document $document)
    {
        Gate::authorize('download', $document);

        AuditLogger::log('document.downloaded', $document, [
            'document_uuid' => $document->uuid,
            'size_bytes' => $document->size_bytes,
        ]);

        $path = Storage::disk('private')->path($document->stored_path);
        if (!file_exists($path)) {
            abort(404, 'Document file not found.');
        }

        return response()->download($path, $document->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reprocess(Document $document, DocumentProcessor $processor)
    {
        Gate::authorize('reprocess', $document);

        AuditLogger::log('document.reprocess_requested', $document, [
            'document_uuid' => $document->uuid,
        ]);

        $processor->process($document);

        return back()->with('status', 'Document reprocessing initiated.');
    }

    public function toggleArchive(Document $document)
    {
        Gate::authorize('update', $document);

        if ($document->archived_at) {
            $document->update(['archived_at' => null]);
            AuditLogger::log('document.restored', $document);
            $msg = 'Document restored to active library.';
        } else {
            $document->update(['archived_at' => now()]);
            AuditLogger::log('document.archived', $document);
            $msg = 'Document archived.';
        }

        return back()->with('status', $msg);
    }
}

@extends('layouts.app')

@section('title', 'Documents Library')
@section('page_title', 'Institutional Documents')

@section('content')
<x-page-header title="Document Library" subtitle="Digital and scanned organizational documents with OCR, semantic extraction, and retrieval">
    @if(Auth::user()->hasPermission('documents.upload'))
        <a href="{{ route('documents.create') }}" class="btn btn-primary shadow-sm">
            <i class="bi bi-cloud-arrow-up me-2"></i>Upload Document
        </a>
    @endif
</x-page-header>

<!-- Filter Toolbar -->
<div class="nx-card mb-4">
    <div class="nx-card-body p-3">
        <form method="GET" action="{{ route('documents.index') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by title, filename, or description..." value="{{ request('search') }}">
                </div>
            </div>

            <div class="col-sm-6 col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach(config('nexora.categories', []) as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-sm-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            @if(Auth::user()->isOrgAdmin() && count($departments) > 0)
                <div class="col-sm-6 col-md-2">
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-auto d-flex align-items-center gap-2 ms-auto">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="archived" value="1" id="archivedSwitch" {{ $showArchived ? 'checked' : '' }} onchange="this.form.submit()">
                    <label class="form-check-label small text-muted" for="archivedSwitch">Archived</label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                @if(request()->hasAny(['search', 'category', 'status', 'department_id', 'archived']))
                    <a href="{{ route('documents.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Documents Table -->
<div class="nx-card">
    <div class="table-responsive">
        <table class="nx-table">
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Category</th>
                    <th>Department</th>
                    <th>Pages</th>
                    <th>Status</th>
                    <th>Uploaded</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                    <tr>
                        <td>
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-file-earmark-pdf fs-4 text-danger flex-shrink-0"></i>
                                <div>
                                    <a href="{{ route('documents.show', $doc) }}" class="fw-semibold text-dark text-decoration-none hover-text-primary">
                                        {{ $doc->title }}
                                    </a>
                                    <div class="small text-muted">{{ $doc->original_name }} &bull; {{ number_format($doc->size_bytes / 1024, 1) }} KB</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border">{{ $doc->category }}</span>
                            @if($doc->category_source === 'manual')
                                <span class="badge bg-info-subtle text-info ms-1" title="Human verified category">Manual</span>
                            @endif
                        </td>
                        <td>{{ $doc->department?->name ?? 'Organization-wide' }}</td>
                        <td>{{ $doc->page_count ?: '—' }}</td>
                        <td>
                            <x-status :status="$doc->processing_status" />
                            @if($doc->processing_error)
                                <div class="small text-danger mt-1 text-truncate" style="max-width: 180px;" title="{{ $doc->processing_error }}">
                                    {{ $doc->processing_error }}
                                </div>
                            @endif
                        </td>
                        <td class="small text-muted">
                            <div>{{ $doc->created_at->format('M d, Y') }}</div>
                            <div style="font-size: 0.75rem;">by {{ $doc->uploader?->name ?? 'System' }}</div>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <a class="dropdown-item small" href="{{ route('documents.show', $doc) }}">
                                            <i class="bi bi-eye me-2"></i>View Details
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item small" href="{{ route('documents.preview', $doc) }}" target="_blank">
                                            <i class="bi bi-file-earmark-pdf me-2"></i>Preview PDF
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item small" href="{{ route('documents.download', $doc) }}">
                                            <i class="bi bi-download me-2"></i>Download File
                                        </a>
                                    </li>

                                    @if($doc->canBeModifiedBy(Auth::user()))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item small" href="{{ route('documents.edit', $doc) }}">
                                                <i class="bi bi-pencil me-2"></i>Edit Metadata
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('documents.reprocess', $doc) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item small">
                                                    <i class="bi bi-arrow-repeat me-2"></i>Reprocess OCR/AI
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('documents.archive', $doc) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item small">
                                                    <i class="bi {{ $doc->archived_at ? 'bi-box-arrow-up' : 'bi-archive' }} me-2"></i>
                                                    {{ $doc->archived_at ? 'Restore from Archive' : 'Archive Document' }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if(Auth::user()->can('delete', $doc))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('documents.destroy', $doc) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item small text-danger" data-confirm="Are you sure you want to delete this document from the library?">
                                                    <i class="bi bi-trash me-2"></i>Delete Document
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty icon="bi-files" title="No documents found" subtitle="No documents match your query or access clearance." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($documents->hasPages())
        <div class="p-3 border-top">
            {{ $documents->links() }}
        </div>
    @endif
</div>
@endsection

@extends('layouts.app')

@section('title', 'Upload Document')
@section('page_title', 'Upload New Document')

@section('content')
<x-page-header title="Upload Document" subtitle="Add digital or scanned PDF documents (English and Bengali) to the intelligence repository" />

<div class="row">
    <div class="col-lg-8">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Document Ingestion Details</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" data-loading>
                    @csrf

                    <!-- PDF File Upload -->
                    <div class="mb-4">
                        <label for="file" class="form-label small fw-semibold text-secondary">PDF File <span class="text-danger">*</span></label>
                        <div class="p-4 border border-2 border-dashed rounded-3 text-center bg-light">
                            <i class="bi bi-file-earmark-pdf fs-1 text-primary mb-2 d-block"></i>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" accept="application/pdf,.pdf" required>
                            <div class="form-text small mt-2">
                                Supported: Digital and scanned PDFs up to 20 MB. Bilingual English & Bangla OCR automatically applied.
                            </div>
                        </div>
                        @error('file')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label small fw-semibold text-secondary">Document Title (Optional)</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" placeholder="Leave empty to auto-extract title from document header">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Department Assignment (for Org Admins) -->
                    @if(Auth::user()->isOrgAdmin() && count($departments) > 0)
                        <div class="mb-3">
                            <label for="department_id" class="form-label small fw-semibold text-secondary">Department Scope</label>
                            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                                <option value="">-- Organization Wide (All Departments) --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text small">Only members of the assigned department and Org Admins will have access.</div>
                            @error('department_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="row mb-3">
                        <!-- Category -->
                        <div class="col-md-6">
                            <label for="category" class="form-label small fw-semibold text-secondary">Initial Category</label>
                            <select class="form-select @error('category') is-invalid @enderror" id="category" name="category">
                                <option value="">-- Auto-detect via AI Classifier --</option>
                                @foreach(config('nexora.categories', []) as $cat)
                                    <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('category')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tags -->
                        <div class="col-md-6">
                            <label for="tags" class="form-label small fw-semibold text-secondary">Tags</label>
                            <input type="text" class="form-control @error('tags') is-invalid @enderror" id="tags" name="tags" value="{{ old('tags') }}" placeholder="e.g. infrastructure, budget, fy26">
                            <div class="form-text small">Comma separated values</div>
                            @error('tags')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-secondary">Description / Remarks (Optional)</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Contextual notes or internal memo notes...">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="bi bi-cloud-arrow-up me-2"></i>Upload and Process
                        </button>
                        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

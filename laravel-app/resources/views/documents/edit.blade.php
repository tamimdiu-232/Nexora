@extends('layouts.app')

@section('title', 'Edit ' . $document->title)
@section('page_title', 'Edit Document Details')

@section('content')
<x-page-header title="Edit Document Details" subtitle="Update metadata and manual categorization for: {{ $document->title }}" />

<div class="row">
    <div class="col-lg-8">
        <div class="nx-card">
            <div class="nx-card-header">
                <span>Document Properties</span>
            </div>
            <div class="nx-card-body">
                <form method="POST" action="{{ route('documents.update', $document) }}" data-loading>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="title" class="form-label small fw-semibold text-secondary">Document Title</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $document->title) }}" required>
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label small fw-semibold text-secondary">Classification Category</label>
                            <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                                @foreach(config('nexora.categories', []) as $cat)
                                    <option value="{{ $cat }}" {{ old('category', $document->category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                            <div class="form-text small text-info">
                                <i class="bi bi-info-circle me-1"></i>Manual changes lock this category (AI reprocessing will not overwrite it).
                            </div>
                            @error('category')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Department Move (Only for Org Admins) -->
                        @if(Auth::user()->isOrgAdmin() && count($departments) > 0)
                            <div class="col-md-6">
                                <label for="department_id" class="form-label small fw-semibold text-secondary">Department Assignment</label>
                                <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                                    <option value="">-- Organization Wide (All Departments) --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $document->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text small">Organization Admin authority: transfer document between departments.</div>
                                @error('department_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="tags" class="form-label small fw-semibold text-secondary">Tags</label>
                        <input type="text" class="form-control @error('tags') is-invalid @enderror" id="tags" name="tags" value="{{ old('tags', is_array($document->tags) ? implode(', ', $document->tags) : '') }}" placeholder="comma, separated, tags">
                        @error('tags')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-secondary">Description / Remarks</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $document->description) }}</textarea>
                        @error('description')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-2"></i>Save Changes
                        </button>
                        <a href="{{ route('documents.show', $document) }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

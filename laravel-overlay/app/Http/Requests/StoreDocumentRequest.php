<?php

namespace App\Http\Requests;

use App\Services\DocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('documents.upload');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:' . config('govdoc.max_upload_kb'),
                function ($attr, $value, $fail) {
                    if (!app(DocumentService::class)->isPdf($value->getRealPath())) $fail('The file content is not a valid PDF.');
                }],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(config('govdoc.categories'))],
            'tags' => ['nullable', 'string', 'max:255'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}

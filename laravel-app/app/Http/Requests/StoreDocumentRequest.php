<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermission('documents.upload') && !$user->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:20480', // 20 MB max
                function ($attribute, $value, $fail) {
                    if (!$value || !$value->isValid()) {
                        $fail('The uploaded file is invalid.');
                        return;
                    }
                    // Validate PDF magic bytes: must start with %PDF-
                    $stream = fopen($value->getRealPath(), 'rb');
                    if ($stream === false) {
                        $fail('Could not read the uploaded file.');
                        return;
                    }
                    $header = fread($stream, 8);
                    fclose($stream);

                    if (!str_starts_with($header, '%PDF-')) {
                        $fail('The file header signature is not a valid PDF (%PDF- missing).');
                    }
                },
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', Rule::in(config('nexora.categories', []))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
    }
}

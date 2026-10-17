<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    /**
     * Decide which organization/department a new document belongs to - server-side, never trusted from the form.
     * @return array{0:int,1:?int}
     */
    public function resolveScope(User $user, ?int $orgId, ?int $deptId): array
    {
        if ($user->isSuper()) {
            if (!$orgId) throw ValidationException::withMessages(['organization_id' => 'Select an organization.']);
        } else {
            $orgId = $user->organization_id;
            if (!$orgId) throw ValidationException::withMessages(['organization_id' => 'Your account has no organization.']);
            if (!$user->isOrgAdmin()) {
                if (!$user->department_id) throw ValidationException::withMessages(['department_id' => 'Your account has no department.']);
                $deptId = $user->department_id; // officers and department admins can only upload into their own department
            }
        }
        if ($deptId && !Department::where('id', $deptId)->where('organization_id', $orgId)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Invalid department for this organization.']);
        }
        return [$orgId, $deptId ?: null];
    }

    public function store(UploadedFile|File $file, User $user, int $orgId, ?int $deptId, array $data, string $originalName): Document
    {
        $safeName = Str::uuid()->toString() . '.pdf'; // generated name; the user's filename is never used on disk
        $path = Storage::disk('local')->putFileAs('documents/' . $orgId, $file, $safeName);

        return Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'department_id' => $deptId,
            'uploaded_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'Other',
            'category_source' => !empty($data['category']) ? 'manual' : 'pending',
            'tags' => $data['tags'] ?? null,
            'original_name' => mb_substr($originalName, 0, 200),
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'processing_status' => 'pending',
        ]);
    }

    public function isPdf(string $path): bool
    {
        $h = fopen($path, 'rb');
        $magic = $h ? fread($h, 5) : '';
        if ($h) fclose($h);
        return $magic === '%PDF-';
    }
}

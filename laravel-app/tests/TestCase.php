<?php

namespace Tests;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentPage;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function org(?string $name = null): Organization
    {
        return Organization::factory()->create($name ? ['name' => $name, 'code' => Str::slug($name)] : []);
    }

    protected function dept(Organization $o): Department
    {
        return Department::factory()->create(['organization_id' => $o->id]);
    }

    protected function user(string $role, ?Organization $o = null, ?Department $d = null, array $attrs = []): User
    {
        return User::factory()->create(['role' => $role, 'organization_id' => $o?->id, 'department_id' => $d?->id] + $attrs);
    }

    /** A completed document with one page, one chunk and a (fake) stored PDF file. */
    protected function doc(Organization $o, ?Department $d = null, array $attrs = []): Document
    {
        $doc = Document::factory()->create(['organization_id' => $o->id, 'department_id' => $d?->id] + $attrs);
        DocumentPage::create(['document_id' => $doc->id, 'page_number' => 1, 'extraction_method' => 'text',
            'text' => 'The ' . $doc->title . ' budget is BDT 50,000,000 and the deadline is 30 June 2027.']);
        DocumentChunk::create(['document_id' => $doc->id, 'chunk_id' => $doc->uuid . ':p1:c0', 'page_number' => 1, 'chunk_index' => 0, 'char_count' => 80]);
        Storage::disk('local')->put($doc->stored_path, "%PDF-1.4\n% synthetic test file");
        return $doc;
    }

    protected function processResponse(): array
    {
        return [
            'document_id' => 'x',
            'pages' => [['page' => 1, 'method' => 'text', 'chars' => 40, 'text' => 'The project budget is BDT 50,000,000.']],
            'chunks' => [['chunk_id' => 'x:p1:c0', 'page' => 1, 'index' => 0, 'chars' => 40]],
            'classification' => ['category' => 'Project Report', 'confidence' => 0.8, 'scores' => [], 'method' => 'rule-based'],
            'info' => ['title' => 'T', 'organization' => null, 'reference_number' => 'REF-1', 'document_date' => null,
                'project_name' => 'P', 'budget' => 'BDT 50,000,000', 'deadline' => null, 'keywords' => ['project'],
                'entities' => [['type' => 'amount', 'value' => 'BDT 50,000,000', 'page' => 1, 'evidence' => 'x']]],
        ];
    }

    protected function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    }
}

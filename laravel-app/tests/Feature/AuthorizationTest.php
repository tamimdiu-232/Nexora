<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Department $deptA;
    private Department $deptB;
    private User $viewer;
    private User $officerA;
    private User $officerB;
    private User $deptAdminA;
    private User $orgAdmin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->org = Organization::create([
            'name' => 'Ministry of Energy',
            'code' => 'ENERGY',
            'is_active' => true,
        ]);

        $this->deptA = Department::create([
            'organization_id' => $this->org->id,
            'name' => 'Renewable Energy Wing',
            'code' => 'REW',
        ]);

        $this->deptB = Department::create([
            'organization_id' => $this->org->id,
            'name' => 'Fossil Fuel Wing',
            'code' => 'FFW',
        ]);

        $this->viewer = User::create([
            'name' => 'Energy Viewer',
            'email' => 'viewer@energy.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'viewer',
            'organization_id' => $this->org->id,
            'department_id' => $this->deptA->id,
            'is_active' => true,
        ]);

        $this->officerA = User::create([
            'name' => 'Officer A',
            'email' => 'officer.a@energy.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->org->id,
            'department_id' => $this->deptA->id,
            'is_active' => true,
        ]);

        $this->officerB = User::create([
            'name' => 'Officer B',
            'email' => 'officer.b@energy.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->org->id,
            'department_id' => $this->deptB->id,
            'is_active' => true,
        ]);

        $this->deptAdminA = User::create([
            'name' => 'Dept Admin A',
            'email' => 'deptadmin.a@energy.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'dept_admin',
            'organization_id' => $this->org->id,
            'department_id' => $this->deptA->id,
            'is_active' => true,
        ]);

        $this->orgAdmin = User::create([
            'name' => 'Energy Org Admin',
            'email' => 'orgadmin@energy.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'org_admin',
            'organization_id' => $this->org->id,
            'department_id' => null,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@nexora.internal',
            'password' => Hash::make('Secret123!'),
            'role' => 'super_admin',
            'organization_id' => null,
            'department_id' => null,
            'is_active' => true,
        ]);
    }

    private function createDoc(User $uploader, ?Department $dept, string $title = 'Test Document'): Document
    {
        return Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $uploader->organization_id,
            'department_id' => $dept?->id,
            'uploaded_by' => $uploader->id,
            'title' => $title,
            'original_name' => 'test.pdf',
            'stored_path' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', 'test'),
            'page_count' => 1,
            'processing_status' => 'completed',
        ]);
    }

    public function test_viewer_cannot_access_document_upload_form(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('documents.create'));
        $response->assertStatus(403);
    }

    public function test_viewer_cannot_upload_document(): void
    {
        $file = UploadedFile::fake()->create('viewer_test.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->viewer)->post(route('documents.store'), [
            'file' => $file,
            'title' => 'Viewer Attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_viewer_cannot_delete_document(): void
    {
        $doc = $this->createDoc($this->officerA, $this->deptA);

        $response = $this->actingAs($this->viewer)->delete(route('documents.destroy', $doc));
        $response->assertStatus(403);
        $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    }

    public function test_officer_cannot_delete_any_document(): void
    {
        // Officers do not have documents.delete permission
        $doc = $this->createDoc($this->officerA, $this->deptA);

        $response = $this->actingAs($this->officerA)->delete(route('documents.destroy', $doc));
        $response->assertStatus(403);
        $this->assertDatabaseHas('documents', ['id' => $doc->id]);
    }

    public function test_officer_cannot_edit_other_officers_document(): void
    {
        $docB = $this->createDoc($this->officerB, $this->deptB);

        $response = $this->actingAs($this->officerA)->get(route('documents.edit', $docB));
        $response->assertStatus(403);

        $response = $this->actingAs($this->officerA)->put(route('documents.update', $docB), [
            'title' => 'Malicious Edit',
        ]);
        $response->assertStatus(403);
    }

    public function test_officer_can_edit_own_document(): void
    {
        $docA = $this->createDoc($this->officerA, $this->deptA);

        $response = $this->actingAs($this->officerA)->get(route('documents.edit', $docA));
        $response->assertStatus(200);

        $response = $this->actingAs($this->officerA)->put(route('documents.update', $docA), [
            'title' => 'Updated by Officer A',
            'category' => 'Office Order',
        ]);
        $response->assertRedirect(route('documents.show', $docA));
        $this->assertDatabaseHas('documents', ['id' => $docA->id, 'title' => 'Updated by Officer A']);
    }

    public function test_dept_admin_cannot_edit_or_delete_documents_in_other_department(): void
    {
        $docB = $this->createDoc($this->officerB, $this->deptB);

        // DeptAdmin A is in deptA, docB is in deptB
        $response = $this->actingAs($this->deptAdminA)->get(route('documents.edit', $docB));
        $response->assertStatus(403);

        $response = $this->actingAs($this->deptAdminA)->delete(route('documents.destroy', $docB));
        $response->assertStatus(403);
    }

    public function test_dept_admin_can_edit_and_delete_documents_in_own_department(): void
    {
        $docA = $this->createDoc($this->officerA, $this->deptA);

        $response = $this->actingAs($this->deptAdminA)->get(route('documents.edit', $docA));
        $response->assertStatus(200);

        $response = $this->actingAs($this->deptAdminA)->delete(route('documents.destroy', $docA));
        $response->assertRedirect(route('documents.index'));
        $this->assertSoftDeleted('documents', ['id' => $docA->id]);
    }

    public function test_org_admin_can_edit_and_delete_documents_in_any_department_within_org(): void
    {
        $docB = $this->createDoc($this->officerB, $this->deptB);

        $response = $this->actingAs($this->orgAdmin)->get(route('documents.edit', $docB));
        $response->assertStatus(200);

        $response = $this->actingAs($this->orgAdmin)->delete(route('documents.destroy', $docB));
        $response->assertRedirect(route('documents.index'));
        $this->assertSoftDeleted('documents', ['id' => $docB->id]);
    }

    public function test_super_admin_cannot_view_or_mutate_organization_documents(): void
    {
        $doc = $this->createDoc($this->officerA, $this->deptA);

        // Super Admin gets 403 on index, show, edit, delete, create
        $this->actingAs($this->superAdmin)->get(route('documents.index'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.create'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.show', $doc))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.edit', $doc))->assertStatus(403);
        $this->actingAs($this->superAdmin)->delete(route('documents.destroy', $doc))->assertStatus(403);
    }
}

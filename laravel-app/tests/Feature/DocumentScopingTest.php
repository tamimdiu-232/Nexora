<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentScopingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private Department $deptA1;
    private Department $deptA2;
    private Department $deptB1;

    private User $orgAdminA;
    private User $officerA1;
    private User $officerA2;
    private User $officerB1;
    private User $superAdmin;

    private Document $docA1;
    private Document $docA2;
    private Document $docAOrgWide;
    private Document $docB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Ministry A', 'code' => 'MIN_A', 'is_active' => true]);
        $this->orgB = Organization::create(['name' => 'Ministry B', 'code' => 'MIN_B', 'is_active' => true]);

        $this->deptA1 = Department::create(['organization_id' => $this->orgA->id, 'name' => 'Dept A1', 'code' => 'DA1']);
        $this->deptA2 = Department::create(['organization_id' => $this->orgA->id, 'name' => 'Dept A2', 'code' => 'DA2']);
        $this->deptB1 = Department::create(['organization_id' => $this->orgB->id, 'name' => 'Dept B1', 'code' => 'DB1']);

        $this->orgAdminA = User::create([
            'name' => 'Org Admin A',
            'email' => 'admin.a@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'org_admin',
            'organization_id' => $this->orgA->id,
            'is_active' => true,
        ]);

        $this->officerA1 = User::create([
            'name' => 'Officer A1',
            'email' => 'officer.a1@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA1->id,
            'is_active' => true,
        ]);

        $this->officerA2 = User::create([
            'name' => 'Officer A2',
            'email' => 'officer.a2@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA2->id,
            'is_active' => true,
        ]);

        $this->officerB1 = User::create([
            'name' => 'Officer B1',
            'email' => 'officer.b1@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgB->id,
            'department_id' => $this->deptB1->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'super_admin',
            'organization_id' => null,
            'is_active' => true,
        ]);

        $this->docA1 = $this->createDoc($this->orgA, $this->deptA1, $this->officerA1, 'Doc A1 Dept');
        $this->docA2 = $this->createDoc($this->orgA, $this->deptA2, $this->officerA2, 'Doc A2 Dept');
        $this->docAOrgWide = $this->createDoc($this->orgA, null, $this->orgAdminA, 'Doc A Org Wide');
        $this->docB1 = $this->createDoc($this->orgB, $this->deptB1, $this->officerB1, 'Doc B1 Dept');
    }

    private function createDoc(Organization $org, ?Department $dept, User $uploader, string $title): Document
    {
        return Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'department_id' => $dept?->id,
            'uploaded_by' => $uploader->id,
            'title' => $title,
            'original_name' => 'test.pdf',
            'stored_path' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', $title),
            'page_count' => 1,
            'processing_status' => 'completed',
        ]);
    }

    public function test_cross_organization_isolation(): void
    {
        $orgADocs = Document::visibleTo($this->officerA1)->pluck('id');
        $this->assertFalse($orgADocs->contains($this->docB1->id));

        $orgBDocs = Document::visibleTo($this->officerB1)->pluck('id');
        $this->assertTrue($orgBDocs->contains($this->docB1->id));
        $this->assertFalse($orgBDocs->contains($this->docA1->id));
        $this->assertFalse($orgBDocs->contains($this->docAOrgWide->id));
    }

    public function test_department_isolation_within_organization(): void
    {
        $a1VisibleIds = Document::visibleTo($this->officerA1)->pluck('id');
        $this->assertTrue($a1VisibleIds->contains($this->docA1->id));
        $this->assertFalse($a1VisibleIds->contains($this->docA2->id));

        $a2VisibleIds = Document::visibleTo($this->officerA2)->pluck('id');
        $this->assertTrue($a2VisibleIds->contains($this->docA2->id));
        $this->assertFalse($a2VisibleIds->contains($this->docA1->id));
    }

    public function test_org_wide_document_visibility(): void
    {
        $a1Visible = Document::visibleTo($this->officerA1)->pluck('id');
        $a2Visible = Document::visibleTo($this->officerA2)->pluck('id');
        $adminAVisible = Document::visibleTo($this->orgAdminA)->pluck('id');

        $this->assertTrue($a1Visible->contains($this->docAOrgWide->id));
        $this->assertTrue($a2Visible->contains($this->docAOrgWide->id));
        $this->assertTrue($adminAVisible->contains($this->docAOrgWide->id));

        // Org Admin sees all docs in own org
        $this->assertTrue($adminAVisible->contains($this->docA1->id));
        $this->assertTrue($adminAVisible->contains($this->docA2->id));
        // But not other orgs
        $this->assertFalse($adminAVisible->contains($this->docB1->id));
    }

    public function test_super_admin_has_zero_document_visibility(): void
    {
        $superDocs = Document::visibleTo($this->superAdmin)->get();
        $this->assertCount(0, $superDocs);

        // Even direct find through scope returns null
        $findA1 = Document::visibleTo($this->superAdmin)->where('id', $this->docA1->id)->first();
        $this->assertNull($findA1);
    }

    public function test_is_visible_to_matches_query_scoping(): void
    {
        $this->assertTrue($this->docA1->isVisibleTo($this->officerA1));
        $this->assertFalse($this->docA1->isVisibleTo($this->officerA2));
        $this->assertTrue($this->docA1->isVisibleTo($this->orgAdminA));
        $this->assertFalse($this->docA1->isVisibleTo($this->officerB1));
        $this->assertFalse($this->docA1->isVisibleTo($this->superAdmin));

        $this->assertTrue($this->docAOrgWide->isVisibleTo($this->officerA1));
        $this->assertTrue($this->docAOrgWide->isVisibleTo($this->officerA2));
        $this->assertTrue($this->docAOrgWide->isVisibleTo($this->orgAdminA));
        $this->assertFalse($this->docAOrgWide->isVisibleTo($this->officerB1));
        $this->assertFalse($this->docAOrgWide->isVisibleTo($this->superAdmin));
    }
}

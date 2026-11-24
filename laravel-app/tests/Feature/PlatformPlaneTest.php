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

class PlatformPlaneTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $orgAdmin;
    private User $officer;
    private Organization $org;
    private Department $dept;
    private Document $sensitiveDoc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@nexora.internal',
            'password' => Hash::make('Secret123!'),
            'role' => 'super_admin',
            'organization_id' => null,
            'is_active' => true,
        ]);

        $this->org = Organization::create([
            'name' => 'Secret Organization Alpha',
            'code' => 'ALPHA',
            'is_active' => true,
        ]);

        $this->dept = Department::create([
            'organization_id' => $this->org->id,
            'name' => 'Classified Operations',
            'code' => 'OPS',
        ]);

        $this->orgAdmin = User::create([
            'name' => 'Alpha Org Admin',
            'email' => 'admin@alpha.org',
            'password' => Hash::make('Secret123!'),
            'role' => 'org_admin',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $this->officer = User::create([
            'name' => 'Alpha Officer',
            'email' => 'officer@alpha.org',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'is_active' => true,
        ]);

        $this->sensitiveDoc = Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'uploaded_by' => $this->officer->id,
            'title' => 'TopSecretBudgetProject2026',
            'original_name' => 'secret_budget.pdf',
            'stored_path' => 'documents/secret_budget.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'sha256' => hash('sha256', 'TopSecretBudgetProject2026'),
            'page_count' => 5,
            'processing_status' => 'completed',
        ]);
    }

    public function test_super_admin_can_access_platform_management_pages(): void
    {
        $this->actingAs($this->superAdmin)->get(route('platform.dashboard'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('platform.health'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('platform.recovery'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('platform.incidents'))->assertStatus(200);
    }

    public function test_super_admin_is_forbidden_from_all_document_and_intelligence_routes(): void
    {
        $this->actingAs($this->superAdmin)->get(route('documents.index'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.create'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.show', $this->sensitiveDoc))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.edit', $this->sensitiveDoc))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('search.index'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('chat.index'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('reviews.index'))->assertStatus(403);
    }

    public function test_organization_users_are_forbidden_from_platform_operations(): void
    {
        $this->actingAs($this->orgAdmin)->get(route('platform.dashboard'))->assertStatus(403);
        $this->actingAs($this->orgAdmin)->get(route('platform.health'))->assertStatus(403);
        $this->actingAs($this->orgAdmin)->get(route('platform.recovery'))->assertStatus(403);
        $this->actingAs($this->orgAdmin)->get(route('platform.incidents'))->assertStatus(403);

        $this->actingAs($this->officer)->get(route('platform.dashboard'))->assertStatus(403);
        $this->actingAs($this->officer)->get(route('platform.health'))->assertStatus(403);
    }

    public function test_super_admin_cannot_manage_departments(): void
    {
        $this->actingAs($this->superAdmin)->get(route('departments.index'))->assertStatus(403);
    }

    public function test_super_admin_platform_views_never_leak_document_titles_or_contents(): void
    {
        $accessibleRoutes = [
            route('platform.dashboard'),
            route('platform.health'),
            route('platform.recovery'),
            route('platform.incidents'),
            route('organizations.index'),
            route('users.index'),
            route('audit.index'),
        ];

        foreach ($accessibleRoutes as $url) {
            $response = $this->actingAs($this->superAdmin)->get($url);
            $response->assertStatus(200);

            // Assert that organization document titles never appear anywhere in platform views
            $response->assertDontSee('TopSecretBudgetProject2026');
            $response->assertDontSee('secret_budget.pdf');
        }
    }
}

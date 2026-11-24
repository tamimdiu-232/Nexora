<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityAttacksTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private Department $deptA1;
    private Department $deptA2;

    private User $viewerA;
    private User $officerA1;
    private User $officerA2;
    private User $deptAdminA1;
    private User $orgAdminA;
    private User $officerB;
    private User $superAdmin;

    private Document $docA1;
    private Document $docB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->orgA = Organization::create(['name' => 'Ministry Alpha', 'code' => 'ALPHA', 'is_active' => true]);
        $this->orgB = Organization::create(['name' => 'Ministry Beta', 'code' => 'BETA', 'is_active' => true]);

        $this->deptA1 = Department::create(['organization_id' => $this->orgA->id, 'name' => 'Dept Alpha 1', 'code' => 'DA1']);
        $this->deptA2 = Department::create(['organization_id' => $this->orgA->id, 'name' => 'Dept Alpha 2', 'code' => 'DA2']);

        $this->viewerA = User::create([
            'name' => 'Viewer Alpha',
            'email' => 'viewer@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'viewer',
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA1->id,
            'is_active' => true,
        ]);

        $this->officerA1 = User::create([
            'name' => 'Officer Alpha 1',
            'email' => 'officer1@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA1->id,
            'is_active' => true,
        ]);

        $this->officerA2 = User::create([
            'name' => 'Officer Alpha 2',
            'email' => 'officer2@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA2->id,
            'is_active' => true,
        ]);

        $this->deptAdminA1 = User::create([
            'name' => 'Dept Admin Alpha 1',
            'email' => 'deptadmin1@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'dept_admin',
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA1->id,
            'is_active' => true,
        ]);

        $this->orgAdminA = User::create([
            'name' => 'Org Admin Alpha',
            'email' => 'orgadmin@alpha.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'org_admin',
            'organization_id' => $this->orgA->id,
            'is_active' => true,
        ]);

        $this->officerB = User::create([
            'name' => 'Officer Beta',
            'email' => 'officer@beta.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $this->orgB->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@nexora.internal',
            'password' => Hash::make('Secret123!'),
            'role' => 'super_admin',
            'organization_id' => null,
            'is_active' => true,
        ]);

        $this->docA1 = Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'department_id' => $this->deptA1->id,
            'uploaded_by' => $this->officerA1->id,
            'title' => 'Alpha 1 Internal Procurement',
            'original_name' => 'procurement.pdf',
            'stored_path' => 'documents/procurement.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', 'procurement.pdf'),
            'page_count' => 2,
            'processing_status' => 'completed',
        ]);

        $this->docB = Document::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'uploaded_by' => $this->officerB->id,
            'title' => 'Beta Ministry Confidential Strategy',
            'original_name' => 'beta_strategy.pdf',
            'stored_path' => 'documents/beta_strategy.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'sha256' => hash('sha256', 'beta_strategy.pdf'),
            'page_count' => 3,
            'processing_status' => 'completed',
        ]);
    }

    /**
     * ATTACK-01: Unauthenticated user attempts to view documents or admin endpoints.
     */
    public function test_attack_01_unauthenticated_access_is_blocked(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
        $this->get(route('search.index'))->assertRedirect(route('login'));
        $this->get(route('chat.index'))->assertRedirect(route('login'));
    }

    /**
     * ATTACK-02: Viewer role attempts document upload, modification, or deletion.
     */
    public function test_attack_02_viewer_mutation_is_forbidden(): void
    {
        $this->actingAs($this->viewerA)->get(route('documents.create'))->assertStatus(403);

        $file = UploadedFile::fake()->create('malicious.pdf', 100, 'application/pdf');
        $this->actingAs($this->viewerA)->post(route('documents.store'), [
            'file' => $file,
            'title' => 'Malicious Upload',
        ])->assertStatus(403);

        $this->actingAs($this->viewerA)->delete(route('documents.destroy', $this->docA1))->assertStatus(403);
    }

    /**
     * ATTACK-03: Horizontal privilege escalation: user accesses document of another department/org.
     */
    public function test_attack_03_cross_department_and_org_access_blocked(): void
    {
        // Officer from Dept A2 cannot access Dept A1 private document
        $this->actingAs($this->officerA2)->get(route('documents.show', $this->docA1))->assertStatus(403);

        // Officer from Org B cannot access Org A document
        $this->actingAs($this->officerB)->get(route('documents.show', $this->docA1))->assertStatus(403);
    }

    /**
     * ATTACK-04: Unauthorized preview or download.
     */
    public function test_attack_04_unauthorized_download_blocked(): void
    {
        $this->actingAs($this->officerB)->get(route('documents.download', $this->docA1))->assertStatus(403);
        $this->actingAs($this->officerB)->get(route('documents.preview', $this->docA1))->assertStatus(403);
    }

    /**
     * ATTACK-05: Cross-tenant search leak attempt.
     */
    public function test_attack_05_search_cross_tenant_isolation(): void
    {
        $response = $this->actingAs($this->officerA1)->get(route('search.index', ['q' => 'Beta Ministry Confidential']));
        $response->assertStatus(200);
        $response->assertDontSee('Beta Ministry Confidential Strategy');
    }

    /**
     * ATTACK-06: RAG prompt injection & out-of-scope context retrieval.
     */
    public function test_attack_06_rag_scope_isolation(): void
    {
        // Compute authorized document scope for Officer A1
        $scoped = Document::visibleTo($this->officerA1)->completed()->pluck('uuid')->all();
        $this->assertContains($this->docA1->uuid, $scoped);
        $this->assertNotContains($this->docB->uuid, $scoped);
    }

    /**
     * ATTACK-07: Interrogating or viewing another user's chat session.
     */
    public function test_attack_07_chat_session_privacy(): void
    {
        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'user_id' => $this->officerA1->id,
            'title' => 'Secret Meeting Inquiries',
        ]);

        $this->actingAs($this->officerA2)->get(route('chat.show', $session))->assertStatus(403);
        $this->actingAs($this->orgAdminA)->get(route('chat.show', $session))->assertStatus(403);
    }

    /**
     * ATTACK-08: Malicious file upload (executables, fake MIME, non-PDF).
     */
    public function test_attack_08_invalid_file_upload_rejected(): void
    {
        $phpFile = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');

        $response = $this->actingAs($this->officerA1)->post(route('documents.store'), [
            'file' => $phpFile,
            'title' => 'PHP Shell Upload Attempt',
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('documents', ['title' => 'PHP Shell Upload Attempt']);
    }

    /**
     * ATTACK-09: Forged citation chunk injection.
     */
    public function test_attack_09_forged_chunk_injection_rejected(): void
    {
        $session = ChatSession::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'user_id' => $this->officerA1->id,
            'title' => 'Attack 09 Test',
        ]);

        Http::fake([
            '*/v1/qa' => Http::response([
                'answer' => 'Spoofed fact [1].',
                'status' => 'answered',
                'citations' => [
                    [
                        'document_id' => $this->docA1->uuid,
                        'chunk_id' => 'forged_chunk_9999',
                        'page_number' => 1,
                        'snippet' => 'spoofed',
                        'score' => 0.99,
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->officerA1)->post(route('chat.ask', $session), [
            'question' => 'Inject citation test',
        ]);

        // Citation should not be created for forged chunk
        $this->assertDatabaseMissing('citations', ['chunk_id' => 'forged_chunk_9999']);
    }

    /**
     * ATTACK-10: Unauthorized privilege escalation.
     */
    public function test_attack_10_privilege_escalation_prevented(): void
    {
        // Dept Admin attempts to create a Super Admin user
        $response = $this->actingAs($this->deptAdminA1)->post(route('users.store'), [
            'name' => 'Escalated Admin',
            'email' => 'escalated@example.com',
            'role' => 'super_admin',
            'department_id' => $this->deptA1->id,
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.com']);

        // User cannot change their own role or status
        $this->actingAs($this->officerA1)->put(route('users.update', $this->officerA1), [
            'name' => 'Officer Alpha 1 Updated',
            'email' => 'officer1@alpha.gov.bd',
            'role' => 'org_admin',
        ])->assertStatus(403);
    }

    /**
     * ATTACK-11: Super Admin attempting to view organization documents or chats.
     */
    public function test_attack_11_super_admin_cannot_access_tenant_content(): void
    {
        $this->actingAs($this->superAdmin)->get(route('documents.index'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('documents.show', $this->docA1))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('search.index'))->assertStatus(403);
        $this->actingAs($this->superAdmin)->get(route('chat.index'))->assertStatus(403);
    }

    /**
     * ATTACK-12: Suspended organization lockout.
     */
    public function test_attack_12_suspended_organization_users_locked_out(): void
    {
        $this->orgA->update(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => 'officer1@alpha.gov.bd',
            'password' => 'Secret123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}

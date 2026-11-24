<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $orgAdminA;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Ministry A', 'code' => 'MIN_A', 'is_active' => true]);
        $this->orgB = Organization::create(['name' => 'Ministry B', 'code' => 'MIN_B', 'is_active' => true]);

        $this->orgAdminA = User::create([
            'name' => 'Org Admin A',
            'email' => 'admin.a@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'org_admin',
            'organization_id' => $this->orgA->id,
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
    }

    public function test_audit_logger_strips_passwords_and_tokens(): void
    {
        $log = AuditLogger::log('security.test', null, [
            'safe_param' => 'hello',
            'password' => 'super-secret-password-123',
            'api_token' => 'bearer_1234567890',
            'nested' => [
                'user_secret' => 'confidential',
                'ok_key' => 'allowed_value',
            ],
        ]);

        $metadata = $log->metadata;

        $this->assertArrayHasKey('safe_param', $metadata);
        $this->assertEquals('hello', $metadata['safe_param']);
        $this->assertArrayNotHasKey('password', $metadata);
        $this->assertArrayNotHasKey('api_token', $metadata);
        $this->assertArrayNotHasKey('user_secret', $metadata['nested']);
        $this->assertEquals('allowed_value', $metadata['nested']['ok_key']);
    }

    public function test_audit_logger_strips_prompts_and_document_content(): void
    {
        $log = AuditLogger::log('intelligence.test', null, [
            'document_uuid' => 'doc-123-uuid',
            'question' => 'What is the top secret allocation?',
            'content' => 'The confidential text from page 1.',
            'ocr_text' => 'Raw extracted OCR text',
            'prompt' => 'You are an AI assistant...',
        ]);

        $metadata = $log->metadata;

        $this->assertArrayHasKey('document_uuid', $metadata);
        $this->assertArrayNotHasKey('question', $metadata);
        $this->assertArrayNotHasKey('content', $metadata);
        $this->assertArrayNotHasKey('ocr_text', $metadata);
        $this->assertArrayNotHasKey('prompt', $metadata);
    }

    public function test_failed_login_never_logs_submitted_password(): void
    {
        $this->post('/login', [
            'email' => 'admin.a@example.com',
            'password' => 'AttemptedCleartextPassword999',
        ]);

        $failedLog = AuditLog::where('action', 'auth.failed_login')->latest()->first();

        $this->assertNotNull($failedLog);
        $json = json_encode($failedLog->metadata);
        $this->assertStringNotContainsString('AttemptedCleartextPassword999', $json);
    }

    public function test_audit_log_visibility_is_scoped_by_organization(): void
    {
        // Log event for Org A
        AuditLog::create([
            'user_id' => $this->orgAdminA->id,
            'organization_id' => $this->orgA->id,
            'action' => 'org_a.event',
            'ip_address' => '127.0.0.1',
            'metadata' => ['event' => 'alpha'],
        ]);

        // Log event for Org B
        AuditLog::create([
            'user_id' => null,
            'organization_id' => $this->orgB->id,
            'action' => 'org_b.event',
            'ip_address' => '127.0.0.1',
            'metadata' => ['event' => 'beta'],
        ]);

        // Org Admin A visits /audit -> sees Org A events, but NOT Org B events
        $responseA = $this->actingAs($this->orgAdminA)->get(route('audit.index'));
        $responseA->assertStatus(200);
        $responseA->assertSee('org_a.event');
        $responseA->assertDontSee('org_b.event');

        // Super Admin visits /audit -> sees both Org A and Org B events
        $responseSuper = $this->actingAs($this->superAdmin)->get(route('audit.index'));
        $responseSuper->assertStatus(200);
        $responseSuper->assertSee('org_a.event');
        $responseSuper->assertSee('org_b.event');
    }
}

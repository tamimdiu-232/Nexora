<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\SecurityIncident;
use App\Services\LogReader;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The Super Admin runs the platform (organizations, health, security, recovery) but never sees organization content. */
class PlatformAdminTest extends TestCase
{
    private function fakeAi(): void
    {
        Http::fake([
            'ai.test/v1/documents/process' => Http::response($this->processResponse()),
            'ai.test/v1/index/health' => Http::response(['documents' => 1, 'chunks' => 1, 'ok' => true]),
            'ai.test/*' => Http::response(['status' => 'ok', 'embedder' => 'hash', 'llm' => 'extractive', 'documents_indexed' => 1]),
        ]);
    }

    public function test_super_admin_cannot_reach_documents_search_chat_or_reviews(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        $doc = $this->doc($org, $d, ['title' => 'TopSecretTitle']);
        $owner = $this->user('viewer', $org, $d);
        $session = ChatSession::create(['user_id' => $owner->id, 'title' => 'Private chat']);

        $this->actingAs($this->user('super_admin'));
        foreach (['/documents', '/documents/' . $doc->uuid, '/documents/' . $doc->uuid . '/preview', '/documents/' . $doc->uuid . '/download',
                     '/documents/' . $doc->uuid . '/related', '/documents/create', '/search?q=BDT', '/chat', '/chat?session=' . $session->id,
                     '/reviews', '/departments'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/documents/' . $doc->uuid . '/summarize')->assertForbidden();
        $this->post('/chat/' . $session->id . '/ask', ['question' => 'anything'])->assertForbidden();
        $this->delete('/documents/' . $doc->uuid)->assertForbidden();
    }

    public function test_no_platform_page_ever_shows_document_titles(): void
    {
        $this->fakeAi();
        $org = $this->org(); $d = $this->dept($org); $officer = $this->user('officer', $org, $d);
        $this->actingAs($officer)->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.pdf', $this->pdfContent()), 'title' => 'TopSecretTitle'])->assertRedirect();
        $this->post('/logout');

        $this->actingAs($this->user('super_admin'));
        foreach (['/dashboard', '/admin/system', '/admin/incidents', '/admin/accounts', '/audit-logs', '/organizations', '/users'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('TopSecretTitle');
        }
        $this->get('/dashboard')->assertSee('Organization content');   // the "not accessible" notice is shown
    }

    public function test_super_admin_manages_organizations_and_creates_only_organization_admins(): void
    {
        $super = $this->user('super_admin'); $org = $this->org();
        $this->actingAs($super)->post('/organizations', ['name' => 'New Authority', 'code' => 'new-authority', 'is_active' => 1])->assertRedirect('/organizations');
        $this->assertDatabaseHas('organizations', ['code' => 'new-authority']);

        $this->post('/users', ['name' => 'Org Boss', 'email' => 'boss@x.test', 'password' => 'Password@1', 'role' => 'org_admin', 'organization_id' => $org->id, 'is_active' => 1])
            ->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'boss@x.test', 'role' => 'org_admin', 'organization_id' => $org->id, 'department_id' => null]);

        foreach (['officer', 'viewer', 'reviewer', 'dept_admin', 'super_admin'] as $role) {
            $this->post('/users', ['name' => 'N', 'email' => $role . '@x.test', 'password' => 'Password@1', 'role' => $role, 'organization_id' => $org->id])->assertSessionHasErrors('role');
        }
        $this->assertDatabaseMissing('users', ['email' => 'officer@x.test']);

        $viewer = $this->user('viewer', $org, $this->dept($org), ['name' => 'Plain Viewer']);
        $this->get('/users')->assertSee('Org Boss')->assertDontSee('Plain Viewer');
        $this->get('/users/' . $viewer->id . '/edit')->assertForbidden();
    }

    public function test_suspending_an_organization_blocks_its_users_but_not_the_platform_admin(): void
    {
        $org = $this->org(); $user = $this->user('viewer', $org, $this->dept($org));
        $this->actingAs($this->user('super_admin'))->put('/organizations/' . $org->id, ['name' => $org->name, 'code' => $org->code])->assertRedirect();
        $this->assertFalse($org->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.updated', 'subject_id' => $org->id]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_account_recovery_actions_are_audited(): void
    {
        Notification::fake();
        $org = $this->org(); $target = $this->user('org_admin', $org); $super = $this->user('super_admin');
        $before = $target->remember_token;
        $this->actingAs($super);

        $this->post('/admin/accounts/' . $target->id . '/reset-link')->assertRedirect();
        Notification::assertSentTo($target, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.reset_link_sent', 'subject_id' => $target->id, 'user_id' => $super->id]);

        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time()]);
        $this->post('/admin/accounts/' . $target->id . '/revoke-sessions')->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-1']);
        $this->assertNotSame($before, $target->fresh()->remember_token);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.sessions_revoked']);

        $this->post('/admin/accounts/' . $target->id . '/toggle-active')->assertRedirect();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_deactivated']);
        $this->post('/admin/accounts/' . $target->id . '/toggle-active');
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_activated']);
    }

    public function test_super_admin_cannot_use_recovery_on_own_account_and_org_admins_cannot_use_it_at_all(): void
    {
        $super = $this->user('super_admin'); $org = $this->org(); $admin = $this->user('org_admin', $org); $other = $this->user('viewer', $org, $this->dept($org));
        $this->actingAs($super)->post('/admin/accounts/' . $super->id . '/toggle-active')->assertForbidden();
        $this->assertTrue($super->fresh()->is_active);

        $this->actingAs($admin);
        $this->get('/admin/accounts')->assertForbidden();
        $this->get('/admin/system')->assertForbidden();
        $this->get('/admin/incidents')->assertForbidden();
        $this->post('/admin/accounts/' . $other->id . '/toggle-active')->assertForbidden();
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_security_incident_lifecycle(): void
    {
        $org = $this->org();
        $this->actingAs($this->user('super_admin'))->post('/admin/incidents', ['title' => 'Suspicious sign-ins', 'severity' => 'high', 'organization_id' => $org->id, 'description' => 'Many failures'])
            ->assertRedirect();
        $incident = SecurityIncident::firstOrFail();
        $this->assertSame('open', $incident->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.created']);

        $this->put('/admin/incidents/' . $incident->id, ['status' => 'resolved'])->assertSessionHasErrors('resolution_note');   // note required to close
        $this->put('/admin/incidents/' . $incident->id, ['status' => 'resolved', 'resolution_note' => 'Passwords reset'])->assertRedirect();
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->resolved_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.updated']);
        $this->post('/admin/incidents', ['title' => 'x', 'severity' => 'nonsense'])->assertSessionHasErrors('severity');
    }

    public function test_system_health_page_shows_services_queue_and_failures_without_titles(): void
    {
        $this->fakeAi();
        $org = $this->org('Health Org'); $doc = $this->doc($org, null, ['title' => 'HiddenFailTitle']);
        DB::table('processing_jobs')->insert(['document_id' => $doc->id, 'status' => 'failed', 'attempt' => 1, 'error_code' => 'corrupt_pdf',
            'error_message' => 'Cannot read PDF', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->user('super_admin'))->get('/admin/system')->assertOk()
            ->assertSee('Online')->assertSee('Health Org')->assertSee('corrupt_pdf')->assertDontSee('HiddenFailTitle');
    }

    public function test_super_admin_sees_audit_entries_of_every_organization(): void
    {
        $a = $this->org(); $b = $this->org();
        \App\Services\AuditLogger::log('marker.org_a', null, [], $this->user('viewer', $a));
        \App\Services\AuditLogger::log('marker.org_b', null, [], $this->user('viewer', $b));
        $this->actingAs($this->user('super_admin'))->get('/audit-logs')->assertSee('marker.org_a')->assertSee('marker.org_b');
    }

    public function test_log_reader_hides_sql_emails_and_stack_traces(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($file, implode("\n", [
            '[2026-10-01 10:00:00] local.INFO: ignored info line',
            '[2026-10-01 10:01:00] local.ERROR: SQLSTATE[HY000] Access denied for user (Connection: mysql, Host: 127.0.0.1, SQL: select * from documents where title = TopSecretTitle)',
            '#0 /var/www/vendor/laravel/framework/Connection.php(838): stack trace line',
            '[2026-10-01 10:02:00] local.ERROR: Mail failed for jane.doe@ministry.gov.bd',
        ]));
        $entries = (new LogReader())->recent(10, $file);
        unlink($file);
        $this->assertCount(2, $entries);
        $this->assertSame('2026-10-01 10:02:00', $entries[0]['time']);   // newest first
        $json = json_encode($entries);
        foreach (['TopSecretTitle', 'jane.doe', 'stack trace', 'ignored info'] as $leak) $this->assertStringNotContainsString($leak, $json);
        $this->assertStringContainsString('[email]', $json);
    }

    public function test_brand_and_disclaimer_text(): void
    {
        $this->actingAs($this->user('viewer', $org = $this->org(), $this->dept($org)))->get('/dashboard')
            ->assertSee('Nexora')->assertSee('Next-generation organizational intelligence')
            ->assertDontSee('Academic prototype')->assertDontSee('student capstone')->assertDontSee('Government of Bangladesh');
        $this->post('/logout');
        $this->get('/login')->assertSee('Nexora')->assertSee('Next-generation organizational intelligence')->assertDontSee('student capstone');
    }
}

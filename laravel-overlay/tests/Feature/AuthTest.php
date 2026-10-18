<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guests_are_redirected_from_every_protected_route(): void
    {
        foreach (['/dashboard', '/documents', '/documents/create', '/search', '/chat', '/reviews', '/users', '/departments', '/organizations', '/audit-logs', '/profile', '/admin/system', '/admin/accounts', '/admin/incidents'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/documents')->assertRedirect('/login');
    }

    public function test_login_success_is_audited(): void
    {
        $u = $this->user('viewer', $this->org());
        $this->post('/login', ['email' => $u->email, 'password' => 'Password@123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => 'auth.login']);
    }

    public function test_failed_login_is_audited_without_the_password(): void
    {
        $u = $this->user('viewer', $this->org());
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong-secret-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $log = AuditLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertStringNotContainsString('wrong-secret-123', json_encode($log->toArray()));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $u = $this->user('viewer', $this->org(), null, ['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Password@123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_logged_out(): void
    {
        $u = $this->user('viewer', $this->org());
        $this->actingAs($u)->get('/dashboard')->assertOk();
        $u->update(['is_active' => false]);
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_is_rate_limited(): void
    {
        $u = $this->user('viewer', $this->org());
        for ($i = 0; $i < 5; $i++) $this->post('/login', ['email' => $u->email, 'password' => 'bad']);
        $this->post('/login', ['email' => $u->email, 'password' => 'Password@123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_is_audited(): void
    {
        $u = $this->user('viewer', $this->org());
        $this->actingAs($u)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => 'auth.logout']);
    }
}

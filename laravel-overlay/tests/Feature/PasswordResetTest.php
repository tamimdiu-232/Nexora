<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_reset_link_is_sent_to_active_user_and_audited(): void
    {
        Notification::fake();
        $u = $this->user('viewer', $this->org());
        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status');
        Notification::assertSentTo($u, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset_requested']);
    }

    public function test_unknown_and_inactive_accounts_get_the_same_answer_and_no_mail(): void
    {
        Notification::fake();
        $inactive = $this->user('viewer', $this->org(), null, ['is_active' => false]);
        $a = $this->post('/forgot-password', ['email' => 'nobody@example.test'])->getSession()->get('status');
        $b = $this->post('/forgot-password', ['email' => $inactive->email])->getSession()->get('status');
        $this->assertSame($a, $b);
        Notification::assertNothingSent();
    }

    public function test_valid_token_changes_password_and_allows_login(): void
    {
        $u = $this->user('viewer', $this->org());
        $token = Password::createToken($u);
        $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'NewPassword#1', 'password_confirmation' => 'NewPassword#1'])
            ->assertRedirect('/login');
        $this->post('/login', ['email' => $u->email, 'password' => 'NewPassword#1'])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset_completed', 'user_id' => $u->id]);
    }

    public function test_invalid_token_and_weak_password_are_rejected(): void
    {
        $u = $this->user('viewer', $this->org());
        $this->post('/reset-password', ['token' => 'bogus', 'email' => $u->email, 'password' => 'NewPassword#1', 'password_confirmation' => 'NewPassword#1'])
            ->assertSessionHasErrors('email');
        $token = Password::createToken($u);
        $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post('/login', ['email' => $u->email, 'password' => 'Password@123'])->assertRedirect('/dashboard');
    }

    public function test_reset_pages_render_for_guests(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/sometoken?email=a@b.test')->assertOk();
    }
}

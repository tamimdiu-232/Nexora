<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sign In');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $org = Organization::create([
            'name' => 'Ministry of Technology',
            'code' => 'TECH',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'John Officer',
            'email' => 'officer@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'officer@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_redirects_to_platform_dashboard(): void
    {
        $superAdmin = User::create([
            'name' => 'Platform Admin',
            'email' => 'superadmin@nexora.internal',
            'password' => Hash::make('Secret123!'),
            'role' => 'super_admin',
            'organization_id' => null,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'superadmin@nexora.internal',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/platform');
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $org = Organization::create([
            'name' => 'Ministry of Health',
            'code' => 'HEALTH',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Dr. Officer',
            'email' => 'health@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'role' => 'officer',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'health@example.com',
            'password' => 'WrongPassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_login_rate_limiting_triggers_after_5_attempts(): void
    {
        RateLimiter::clear('rate_limit_test@example.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'rate_limit_test@example.com',
                'password' => 'wrong-pass',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'rate_limit_test@example.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(session('errors')->first('email') !== null);
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $org = Organization::create([
            'name' => 'Finance Dept',
            'code' => 'FIN',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Disabled User',
            'email' => 'disabled@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $org->id,
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'Secret123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('deactivated', session('errors')->first('email'));
    }

    public function test_user_of_suspended_organization_cannot_login(): void
    {
        $suspendedOrg = Organization::create([
            'name' => 'Suspended Org',
            'code' => 'SUSP',
            'is_active' => false,
        ]);

        User::create([
            'name' => 'Blocked Member',
            'email' => 'blocked@susp.gov.bd',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $suspendedOrg->id,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'blocked@susp.gov.bd',
            'password' => 'Secret123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('suspended', session('errors')->first('email'));
    }

    public function test_authenticated_deactivated_user_is_logged_out_by_middleware(): void
    {
        $org = Organization::create([
            'name' => 'Active Org',
            'code' => 'ACT',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Active User',
            'email' => 'active_turn_inactive@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        // Access dashboard successfully
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Deactivate user mid-session
        $user->update(['is_active' => false]);

        // Next request should trigger EnsureUserIsActive middleware logout
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $org = Organization::create([
            'name' => 'Org Logout',
            'code' => 'LOGO',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Logout User',
            'email' => 'logout@example.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'officer',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}

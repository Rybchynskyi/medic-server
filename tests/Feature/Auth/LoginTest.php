<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleNames;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        Role::findOrCreate(RoleNames::USER);
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $user->syncRoles([RoleNames::USER]);

        config(['sanctum.stateful' => ['localhost:3001']]);

        $response = $this->withHeader('Origin', 'http://localhost:3001')->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_guest_without_json_accept_header_gets_json_401_not_a_login_redirect(): void
    {
        // Regression test: this app has no named "login" route, so Laravel's
        // default redirect-to-login behavior for non-JSON requests must not
        // be triggered — see bootstrap/app.php.
        $response = $this->get('/api/user');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNames;
use App\Enums\UserStatuses;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccessGranted;
use App\Notifications\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::ADMIN);
        Role::findOrCreate(RoleNames::USER);
        config(['sanctum.stateful' => ['localhost:3001']]);
        Notification::fake();

        $this->admin = User::factory()->create();
        $this->admin->syncRoles([RoleNames::ADMIN]);
    }

    private function login(string $email, string $password)
    {
        return $this->withHeader('Origin', 'http://localhost:3001')->postJson('/api/login', compact('email', 'password'));
    }

    public function test_pending_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => UserStatuses::PENDING, 'password' => bcrypt('secret123')]);

        $this->login($user->email, 'secret123')->assertForbidden();
        $this->assertGuest();
    }

    public function test_pending_user_with_wrong_password_gets_generic_error(): void
    {
        $user = User::factory()->create(['status' => UserStatuses::PENDING, 'password' => bcrypt('secret123')]);

        $this->login($user->email, 'wrong')->assertStatus(401);
    }

    public function test_admin_approves_user_who_then_can_log_in(): void
    {
        $user = User::factory()->create(['status' => UserStatuses::PENDING, 'password' => bcrypt('secret123')]);
        $user->syncRoles([RoleNames::USER]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->identifier}/approve")
            ->assertOk()
            ->assertJsonPath('status', UserStatuses::ACTIVE);

        Notification::assertSentTo($user, AccessGranted::class);

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->login($user->email, 'secret123')->assertOk();
    }

    public function test_rejected_user_loses_access_to_open_session(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::USER]);
        $user->update(['status' => UserStatuses::REJECTED]);

        $this->actingAs($user)->getJson('/api/user')->assertStatus(401);
    }

    public function test_non_admin_cannot_approve(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::USER]);
        $pending = User::factory()->create(['status' => UserStatuses::PENDING]);

        $this->actingAs($user)->postJson("/api/admin/users/{$pending->identifier}/approve")->assertForbidden();
    }

    public function test_admin_creates_user_without_password_and_invitation_is_sent(): void
    {
        $organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Лікарня', 'edrpou' => '12345678']);

        $this->actingAs($this->admin)->postJson('/api/admin/users', [
            'name' => 'Олена',
            'email' => 'olena@example.com',
            'role' => RoleNames::USER,
            'organizationIdentifier' => $organization->identifier,
        ])->assertOk();

        $user = User::where('email', 'olena@example.com')->firstOrFail();
        $this->assertTrue($user->isActive());

        $token = null;
        Notification::assertSentTo($user, Invitation::class, function (Invitation $notification) use ($user, &$token) {
            parse_str(parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return $query['invite'] === '1';
        });

        // The invitation link sets the user's own password.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'my-own-pass',
            'password_confirmation' => 'my-own-pass',
            'invite' => true,
        ])->assertOk();

        $this->login($user->email, 'my-own-pass')->assertOk();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\ModuleKeys;
use App\Enums\RoleNames;
use App\Enums\UserStatuses;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewRegistrationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::USER);
        Role::findOrCreate(RoleNames::ADMIN);
        config(['sanctum.stateful' => ['localhost:3001']]);
        Notification::fake();
    }

    private function register(array $overrides = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3001')->postJson('/api/register', array_merge([
            'name' => 'Іван Петренко',
            'edrpou' => '12345678',
            'organizationName' => 'Клініка "Здоров\'я"',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $overrides));
    }

    public function test_registration_creates_pending_user_with_organization_and_does_not_log_in(): void
    {
        $this->register()->assertOk()->assertJsonStructure(['message']);

        $user = User::where('email', 'ivan@example.com')->firstOrFail();
        $this->assertSame(UserStatuses::PENDING, $user->status);
        $this->assertGuest();

        $organization = Organization::firstOrFail();
        $this->assertSame('12345678', $organization->edrpou);
        $this->assertSame('Клініка "Здоров\'я"', $organization->name);
        $this->assertSame($organization->id, $user->organization_id);
    }

    public function test_registration_with_known_edrpou_joins_existing_organization(): void
    {
        $organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Лікарня', 'edrpou' => '12345678']);

        $this->register(['organizationName' => ''])->assertOk();

        $this->assertSame(1, Organization::count());
        $this->assertSame($organization->id, User::where('email', 'ivan@example.com')->first()->organization_id);
    }

    public function test_organization_name_is_required_for_new_edrpou(): void
    {
        $this->register(['organizationName' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('organizationName');
    }

    public function test_edrpou_must_be_eight_digits(): void
    {
        $this->register(['edrpou' => '1234'])->assertStatus(422)->assertJsonValidationErrors('edrpou');
        $this->register(['edrpou' => '1234567a'])->assertStatus(422)->assertJsonValidationErrors('edrpou');

        $this->assertSame(0, User::count());
    }

    public function test_admins_are_notified_about_new_registration(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles([RoleNames::ADMIN]);

        $this->register()->assertOk();

        Notification::assertSentTo($admin, NewRegistrationRequest::class);
    }

    public function test_registration_enables_only_modules_marked_for_new_organizations(): void
    {
        Module::create(['key' => ModuleKeys::MATERIALS_CALCULATOR, 'name' => 'Калькулятор', 'enabled_for_new_organizations' => true]);
        Module::create(['key' => 'other_module', 'name' => 'Інший', 'enabled_for_new_organizations' => false]);

        $this->register()->assertOk();

        $user = User::where('email', 'ivan@example.com')->first();
        $this->assertTrue($user->hasModuleEnabled(ModuleKeys::MATERIALS_CALCULATOR));
        $this->assertFalse($user->hasModuleEnabled('other_module'));
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->register(['password_confirmation' => 'different'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
    }

    public function test_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'ivan@example.com']);

        $this->register()
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, Organization::count());
    }
}

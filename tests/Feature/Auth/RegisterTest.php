<?php

namespace Tests\Feature\Auth;

use App\Enums\ModuleKeys;
use App\Enums\RoleNames;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::USER);
        config(['sanctum.stateful' => ['localhost:3001']]);
    }

    private function register(array $overrides = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3001')->postJson('/api/register', array_merge([
            'name' => 'Іван Петренко',
            'organizationName' => 'Клініка "Здоров\'я"',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $overrides));
    }

    public function test_guest_can_register_and_gets_own_organization_and_is_logged_in(): void
    {
        $response = $this->register();

        $response->assertOk();
        $response->assertJsonPath('email', 'ivan@example.com');
        $response->assertJsonPath('organization.name', 'Клініка "Здоров\'я"');
        $response->assertJsonPath('roles', [RoleNames::USER]);

        $user = User::where('email', 'ivan@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, Organization::count());
        $this->assertSame($user->organization_id, Organization::first()->id);
    }

    public function test_registration_enables_only_modules_marked_for_new_organizations(): void
    {
        Module::create(['key' => ModuleKeys::MATERIALS_CALCULATOR, 'name' => 'Калькулятор', 'enabled_for_new_organizations' => true]);
        Module::create(['key' => 'other_module', 'name' => 'Інший', 'enabled_for_new_organizations' => false]);

        $response = $this->register();

        $response->assertOk();
        $response->assertJsonPath('modules', [ModuleKeys::MATERIALS_CALCULATOR]);
        $this->assertTrue(User::where('email', 'ivan@example.com')->first()->hasModuleEnabled(ModuleKeys::MATERIALS_CALCULATOR));
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

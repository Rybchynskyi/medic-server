<?php

namespace Tests\Feature\Admin;

use App\Enums\ModuleKeys;
use App\Enums\RoleNames;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_module_default_for_new_organizations(): void
    {
        Role::findOrCreate(RoleNames::ADMIN);
        $admin = User::factory()->create();
        $admin->syncRoles([RoleNames::ADMIN]);

        $module = Module::create(['key' => ModuleKeys::MATERIALS_CALCULATOR, 'name' => 'Калькулятор']);

        $this->actingAs($admin);

        $this->putJson('/api/admin/modules/'.ModuleKeys::MATERIALS_CALCULATOR, ['enabledForNewOrganizations' => true])
            ->assertOk()
            ->assertJsonPath('enabledForNewOrganizations', true);

        $this->assertTrue($module->fresh()->enabled_for_new_organizations);

        $this->getJson('/api/admin/modules')
            ->assertOk()
            ->assertJsonPath('0.enabledForNewOrganizations', true);
    }

    public function test_regular_user_cannot_change_module_defaults(): void
    {
        Role::findOrCreate(RoleNames::USER);
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::USER]);

        Module::create(['key' => ModuleKeys::MATERIALS_CALCULATOR, 'name' => 'Калькулятор']);

        $this->actingAs($user);

        $this->putJson('/api/admin/modules/'.ModuleKeys::MATERIALS_CALCULATOR, ['enabledForNewOrganizations' => true])
            ->assertForbidden();
    }
}

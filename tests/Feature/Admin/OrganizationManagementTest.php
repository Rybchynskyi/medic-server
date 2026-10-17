<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNames;
use App\Models\Organization;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_organization_details_with_stats_and_users(): void
    {
        Role::findOrCreate(RoleNames::ADMIN);
        Role::findOrCreate(RoleNames::USER);

        $admin = User::factory()->create();
        $admin->syncRoles([RoleNames::ADMIN]);

        $organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Тестова лікарня']);

        $member = User::factory()->create(['organization_id' => $organization->id]);
        $member->syncRoles([RoleNames::USER]);

        Service::create([
            'identifier' => 'svc000000001', 'organization_id' => $organization->id,
            'code' => '001', 'name' => 'Послуга', 'unit' => 'шт',
        ]);

        $this->actingAs($admin);

        $response = $this->getJson("/api/admin/organizations/{$organization->identifier}");

        $response->assertOk();
        $response->assertJsonPath('identifier', $organization->identifier);
        $response->assertJsonPath('usersCount', 1);
        $response->assertJsonPath('servicesCount', 1);
        $response->assertJsonCount(1, 'users');
        $response->assertJsonPath('users.0.identifier', $member->identifier);
    }

    public function test_non_admin_cannot_view_organization_details(): void
    {
        Role::findOrCreate(RoleNames::USER);
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::USER]);

        $organization = Organization::create(['identifier' => 'org000000002', 'name' => 'Тестова лікарня 2']);

        $this->actingAs($user);

        $this->getJson("/api/admin/organizations/{$organization->identifier}")->assertForbidden();
    }
}

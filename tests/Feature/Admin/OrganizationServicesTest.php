<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNames;
use App\Models\Material;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceMaterialNorm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationServicesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::ADMIN);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles([RoleNames::ADMIN]);

        $this->organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Тестова лікарня']);
        $this->service = Service::create([
            'identifier' => 'svc000000001', 'organization_id' => $this->organization->id, 'code' => '457',
            'name' => 'МРТ', 'unit' => 'дослідження', 'category' => 'Томографія', 'contract' => '1750/м',
        ]);
        Service::create(['identifier' => 'svc000000002', 'organization_id' => $this->organization->id, 'code' => '001', 'name' => 'Консультація']);

        foreach ([['Шприц 20,0', 'шт', 2], ['Бахіли', 'пара', 1]] as $i => [$name, $unit, $quantity]) {
            $material = Material::create(['identifier' => "mat00000000$i", 'organization_id' => $this->organization->id, 'name' => $name, 'unit' => $unit]);
            ServiceMaterialNorm::create([
                'organization_id' => $this->organization->id, 'service_id' => $this->service->id,
                'material_id' => $material->id, 'quantity_per_service' => $quantity,
            ]);
        }
    }

    public function test_admin_sees_services_with_contract_and_norms_count(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/admin/organizations/{$this->organization->identifier}/services")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.code', '001')
            ->assertJsonPath('0.normsCount', 0)
            ->assertJsonPath('1.code', '457')
            ->assertJsonPath('1.contract', '1750/м')
            ->assertJsonPath('1.category', 'Томографія')
            ->assertJsonPath('1.normsCount', 2);
    }

    public function test_admin_sees_norms_of_a_service_sorted_by_material(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/admin/organizations/{$this->organization->identifier}/services/{$this->service->identifier}/norms")
            ->assertOk()
            ->assertExactJson([
                ['material' => 'Бахіли', 'unit' => 'пара', 'quantityPerService' => 1],
                ['material' => 'Шприц 20,0', 'unit' => 'шт', 'quantityPerService' => 2],
            ]);
    }

    public function test_service_of_another_organization_is_not_found(): void
    {
        $other = Organization::create(['identifier' => 'org000000002', 'name' => 'Інша лікарня']);

        $this->actingAs($this->admin)
            ->getJson("/api/admin/organizations/{$other->identifier}/services/{$this->service->identifier}/norms")
            ->assertNotFound();
    }

    public function test_non_admin_cannot_view_directory(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson("/api/admin/organizations/{$this->organization->identifier}/services")
            ->assertForbidden();
    }
}

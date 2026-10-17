<?php

namespace Tests\Feature\Period;

use App\Enums\ModuleKeys;
use App\Enums\RoleNames;
use App\Models\Material;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceMaterialNorm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PeriodCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $serviceA;

    private Service $serviceB;

    private Material $gauze;

    private Material $alcohol;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::USER);
        Role::findOrCreate(RoleNames::ADMIN);

        $module = Module::create(['key' => ModuleKeys::MATERIALS_CALCULATOR, 'name' => 'Калькулятор']);
        $organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Тестова лікарня']);

        $this->user = User::factory()->create(['identifier' => 'usr000000001', 'organization_id' => $organization->id]);
        $this->user->syncRoles([RoleNames::USER]);
        $this->user->modules()->attach($module->id, ['enabled' => true]);

        $this->serviceA = Service::create([
            'identifier' => 'svcA00000001', 'organization_id' => $organization->id,
            'code' => '061', 'name' => 'Лазеротерапія', 'unit' => 'процедура',
        ]);
        $this->serviceB = Service::create([
            'identifier' => 'svcB00000001', 'organization_id' => $organization->id,
            'code' => '069', 'name' => 'Ударно-хвильова терапія', 'unit' => 'процедура',
        ]);

        $this->gauze = Material::create([
            'identifier' => 'matG00000001', 'organization_id' => $organization->id,
            'name' => 'Вата', 'unit' => 'г',
        ]);
        $this->alcohol = Material::create([
            'identifier' => 'matA00000001', 'organization_id' => $organization->id,
            'name' => 'Спирт 96%', 'unit' => 'г',
        ]);

        ServiceMaterialNorm::create([
            'organization_id' => $organization->id, 'service_id' => $this->serviceA->id,
            'material_id' => $this->gauze->id, 'quantity_per_service' => 3,
        ]);
        ServiceMaterialNorm::create([
            'organization_id' => $organization->id, 'service_id' => $this->serviceA->id,
            'material_id' => $this->alcohol->id, 'quantity_per_service' => 1.3,
        ]);
        ServiceMaterialNorm::create([
            'organization_id' => $organization->id, 'service_id' => $this->serviceB->id,
            'material_id' => $this->gauze->id, 'quantity_per_service' => 5,
        ]);
    }

    public function test_full_period_lifecycle_calculates_material_totals_correctly(): void
    {
        $this->actingAs($this->user);

        $openResponse = $this->postJson('/api/periods', ['month' => 1, 'year' => 2026]);
        $openResponse->assertOk();
        $periodIdentifier = $openResponse->json('identifier');

        $this->postJson("/api/periods/{$periodIdentifier}/entries", [
            'serviceIdentifier' => $this->serviceA->identifier,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson("/api/periods/{$periodIdentifier}/entries", [
            'serviceIdentifier' => $this->serviceB->identifier,
            'quantity' => 1,
        ])->assertOk();

        $closeResponse = $this->postJson("/api/periods/{$periodIdentifier}/close");
        $closeResponse->assertOk();
        $closeResponse->assertJsonPath('status', 'closed');
        $closeResponse->assertJsonCount(2, 'entries');

        $totals = collect($closeResponse->json('materialTotals'))
            ->keyBy(fn ($row) => $row['material']['identifier']);

        // Вата: 2 (service A, норма 3) + 1 (service B, норма 5) = 11
        $this->assertEquals(11, $totals[$this->gauze->identifier]['totalQuantity']);
        // Спирт: 2 * 1.3 = 2.6
        $this->assertEquals(2.6, $totals[$this->alcohol->identifier]['totalQuantity']);
    }

    public function test_entries_cannot_be_modified_after_period_is_closed(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 2, 'year' => 2026])->json();

        $this->postJson("/api/periods/{$period['identifier']}/entries", [
            'serviceIdentifier' => $this->serviceA->identifier,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/periods/{$period['identifier']}/close")->assertOk();

        $this->postJson("/api/periods/{$period['identifier']}/entries", [
            'serviceIdentifier' => $this->serviceB->identifier,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_export_is_only_available_after_closing(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 3, 'year' => 2026])->json();

        $this->get("/api/periods/{$period['identifier']}/export")->assertStatus(422);

        $this->postJson("/api/periods/{$period['identifier']}/entries", [
            'serviceIdentifier' => $this->serviceA->identifier,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson("/api/periods/{$period['identifier']}/close")->assertOk();

        $this->get("/api/periods/{$period['identifier']}/export")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_user_without_enabled_module_is_forbidden(): void
    {
        $module = Module::where('key', ModuleKeys::MATERIALS_CALCULATOR)->first();
        $this->user->modules()->updateExistingPivot($module->id, ['enabled' => false]);
        $this->actingAs($this->user);

        $this->getJson('/api/periods')->assertForbidden();
    }
}

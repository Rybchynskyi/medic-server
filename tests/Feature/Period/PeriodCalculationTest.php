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

        $this->addEntries($periodIdentifier, [$this->serviceA->identifier => 2])->assertOk();

        $this->addEntries($periodIdentifier, [$this->serviceB->identifier => 1])->assertOk();

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

        $this->addEntries($period['identifier'], [$this->serviceA->identifier => 1])->assertOk();

        $this->postJson("/api/periods/{$period['identifier']}/close")->assertOk();

        $this->addEntries($period['identifier'], [$this->serviceB->identifier => 1])->assertForbidden();
    }

    public function test_export_is_available_for_open_and_closed_periods(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 3, 'year' => 2026])->json();

        $this->addEntries($period['identifier'], [$this->serviceA->identifier => 1])->assertOk();

        $this->get("/api/periods/{$period['identifier']}/export")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->postJson("/api/periods/{$period['identifier']}/close")->assertOk();

        $this->get("/api/periods/{$period['identifier']}/export")->assertOk();
    }

    public function test_each_save_adds_to_saved_quantities_and_is_logged_with_author(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 4, 'year' => 2026])->json();

        $this->addEntries($period['identifier'], [$this->serviceA->identifier => 2, $this->serviceB->identifier => 1])
            ->assertOk()
            ->assertJsonCount(2, 'entries');

        $colleague = User::factory()->create(['name' => 'Колега', 'organization_id' => $this->user->organization_id]);
        $colleague->syncRoles([RoleNames::USER]);
        $colleague->modules()->attach(Module::first()->id, ['enabled' => true]);

        $response = $this->actingAs($colleague)->addEntries($period['identifier'], [$this->serviceA->identifier => 2]);
        $response->assertOk();
        $response->assertJsonCount(3, 'entries');
        // Newest first: the colleague's record heads the journal.
        $response->assertJsonPath('entries.0.quantity', 2);
        $response->assertJsonPath('entries.0.createdBy', 'Колега');

        $totals = collect($response->json('materialTotals'))->keyBy(fn ($row) => $row['material']['identifier']);
        // Service A: 2 + 2 = 4. Вата: 4 × 3 + 1 × 5 = 17; Спирт: 4 × 1.3 = 5.2
        $this->assertEquals(17, $totals[$this->gauze->identifier]['totalQuantity']);
        $this->assertEquals(5.2, $totals[$this->alcohol->identifier]['totalQuantity']);
    }

    public function test_deleting_a_wrong_record_recalculates_totals(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 7, 'year' => 2026])->json();
        $this->addEntries($period['identifier'], [$this->serviceB->identifier => 1])->assertOk();
        $wrong = $this->addEntries($period['identifier'], [$this->serviceB->identifier => 50])->json('entries.0');

        $response = $this->deleteJson("/api/periods/{$period['identifier']}/entries/{$wrong['identifier']}");
        $response->assertOk();
        $response->assertJsonCount(1, 'entries');
        $response->assertJsonPath('materialTotals.0.totalQuantity', 5);
    }

    public function test_correcting_a_record_recalculates_totals(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 10, 'year' => 2026])->json();
        $entry = $this->addEntries($period['identifier'], [$this->serviceB->identifier => 10])->json('entries.0');
        $url = "/api/periods/{$period['identifier']}/entries/{$entry['identifier']}";

        $response = $this->putJson($url, ['quantity' => 7]);
        $response->assertOk();
        $response->assertJsonPath('entries.0.quantity', 7);
        // Вата: 7 × 5 = 35
        $response->assertJsonPath('materialTotals.0.totalQuantity', 35);

        $this->putJson($url, ['quantity' => 0])->assertStatus(422);
    }

    public function test_closed_period_can_be_reopened_corrected_and_closed_again(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 8, 'year' => 2026])->json();
        $url = "/api/periods/{$period['identifier']}";
        $this->addEntries($period['identifier'], [$this->serviceB->identifier => 1])->assertOk();
        $this->postJson("{$url}/close")->assertOk();

        $this->postJson("{$url}/reopen")->assertOk()->assertJsonPath('status', 'open');
        $this->postJson("{$url}/reopen")->assertForbidden();

        $this->addEntries($period['identifier'], [$this->serviceB->identifier => 1])->assertOk();
        $response = $this->postJson("{$url}/close");
        $response->assertOk();
        $response->assertJsonPath('status', 'closed');
        $response->assertJsonCount(1, 'materialTotals');
        $response->assertJsonPath('materialTotals.0.totalQuantity', 10);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $this->actingAs($this->user);

        $period = $this->postJson('/api/periods', ['month' => 9, 'year' => 2026])->json();

        $this->addEntries($period['identifier'], [$this->serviceA->identifier => 0])->assertStatus(422);
    }

    public function test_entries_reject_services_of_another_organization(): void
    {
        $this->actingAs($this->user);

        $otherOrganization = Organization::create(['identifier' => 'org000000002', 'name' => 'Інша лікарня']);
        $foreignService = Service::create([
            'identifier' => 'svcX00000001', 'organization_id' => $otherOrganization->id,
            'code' => '061', 'name' => 'Чужа послуга',
        ]);

        $period = $this->postJson('/api/periods', ['month' => 5, 'year' => 2026])->json();

        $this->addEntries($period['identifier'], [$foreignService->identifier => 1])->assertStatus(422);
    }

    public function test_user_without_enabled_module_is_forbidden(): void
    {
        $module = Module::where('key', ModuleKeys::MATERIALS_CALCULATOR)->first();
        $this->user->modules()->updateExistingPivot($module->id, ['enabled' => false]);
        $this->actingAs($this->user);

        $this->getJson('/api/periods')->assertForbidden();
    }

    public function test_admin_without_organization_is_forbidden_instead_of_crashing(): void
    {
        $admin = User::factory()->create(['organization_id' => null]);
        $admin->syncRoles([RoleNames::ADMIN]);
        $this->actingAs($admin);

        $this->getJson('/api/periods')->assertForbidden();
    }

    /**
     * @param  array<string, float>  $quantitiesByServiceIdentifier
     */
    private function addEntries(string $periodIdentifier, array $quantitiesByServiceIdentifier)
    {
        $entries = collect($quantitiesByServiceIdentifier)
            ->map(fn ($quantity, $identifier) => ['serviceIdentifier' => $identifier, 'quantity' => $quantity])
            ->values()
            ->all();

        return $this->postJson("/api/periods/{$periodIdentifier}/entries", ['entries' => $entries]);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNames;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceMaterialNorm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectoryImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::ADMIN);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles([RoleNames::ADMIN]);

        $this->organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Тестова лікарня']);
    }

    public function test_norms_are_imported_from_mat_sheet_and_missing_services_are_created(): void
    {
        $file = $this->makeWorkbook([
            'НЧ' => [['Норми часу'], ['999', 'Не та таблиця', 'Щось', 'шт', 100]],
            'МАТ' => [
                ['Код послуги', 'Найменування послуги', 'Матеріал', 'Одиниця виміру', 'Витрати на одну послугу'],
                ['1084', 'Консультація ерготерапевта', 'Маска медична одноразова', 'шт', 1],
                ['1084', null, 'Рукавички одноразові', 'пара', 1],
                ['1100', 'Транспортування хворого', 'Бензин (дизтопливо)', 'л', 0.129],
                ['068', 'Тубус кварц', null, null, null],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post("/api/admin/organizations/{$this->organization->identifier}/import/material-norms", ['file' => $file])
            ->assertOk()
            ->assertJsonPath('message', 'Імпортовано послуг: 3, норм витрат: 3.');

        $services = Service::where('organization_id', $this->organization->id)->orderBy('code')->pluck('name', 'code');
        $this->assertSame([
            '068' => 'Тубус кварц',
            '1084' => 'Консультація ерготерапевта',
            '1100' => 'Транспортування хворого',
        ], $services->all());

        $this->assertSame(3, ServiceMaterialNorm::count());
    }

    public function test_sections_and_units_are_taken_from_nch_sheet_and_wrong_names_are_fixed(): void
    {
        Service::create(['identifier' => 'srv000000001', 'organization_id' => $this->organization->id, 'code' => '060', 'name' => '37.355239254793']);

        $file = $this->makeWorkbook([
            'КАЛЬК_2_1' => [['КОД ПОСЛУГИ', 'ПРЯМІ ВИТРАТИ'], ['060', 37.35]],
            'НЧ' => [
                ['Код послуги', 'Найменування послуги', 'Одиниця виміру'],
                [null, 'Фізіотерапія'],
                ['060', 'Міостимуляція', 'процедура'],
                [null, 'Інші послуги'],
                ['070', 'Білірубінометрія', 'дослідження'],
            ],
            'МАТ' => [
                ['Код послуги', 'Найменування послуги', 'Матеріал', 'Одиниця виміру', 'Витрати на одну послугу'],
                ['060', 'Міостимуляція', 'Гель', 'г', 5],
                ['070', 'Білірубінометрія', 'Серветка', 'шт', 1],
            ],
        ]);

        $this->import($file)->assertOk();

        $services = Service::orderBy('code')->get(['code', 'name', 'unit', 'category'])->toArray();
        $this->assertSame([
            ['code' => '060', 'name' => 'Міостимуляція', 'unit' => 'процедура', 'category' => 'Фізіотерапія'],
            ['code' => '070', 'name' => 'Білірубінометрія', 'unit' => 'дослідження', 'category' => 'Інші послуги'],
        ], $services);
    }

    public function test_reimport_deletes_norms_missing_from_the_file(): void
    {
        $this->import($this->makeWorkbook(['МАТ' => [
            ['Код послуги', 'Найменування послуги', 'Матеріал', 'Одиниця виміру', 'Витрати на одну послугу'],
            ['1084', 'Консультація', 'Маска', 'шт', 1],
            ['1084', null, 'Рукавички', 'пара', 1],
        ]]))->assertOk();

        $this->import($this->makeWorkbook(['МАТ' => [
            ['Код послуги', 'Найменування послуги', 'Матеріал', 'Одиниця виміру', 'Витрати на одну послугу'],
            ['1084', 'Консультація', 'Маска', 'шт', 2],
        ]]))
            ->assertOk()
            ->assertJsonPath('message', 'Імпортовано послуг: 1, норм витрат: 1. Видалено норм, яких немає у файлі: 1.');

        $norm = ServiceMaterialNorm::with('material')->sole();
        $this->assertSame('Маска', $norm->material->name);
        $this->assertEquals(2, $norm->quantity_per_service);
    }

    public function test_sheet_with_numbers_instead_of_service_names_is_rejected(): void
    {
        $file = $this->makeWorkbook(['Лист1' => [
            ['КОД ПОСЛУГИ', 'ПРЯМІ ВИТРАТИ'],
            ['1084', 37.355239254793, 0, 3.1],
        ]]);

        $this->import($file)->assertStatus(422);

        $this->assertSame(0, Service::count());
    }

    public function test_workbook_without_mat_sheet_is_rejected(): void
    {
        $file = $this->makeWorkbook([
            'НЧ' => [['Код послуги', 'Найменування послуги'], ['060', 'Міостимуляція']],
            'ФОП' => [['Код послуги'], ['060']],
        ]);

        $this->import($file)
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'У файлі немає листа «МАТ» з нормами витрат матеріалів.');
    }

    private function import(UploadedFile $file)
    {
        return $this->actingAs($this->admin)
            ->postJson("/api/admin/organizations/{$this->organization->identifier}/import/material-norms", ['file' => $file]);
    }

    private function makeWorkbook(array $sheets): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            foreach ($rows as $index => $row) {
                foreach (array_values($row) as $column => $value) {
                    if ($value !== null) {
                        $sheet->setCellValueExplicit(
                            [$column + 1, $index + 1],
                            $value,
                            is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC
                        );
                    }
                }
            }
        }
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', null, null, true);
    }
}

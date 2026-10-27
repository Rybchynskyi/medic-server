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
            ->assertJsonPath('message', 'Імпортовано норм витрат: 3.');

        $services = Service::where('organization_id', $this->organization->id)->orderBy('code')->pluck('name', 'code');
        $this->assertSame([
            '068' => 'Тубус кварц',
            '1084' => 'Консультація ерготерапевта',
            '1100' => 'Транспортування хворого',
        ], $services->all());

        $this->assertSame(3, ServiceMaterialNorm::count());
    }

    public function test_services_import_stores_section_headers_as_category(): void
    {
        $file = $this->makeWorkbook([
            'Лист1' => [
                ['Код послуги', 'Найменування послуги', 'Одиниця виміру'],
                [null, 'Фізіотерапія'],
                ['060', 'Міостимуляція', 'процедура'],
                [null, 'Інші послуги'],
                ['070', 'Білірубінометрія', 'дослідження'],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post("/api/admin/organizations/{$this->organization->identifier}/import/services", ['file' => $file])
            ->assertOk();

        $this->assertSame([
            '060' => 'Фізіотерапія',
            '070' => 'Інші послуги',
        ], Service::orderBy('code')->pluck('category', 'code')->all());
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

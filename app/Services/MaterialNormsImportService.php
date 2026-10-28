<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\MaterialRepository;
use App\Repositories\ServiceMaterialNormRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaterialNormsImportService extends CoreService
{
    /** Sheet names (Cyrillic and Latin spelling) used in calculation workbooks. */
    private const NORMS_SHEET_NAMES = ['МАТ', 'MAT'];

    private const SERVICES_SHEET_NAMES = ['НЧ', 'HЧ'];

    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly MaterialRepository $materialRepository,
        private readonly ServiceMaterialNormRepository $normRepository
    ) {}

    /**
     * Imports a calculation workbook. The "МАТ" sheet (Код послуги | Найменування послуги | Найменування матеріалу |
     * Одиниця виміру | Витрати на 1 послугу) gives the services and their norms; the optional "НЧ" sheet
     * (Код послуги | Найменування послуги | Одиниця виміру, section rows without a code) gives sections and units.
     * Afterwards the organization's norms match the file exactly: norms missing from it are deleted.
     *
     * @return array{services: int, norms: int, deletedNorms: int}
     */
    public function import(Organization $organization, UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $normRows = $this->parseNormsSheet($this->resolveNormsSheet($spreadsheet));
        $servicesSheet = $this->findSheet($spreadsheet, self::SERVICES_SHEET_NAMES);
        $serviceDetails = $servicesSheet ? $this->parseServicesSheet($servicesSheet) : null;

        return DB::transaction(function () use ($organization, $normRows, $serviceDetails) {
            $servicesByCode = [];
            foreach ($normRows['services'] as $code => $name) {
                $servicesByCode[$code] = $this->saveService($organization->id, $code, $name, $serviceDetails[$code] ?? null, $serviceDetails !== null);
            }

            $keptNormIds = [];
            foreach ($normRows['norms'] as $norm) {
                $material = $this->materialRepository->findByOrganizationNameAndUnit($organization->id, $norm['material'], $norm['unit'])
                    ?? $this->materialRepository->create([
                        'organization_id' => $organization->id,
                        'name' => $norm['material'],
                        'unit' => $norm['unit'],
                    ]);

                $keptNormIds[] = $this->normRepository->updateOrCreate(
                    $organization->id,
                    $servicesByCode[$norm['code']]->id,
                    $material->id,
                    $norm['quantity']
                )->id;
            }

            return [
                'services' => count($servicesByCode),
                'norms' => count($keptNormIds),
                'deletedNorms' => $this->normRepository->deleteForOrganizationExcept($organization->id, $keptNormIds),
            ];
        });
    }

    /**
     * @return array{services: array<string, string>, norms: list<array{code: string, material: string, unit: string, quantity: float}>}
     */
    private function parseNormsSheet(Worksheet $sheet): array
    {
        $services = [];
        $norms = [];

        foreach (array_slice($sheet->toArray(null, true, false, false), 1) as $row) {
            $code = $this->cell($row, 0);
            $serviceName = $this->cell($row, 1);
            $materialName = $this->cell($row, 2);
            $unit = $this->cell($row, 3);
            $quantity = $row[4] ?? null;

            if ($code === '') {
                continue;
            }

            if ($serviceName !== '' && ! isset($services[$code])) {
                $this->assertLooksLikeName($serviceName, $code);
                $services[$code] = $serviceName;
            }

            if ($materialName === '' || $quantity === null || $quantity === '') {
                continue;
            }

            $norms[] = ['code' => $code, 'material' => $materialName, 'unit' => $unit, 'quantity' => (float) $quantity];
        }

        foreach ($norms as $norm) {
            if (! isset($services[$norm['code']])) {
                throw ValidationException::withMessages([
                    'file' => "На листі «МАТ» для послуги з кодом {$norm['code']} не вказано найменування.",
                ]);
            }
        }

        if (empty($services)) {
            throw ValidationException::withMessages([
                'file' => 'На листі «МАТ» не знайдено жодної послуги. Перевірте, що це файл калькуляції.',
            ]);
        }

        return ['services' => $services, 'norms' => $norms];
    }

    /**
     * Rows without a code are section headers and become the category of the services below them.
     *
     * @return array<string, array{category: ?string, unit: ?string}>
     */
    private function parseServicesSheet(Worksheet $sheet): array
    {
        $details = [];
        $category = null;

        foreach (array_slice($sheet->toArray(null, true, false, false), 1) as $row) {
            $code = $this->cell($row, 0);
            $name = $this->cell($row, 1);
            $unit = $this->cell($row, 2);

            if ($code === '') {
                if ($name !== '') {
                    $category = $name;
                }

                continue;
            }

            $details[$code] = ['category' => $category, 'unit' => $unit ?: null];
        }

        return $details;
    }

    /**
     * Without a "НЧ" sheet the existing section and unit are kept.
     */
    private function saveService(int $organizationId, string $code, string $name, ?array $details, bool $hasServicesSheet)
    {
        $attributes = ['name' => $name];
        if ($hasServicesSheet) {
            $attributes['category'] = $details['category'] ?? null;
            $attributes['unit'] = $details['unit'] ?? null;
        }

        $service = $this->serviceRepository->findByOrganizationAndCode($organizationId, $code);
        if ($service) {
            $service->fill($attributes);
            $this->serviceRepository->save($service);

            return $service;
        }

        return $this->serviceRepository->create(['organization_id' => $organizationId, 'code' => $code] + $attributes);
    }

    /**
     * Guards against a wrong sheet, e.g. a cost table where the second column holds amounts.
     */
    private function assertLooksLikeName(string $name, string $code): void
    {
        if (is_numeric(str_replace(',', '.', $name))) {
            throw ValidationException::withMessages([
                'file' => "Схоже, це не той лист: замість найменування послуги {$code} стоїть число «{$name}».",
            ]);
        }
    }

    private function resolveNormsSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $sheet = $this->findSheet($spreadsheet, self::NORMS_SHEET_NAMES);
        if ($sheet) {
            return $sheet;
        }

        if ($spreadsheet->getSheetCount() === 1) {
            return $spreadsheet->getActiveSheet();
        }

        throw ValidationException::withMessages([
            'file' => 'У файлі немає листа «МАТ» з нормами витрат матеріалів.',
        ]);
    }

    private function findSheet(Spreadsheet $spreadsheet, array $names): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (in_array(mb_strtoupper(trim($sheet->getTitle())), $names, true)) {
                return $sheet;
            }
        }

        return null;
    }

    private function cell(array $row, int $index): string
    {
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }
}

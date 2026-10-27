<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\MaterialRepository;
use App\Repositories\ServiceMaterialNormRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaterialNormsImportService extends CoreService
{
    /** Sheet names (Cyrillic and Latin spelling) that hold the norms in calculation workbooks. */
    private const NORMS_SHEET_NAMES = ['МАТ', 'MAT'];

    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly MaterialRepository $materialRepository,
        private readonly ServiceMaterialNormRepository $normRepository
    ) {}

    /**
     * Imports "Норми витрат матеріалів": Код послуги | Найменування послуги | Найменування матеріалу | Одиниця виміру | Витрати на 1 послугу.
     * Reads the "МАТ" sheet when the workbook has one, otherwise the active sheet.
     * Services missing from the directory are created from the code and name in the file.
     */
    public function import(Organization $organization, UploadedFile $file): int
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = array_slice($this->resolveSheet($spreadsheet)->toArray(null, true, false, false), 1);

        $imported = 0;

        foreach ($rows as $row) {
            $code = isset($row[0]) ? trim((string) $row[0]) : '';
            $serviceName = isset($row[1]) ? trim((string) $row[1]) : '';
            $materialName = isset($row[2]) ? trim((string) $row[2]) : '';
            $unit = isset($row[3]) ? trim((string) $row[3]) : '';
            $quantity = $row[4] ?? null;

            if ($code === '') {
                continue;
            }

            $service = $this->serviceRepository->findByOrganizationAndCode($organization->id, $code);
            if (! $service) {
                if ($serviceName === '') {
                    continue;
                }
                $service = $this->serviceRepository->create([
                    'organization_id' => $organization->id,
                    'code' => $code,
                    'name' => $serviceName,
                ]);
            }

            if ($materialName === '' || $quantity === null || $quantity === '') {
                continue;
            }

            $material = $this->materialRepository->findByOrganizationNameAndUnit($organization->id, $materialName, $unit);
            if (! $material) {
                $material = $this->materialRepository->create([
                    'organization_id' => $organization->id,
                    'name' => $materialName,
                    'unit' => $unit,
                ]);
            }

            $this->normRepository->updateOrCreate(
                $organization->id,
                $service->id,
                $material->id,
                (float) $quantity
            );

            $imported++;
        }

        return $imported;
    }

    private function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (in_array(mb_strtoupper(trim($sheet->getTitle())), self::NORMS_SHEET_NAMES, true)) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }
}

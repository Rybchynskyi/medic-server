<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\MaterialRepository;
use App\Repositories\ServiceMaterialNormRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MaterialNormsImportService extends CoreService
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly MaterialRepository $materialRepository,
        private readonly ServiceMaterialNormRepository $normRepository
    ) {}

    /**
     * Imports "Норми витрат матеріалів": Код послуги | Найменування послуги | Найменування матеріалу | Одиниця виміру | Витрати на 1 послугу.
     * The service code must already exist (imported from "Перелік послуг" beforehand).
     */
    public function import(Organization $organization, UploadedFile $file): int
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = array_slice($spreadsheet->getActiveSheet()->toArray(null, true, false, false), 1);

        $missingCodes = [];
        foreach ($rows as $row) {
            $code = isset($row[0]) ? trim((string) $row[0]) : '';

            if ($code === '') {
                continue;
            }

            if (! $this->serviceRepository->findByOrganizationAndCode($organization->id, $code)) {
                $missingCodes[$code] = true;
            }
        }

        if (! empty($missingCodes)) {
            throw ValidationException::withMessages([
                'file' => 'Відсутні послуги з кодами: '.implode(', ', array_keys($missingCodes)).'. Спочатку імпортуйте «Перелік послуг».',
            ]);
        }

        $imported = 0;

        foreach ($rows as $row) {
            $code = isset($row[0]) ? trim((string) $row[0]) : '';
            $materialName = isset($row[2]) ? trim((string) $row[2]) : '';
            $unit = isset($row[3]) ? trim((string) $row[3]) : '';
            $quantity = $row[4] ?? null;

            if ($code === '' || $materialName === '' || $quantity === null || $quantity === '') {
                continue;
            }

            $service = $this->serviceRepository->findByOrganizationAndCode($organization->id, $code);

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
}

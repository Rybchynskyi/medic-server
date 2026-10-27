<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\ServiceRepository;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ServicesImportService extends CoreService
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository
    ) {}

    /**
     * Imports the "Перелік послуг" directory: Код послуги | Найменування послуги | Одиниця виміру.
     * Rows without a code are section headers (e.g. "Фізіотерапія") and become the category of the services below them.
     */
    public function import(Organization $organization, UploadedFile $file): int
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        $imported = 0;
        $category = null;

        foreach (array_slice($rows, 1) as $row) {
            $code = isset($row[0]) ? trim((string) $row[0]) : '';
            $name = isset($row[1]) ? trim((string) $row[1]) : '';
            $unit = isset($row[2]) ? trim((string) $row[2]) : '';

            if ($code === '') {
                if ($name !== '') {
                    $category = $name;
                }

                continue;
            }

            if ($name === '') {
                continue;
            }

            $service = $this->serviceRepository->findByOrganizationAndCode($organization->id, $code);

            if ($service) {
                $service->fill(['name' => $name, 'unit' => $unit ?: null, 'category' => $category]);
                $this->serviceRepository->save($service);
            } else {
                $this->serviceRepository->create([
                    'organization_id' => $organization->id,
                    'code' => $code,
                    'name' => $name,
                    'unit' => $unit ?: null,
                    'category' => $category,
                ]);
            }

            $imported++;
        }

        return $imported;
    }
}

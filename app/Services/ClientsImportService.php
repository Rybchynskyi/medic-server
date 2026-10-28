<?php

namespace App\Services;

use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClientsImportService extends CoreService
{
    /** Only clients whose registry state is this are imported. */
    private const REGISTERED_STATE = 'зареєстровано';

    /** Column key => header prefix in the client base workbook (matched case-insensitively). */
    private const COLUMNS = [
        'our_name' => 'наша назва',
        'edrpou' => 'єдрпоу',
        'contracts' => 'договори',
        'state' => 'стан у єдр',
        'registry_name' => 'назва в єдр',
        'director' => 'керівник',
        'address' => 'адреса',
        'contact_person' => 'контактна особа',
        'contact_phone' => 'телефон',
        'contact_email' => 'email',
        'admin_note' => 'примітка',
    ];

    private const REQUIRED_COLUMNS = ['edrpou', 'state', 'our_name', 'registry_name'];

    public function __construct(
        private readonly OrganizationRepository $organizationRepository
    ) {}

    /**
     * Imports the client base: one organization per EDRPOU, named after the registry with our name kept aside.
     * Existing organizations only get their empty fields filled, so manual edits survive a re-import.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(UploadedFile $file): array
    {
        $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        $columns = $this->resolveColumns(array_shift($rows) ?? []);

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $columns, &$result) {
            foreach ($rows as $row) {
                $values = array_map(fn ($index) => $this->cell($row, $index), $columns);
                $edrpou = $values['edrpou'] ?? '';

                if ($edrpou === '') {
                    continue;
                }

                if (mb_strtolower($values['state'] ?? '') !== self::REGISTERED_STATE || ! preg_match('/^\d{8}$/', $edrpou)) {
                    $result['skipped']++;

                    continue;
                }

                $details = array_filter(
                    array_intersect_key($values, array_flip(Organization::CLIENT_DETAIL_FIELDS)),
                    fn ($value) => $value !== ''
                );
                if (isset($details['contact_email']) && ! filter_var($details['contact_email'], FILTER_VALIDATE_EMAIL)) {
                    unset($details['contact_email']);
                }

                $organization = $this->organizationRepository->findByEdrpou($edrpou);
                if (! $organization) {
                    $name = ($values['registry_name'] ?? '') ?: ($values['our_name'] ?? '');
                    $this->organizationRepository->create(['name' => mb_substr($name, 0, 255), 'edrpou' => $edrpou] + $details);
                    $result['created']++;

                    continue;
                }

                $empty = array_filter($details, fn ($value, $field) => blank($organization->{$field}), ARRAY_FILTER_USE_BOTH);
                if (! empty($empty)) {
                    $organization->fill($empty);
                    $this->organizationRepository->save($organization);
                    $result['updated']++;
                }
            }
        });

        return $result;
    }

    /**
     * @return array<string, int> column key => index
     */
    private function resolveColumns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim((string) $title));
            foreach (self::COLUMNS as $key => $prefix) {
                if (! isset($columns[$key]) && str_starts_with($title, $prefix)) {
                    $columns[$key] = $index;
                    break;
                }
            }
        }

        $missing = array_diff(self::REQUIRED_COLUMNS, array_keys($columns));
        if (! empty($missing)) {
            $titles = array_map(fn ($key) => '«'.self::COLUMNS[$key].'»', $missing);
            throw ValidationException::withMessages([
                'file' => 'У першому рядку файлу не знайдено колонок: '.implode(', ', $titles).'.',
            ]);
        }

        return $columns;
    }

    private function cell(array $row, int $index): string
    {
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }
}

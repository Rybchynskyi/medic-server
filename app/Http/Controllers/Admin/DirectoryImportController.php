<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportDirectoryRequest;
use App\Services\MaterialNormsImportService;
use App\Services\OrganizationService;

class DirectoryImportController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly MaterialNormsImportService $materialNormsImportService
    ) {}

    public function materialNorms(ImportDirectoryRequest $request, string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $result = $this->materialNormsImportService->import($organization, $request->file('file'));

        $message = "Імпортовано послуг: {$result['services']}, норм витрат: {$result['norms']}.";
        if ($result['deletedNorms'] > 0) {
            $message .= " Видалено норм, яких немає у файлі: {$result['deletedNorms']}.";
        }

        return $this->successResponse($message);
    }
}

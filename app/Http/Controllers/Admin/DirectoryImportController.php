<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportDirectoryRequest;
use App\Services\MaterialNormsImportService;
use App\Services\OrganizationService;
use App\Services\ServicesImportService;

class DirectoryImportController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly ServicesImportService $servicesImportService,
        private readonly MaterialNormsImportService $materialNormsImportService
    ) {}

    public function services(ImportDirectoryRequest $request, string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $count = $this->servicesImportService->import($organization, $request->file('file'));

        return $this->successResponse("Імпортовано послуг: {$count}.");
    }

    public function materialNorms(ImportDirectoryRequest $request, string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $count = $this->materialNormsImportService->import($organization, $request->file('file'));

        return $this->successResponse("Імпортовано норм витрат: {$count}.");
    }
}

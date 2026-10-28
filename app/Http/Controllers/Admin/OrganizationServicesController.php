<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Presenters\ServiceForAdminPresenter;
use App\Presenters\ServiceMaterialNormPresenter;
use App\Repositories\ServiceMaterialNormRepository;
use App\Repositories\ServiceRepository;
use App\Services\OrganizationService;

/**
 * Read-only view of an organization's services directory for admins.
 */
class OrganizationServicesController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly ServiceRepository $serviceRepository,
        private readonly ServiceMaterialNormRepository $normRepository
    ) {}

    public function index(string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);

        return $this->present(
            $this->serviceRepository->allForOrganizationWithNormsCount($organization->id),
            ServiceForAdminPresenter::class
        );
    }

    public function norms(string $identifier, string $serviceIdentifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $service = $this->serviceRepository->findByIdentifierForOrganization($serviceIdentifier, $organization->id);
        abort_if(! $service, 404);

        $norms = $this->normRepository->findByServiceIds([$service->id])->sortBy('material.name', SORT_NATURAL)->values();

        return $this->present($norms, ServiceMaterialNormPresenter::class);
    }
}

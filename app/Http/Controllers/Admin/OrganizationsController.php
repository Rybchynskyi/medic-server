<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Presenters\OrganizationPresenter;
use App\Services\OrganizationService;

class OrganizationsController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService
    ) {}

    public function index()
    {
        return $this->present($this->organizationService->all(), OrganizationPresenter::class);
    }

    public function show(string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierWithDetailsOrFail($identifier);

        return $this->present($organization, OrganizationPresenter::class, ['users']);
    }

    public function store(CreateOrganizationRequest $request)
    {
        $organization = $this->organizationService->create($request->validated());

        return $this->present($organization, OrganizationPresenter::class, [], 'Установу створено.');
    }

    public function update(UpdateOrganizationRequest $request, string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $organization = $this->organizationService->update($organization, $request->validated());

        return $this->present($organization, OrganizationPresenter::class, [], 'Установу оновлено.');
    }

    public function destroy(string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $this->organizationService->delete($organization);

        return $this->successResponse('Установу видалено.');
    }
}

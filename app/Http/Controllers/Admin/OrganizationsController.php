<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrganizationRequest;
use App\Http\Requests\Admin\ImportClientsRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Presenters\OrganizationPresenter;
use App\Services\ClientsImportService;
use App\Services\OrganizationService;

class OrganizationsController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly ClientsImportService $clientsImportService
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

    public function importClients(ImportClientsRequest $request)
    {
        $result = $this->clientsImportService->import($request->file('file'));

        return $this->successResponse(
            "Додано нових установ: {$result['created']}, доповнено даними: {$result['updated']}. "
            ."Пропущено (не «Зареєстровано» у ЄДР): {$result['skipped']}."
        );
    }

    public function invite(string $identifier)
    {
        $organization = $this->organizationService->invite($this->organizationService->findByIdentifierOrFail($identifier));

        return $this->present(
            $organization,
            OrganizationPresenter::class,
            [],
            "Запрошення надіслано на {$organization->contact_email}."
        );
    }

    public function destroy(string $identifier)
    {
        $organization = $this->organizationService->findByIdentifierOrFail($identifier);
        $this->organizationService->delete($organization);

        return $this->successResponse('Установу видалено.');
    }
}

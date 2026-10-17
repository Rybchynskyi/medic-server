<?php

namespace App\Http\Controllers;

use App\Http\Requests\Period\OpenPeriodRequest;
use App\Http\Requests\Period\StoreEntryRequest;
use App\Http\Requests\Period\UpdateEntryRequest;
use App\Presenters\Period\PeriodPresenter;
use App\Repositories\PeriodEntryRepository;
use App\Repositories\ServiceRepository;
use App\Services\PeriodExportService;
use App\Services\PeriodsService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PeriodsController extends Controller
{
    public function __construct(
        private readonly PeriodsService $periodsService,
        private readonly ServiceRepository $serviceRepository,
        private readonly PeriodEntryRepository $periodEntryRepository,
        private readonly PeriodExportService $periodExportService
    ) {}

    public function index(Request $request)
    {
        $periods = $this->periodsService->allForOrganization($request->user()->organization);

        return $this->present($periods, PeriodPresenter::class);
    }

    public function store(OpenPeriodRequest $request)
    {
        $period = $this->periodsService->open(
            $request->user()->organization,
            $request->user(),
            (int) $request->validated('month'),
            (int) $request->validated('year')
        );

        return $this->present($period, PeriodPresenter::class, [], 'Період відкрито.');
    }

    public function show(Request $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail(
            $identifier,
            $request->user()->organization,
            ['entries.service', 'materialTotals.material']
        );
        $this->authorize('hasAccess', $period);

        return $this->present($period, PeriodPresenter::class, ['entries', 'materialTotals']);
    }

    public function storeEntry(StoreEntryRequest $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $service = $this->serviceRepository->findByIdentifierForOrganization(
            $request->validated('serviceIdentifier'),
            $request->user()->organization_id
        );

        $this->periodsService->addEntry($period, $service->id, (float) $request->validated('quantity'));

        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization, ['entries.service']);

        return $this->present($period, PeriodPresenter::class, ['entries'], 'Послугу додано.');
    }

    public function updateEntry(UpdateEntryRequest $request, string $identifier, string $entryIdentifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $entry = $this->periodEntryRepository->findByIdentifierForPeriod($entryIdentifier, $period->id);
        abort_if(! $entry, 404);

        $this->periodsService->updateEntry($entry, (float) $request->validated('quantity'));

        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization, ['entries.service']);

        return $this->present($period, PeriodPresenter::class, ['entries'], 'Запис оновлено.');
    }

    public function destroyEntry(Request $request, string $identifier, string $entryIdentifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $entry = $this->periodEntryRepository->findByIdentifierForPeriod($entryIdentifier, $period->id);
        abort_if(! $entry, 404);

        $this->periodsService->deleteEntry($entry);

        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization, ['entries.service']);

        return $this->present($period, PeriodPresenter::class, ['entries'], 'Запис видалено.');
    }

    public function close(Request $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $period = $this->periodsService->close($period);
        $period->load('entries.service', 'materialTotals.material');

        return $this->present($period, PeriodPresenter::class, ['entries', 'materialTotals'], 'Період закрито, розрахунок виконано.');
    }

    public function export(Request $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization, ['organization']);
        $this->authorize('hasAccess', $period);

        abort_if($period->isOpen(), 422, 'Період ще не закрито.');

        $spreadsheet = $this->periodExportService->buildSpreadsheet($period);
        $fileName = $this->periodExportService->buildFileName($period);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Period\OpenPeriodRequest;
use App\Http\Requests\Period\StoreEntryRequest;
use App\Http\Requests\Period\UpdateEntryRequest;
use App\Models\Period;
use App\Presenters\Period\PeriodPresenter;
use App\Repositories\PeriodEntryRepository;
use App\Repositories\ServiceRepository;
use App\Services\PeriodExportService;
use App\Services\PeriodsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $period = $this->findWithEntries($identifier, $request);
        $this->authorize('hasAccess', $period);

        return $this->presentWithTotals($period);
    }

    public function storeEntry(StoreEntryRequest $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $items = collect($request->validated('entries'));
        $services = $this->serviceRepository
            ->findByIdentifiersForOrganization($items->pluck('serviceIdentifier')->all(), $request->user()->organization_id)
            ->keyBy('identifier');

        if ($services->count() !== $items->count()) {
            throw ValidationException::withMessages([
                'entries' => 'Деякі послуги не знайдено в довіднику організації. Оновіть сторінку.',
            ]);
        }

        $quantities = $items
            ->mapWithKeys(fn ($item) => [$services[$item['serviceIdentifier']]->id => (float) $item['quantity']])
            ->all();

        $this->periodsService->addEntries($period, $request->user(), $quantities);

        return $this->presentWithTotals($this->findWithEntries($identifier, $request), 'Кількості додано до збережених.');
    }

    public function updateEntry(UpdateEntryRequest $request, string $identifier, string $entryIdentifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $entry = $this->periodEntryRepository->findByIdentifierForPeriod($entryIdentifier, $period->id);
        abort_if(! $entry, 404);

        $this->periodsService->updateEntry($entry, (float) $request->validated('quantity'));

        return $this->presentWithTotals($this->findWithEntries($identifier, $request), 'Запис оновлено.');
    }

    public function destroyEntry(Request $request, string $identifier, string $entryIdentifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $entry = $this->periodEntryRepository->findByIdentifierForPeriod($entryIdentifier, $period->id);
        abort_if(! $entry, 404);

        $this->periodsService->deleteEntry($entry);

        return $this->presentWithTotals($this->findWithEntries($identifier, $request), 'Запис видалено.');
    }

    public function close(Request $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canEdit', $period);

        $this->periodsService->close($period);

        return $this->presentWithTotals($this->findWithEntries($identifier, $request), 'Період закрито, розрахунок виконано.');
    }

    public function reopen(Request $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization);
        $this->authorize('canReopen', $period);

        $this->periodsService->reopen($period);

        return $this->presentWithTotals(
            $this->findWithEntries($identifier, $request),
            'Період знову відкрито. Після виправлень закрийте його ще раз.'
        );
    }

    public function export(Request $request, string $identifier)
    {
        $period = $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization, ['organization']);
        $this->authorize('hasAccess', $period);

        $spreadsheet = $this->periodExportService->buildSpreadsheet($period, $this->periodsService->materialTotalsFor($period));
        $fileName = $this->periodExportService->buildFileName($period);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Entries come newest first, as a journal of who added what and when.
     */
    private function findWithEntries(string $identifier, Request $request): Period
    {
        return $this->periodsService->findByIdentifierForOrganizationOrFail($identifier, $request->user()->organization, [
            'entries' => fn ($query) => $query->with('service', 'creator')->orderByDesc('id'),
        ]);
    }

    private function presentWithTotals(Period $period, ?string $message = null)
    {
        $period->setRelation('materialTotals', $this->periodsService->materialTotalsFor($period));

        return $this->present($period, PeriodPresenter::class, ['entries', 'materialTotals'], $message);
    }
}

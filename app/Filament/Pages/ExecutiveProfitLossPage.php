<?php

namespace App\Filament\Pages;

use App\Enums\EventAnalyticsPhase;
use App\Enums\ProfitRecognitionMode;
use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateResource;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventTemplate;
use App\Services\ExecutiveProfitLossService;
use App\Support\ExecutiveAccess;
use App\Support\ExecutiveModuleNavigation;
use App\Support\FilamentNavigation;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutiveProfitLossPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.executive-profit-loss';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EXECUTIVE;

    protected static ?string $navigationLabel = 'Zyski i straty';

    protected static ?int $navigationSort = 1;

    /** @var list<string> */
    public const SORTABLE = [
        'event_name',
        'event_date',
        'client_name',
        'phase',
        'revenue_pln',
        'costs_pln',
        'net_result_pln',
        'cost_outstanding_pln',
        'receivables_pln',
        'margin_recognized_percent',
    ];

    #[Url(as: 'from', except: '')]
    public ?string $filterDateFrom = null;

    #[Url(as: 'to', except: '')]
    public ?string $filterDateTo = null;

    #[Url(as: 'axis', except: 'start_date')]
    public ?string $filterDateAxis = 'start_date';

    #[Url(as: 'phase', except: '')]
    public ?string $filterPhase = null;

    /** @var list<string> */
    #[Url(as: 'statuses')]
    public array $filterEventStatuses = [];

    #[Url(as: 'settlement_status', except: '')]
    public ?string $filterStatus = null;

    #[Url(as: 'template', except: '')]
    public ?string $filterTemplateId = null;

    #[Url(as: 'client', except: '')]
    public ?string $filterClient = null;

    #[Url(as: 'mode', except: 'auto')]
    public ?string $filterRecognitionMode = 'auto';

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'sort', except: 'net_result_pln')]
    public string $sortBy = 'net_result_pln';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    public static function canAccess(): bool
    {
        return ExecutiveAccess::canAccessProfitLossPanel();
    }

    public function mount(): void
    {
        if ($this->filterEventStatuses === [] && ! request()->has('statuses')) {
            $this->filterEventStatuses = self::defaultEventStatuses();
        }

        // BC: stary parametr event_status=confirmed
        if ($this->filterEventStatuses === [] && filled(request('event_status'))) {
            $this->filterEventStatuses = [(string) request('event_status')];
        }

        $this->syncFormFromFilters();
    }

    /** @return list<string> */
    public static function defaultEventStatuses(): array
    {
        return array_values(array_diff(
            array_keys(Event::getStatusOptions()),
            [Event::STATUS_CANCELLED, Event::STATUS_PENDING_CANCELLATION],
        ));
    }

    /** @return list<string> */
    public static function pipelineEventStatuses(): array
    {
        return [
            Event::STATUS_OFFER,
            Event::STATUS_PROVISIONAL_RESERVATION,
            Event::STATUS_CONFIRMED,
        ];
    }

    public function presetStatusesWithoutCancelled(): void
    {
        $this->filterEventStatuses = self::defaultEventStatuses();
        $this->syncFormFromFilters();
    }

    public function presetStatusesPipeline(): void
    {
        $this->filterEventStatuses = self::pipelineEventStatuses();
        $this->syncFormFromFilters();
    }

    public function presetStatusesAll(): void
    {
        $this->filterEventStatuses = array_keys(Event::getStatusOptions());
        $this->syncFormFromFilters();
    }

    public function getTitle(): string
    {
        return 'Panel zysków i strat';
    }

    public function getNavigationTabs(): array
    {
        return ExecutiveModuleNavigation::tabs('profit-loss');
    }

    public function sortByColumn(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDir = in_array($column, ['event_name', 'client_name', 'phase', 'event_date'], true)
            ? 'asc'
            : 'desc';
    }

    public function narrowPhase(string $phase): void
    {
        $this->filterPhase = $this->filterPhase === $phase ? null : $phase;
        $this->syncFormFromFilters();
    }

    public function narrowTemplate(?string $templateId): void
    {
        $value = filled($templateId) ? (string) $templateId : 'none';
        $this->filterTemplateId = $this->filterTemplateId === $value ? null : $value;
        $this->syncFormFromFilters();
    }

    public function narrowClient(string $client): void
    {
        if ($client === 'Bez klienta') {
            $this->filterClient = $this->filterClient === '__none__' ? null : '__none__';
        } else {
            $this->filterClient = $this->filterClient === $client ? null : $client;
        }

        $this->syncFormFromFilters();
    }

    public function resetNarrowing(): void
    {
        $this->filterPhase = null;
        $this->filterEventStatuses = self::defaultEventStatuses();
        $this->filterStatus = null;
        $this->filterTemplateId = null;
        $this->filterClient = null;
        $this->filterSearch = null;
        $this->filterDateFrom = null;
        $this->filterDateTo = null;
        $this->filterDateAxis = 'start_date';
        $this->filterRecognitionMode = 'auto';
        $this->sortBy = 'net_result_pln';
        $this->sortDir = 'desc';
        $this->syncFormFromFilters();
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    Section::make('Filtry')
                        ->schema([
                            TextInput::make('filterSearch')
                                ->label('Szukaj')
                                ->placeholder('Kod, nazwa, klient…')
                                ->columnSpan(['default' => 1, 'md' => 2]),
                            DatePicker::make('filterDateFrom')->label('Od')->native(false),
                            DatePicker::make('filterDateTo')->label('Do')->native(false),
                            Select::make('filterPhase')
                                ->label('Faza')
                                ->options(['' => 'Wszystkie', ...EventAnalyticsPhase::options()])
                                ->native(false),
                            Select::make('filterTemplateId')
                                ->label('Szablon')
                                ->options(
                                    fn (): array => [
                                        '' => 'Wszystkie',
                                        'none' => 'Bez szablonu',
                                    ] + EventTemplate::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                                )
                                ->searchable()
                                ->native(false),
                            TextInput::make('filterClient')
                                ->label('Klient')
                                ->placeholder('Fragment nazwy…'),
                            CheckboxList::make('filterEventStatuses')
                                ->label('Statusy imprez')
                                ->options(Event::getStatusOptions())
                                ->columns(['default' => 2, 'md' => 4])
                                ->bulkToggleable()
                                ->columnSpanFull()
                                ->helperText('Domyślnie bez anulowanych. Zaznacz np. Oferta + Potwierdzona jednocześnie.'),
                        ])
                        ->columns(['default' => 1, 'md' => 3, 'xl' => 6]),
                    Section::make('Zaawansowane')
                        ->collapsed()
                        ->schema([
                            Select::make('filterDateAxis')
                                ->label('Oś daty')
                                ->options([
                                    'start_date' => 'Data wyjazdu',
                                    'end_date' => 'Data powrotu',
                                ])
                                ->native(false),
                            Select::make('filterStatus')
                                ->label('Status rozliczenia')
                                ->options(['' => 'Wszystkie', ...EventSettlement::$statuses])
                                ->native(false),
                            Select::make('filterRecognitionMode')
                                ->label('Tryb uznania zysku')
                                ->options(ProfitRecognitionMode::options())
                                ->native(false),
                        ])
                        ->columns(3),
                ])
                ->statePath(''),
        ];
    }

    /** @return array<string, mixed> */
    public function getFilters(): array
    {
        $statuses = array_values(array_filter($this->filterEventStatuses, fn ($s) => filled($s)));

        return [
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
            'date_axis' => $this->filterDateAxis ?: 'start_date',
            'phase' => filled($this->filterPhase) ? $this->filterPhase : null,
            'event_statuses' => $statuses,
            'status' => filled($this->filterStatus) ? $this->filterStatus : null,
            'template_id' => filled($this->filterTemplateId) ? $this->filterTemplateId : null,
            'client' => filled($this->filterClient) ? $this->filterClient : null,
            'recognition_mode' => $this->filterRecognitionMode ?: 'auto',
            'search' => $this->filterSearch,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getRows(): array
    {
        return once(fn (): array => app(ExecutiveProfitLossService::class)
            ->eventRows($this->getFilters())
            ->all());
    }

    /** @return array<int, array<string, mixed>> */
    public function getDisplayRows(): array
    {
        $rows = collect($this->getRows());
        $column = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'net_result_pln';
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        $sorted = $dir === 'asc'
            ? $rows->sortBy(fn (array $row) => $row[$column] ?? null, SORT_NATURAL | SORT_FLAG_CASE)
            : $rows->sortByDesc(fn (array $row) => $row[$column] ?? null, SORT_NATURAL | SORT_FLAG_CASE);

        return $sorted->values()->all();
    }

    /** @return array<string, float|int|null> */
    public function getSummary(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->summarize(collect($this->getRows()));
    }

    /** @return array<int, array<string, mixed>> */
    public function getMonthlyTrend(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->monthlyTrend($this->getFilters());
    }

    /** @return array<int, array<string, mixed>> */
    public function getByTemplate(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->aggregateByTemplate(collect($this->getRows()));
    }

    /** @return array<int, array<string, mixed>> */
    public function getByClient(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->aggregateByClient(collect($this->getRows()));
    }

    /** @return array<int, array<string, mixed>> */
    public function getByPhase(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->aggregateByPhase(collect($this->getRows()));
    }

    public function eventFinanceUrl(int $eventId): string
    {
        return EventResource::getUrl('finance', ['record' => $eventId]);
    }

    public function eventEditUrl(int $eventId): string
    {
        return EventResource::getUrl('edit', ['record' => $eventId]);
    }

    public function eventsIndexUrl(?string $status = null, ?string $search = null, ?string $templateId = null): string
    {
        $params = [];
        if (filled($status)) {
            $params['tableFilters']['status']['value'] = $status;
        }
        if (filled($templateId) && $templateId !== 'none') {
            $params['tableFilters']['event_template_id']['value'] = $templateId;
        }
        if (filled($search)) {
            $params['tableSearch'] = $search;
        }

        return EventResource::getUrl('index', $params);
    }

    public function templateUrl(int $templateId): string
    {
        return EventTemplateResource::getUrl('edit', ['record' => $templateId]);
    }

    public function contractorsUrl(): string
    {
        return ContractorResource::getUrl('index');
    }

    public function sortIndicator(string $column): string
    {
        if ($this->sortBy !== $column) {
            return '';
        }

        return $this->sortDir === 'asc' ? ' ↑' : ' ↓';
    }

    public function hasActiveNarrowing(): bool
    {
        $defaultStatuses = self::defaultEventStatuses();
        $statusesDiffer = collect($this->filterEventStatuses)->sort()->values()->all()
            !== collect($defaultStatuses)->sort()->values()->all();

        return filled($this->filterPhase)
            || $statusesDiffer
            || filled($this->filterStatus)
            || filled($this->filterTemplateId)
            || filled($this->filterClient)
            || filled($this->filterSearch)
            || filled($this->filterDateFrom)
            || filled($this->filterDateTo)
            || ($this->filterRecognitionMode && $this->filterRecognitionMode !== 'auto')
            || ($this->filterDateAxis && $this->filterDateAxis !== 'start_date');
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->getDisplayRows();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Impreza',
                'Kod',
                'Klient',
                'Szablon',
                'Wyjazd',
                'Faza',
                'Tryb uznania',
                'Status imprezy',
                'Status rozliczenia',
                'Przychód uznany',
                'Przychód plan (due)',
                'Przychód zapłacony',
                'Koszt uznany',
                'Koszt plan',
                'Koszt zapłacony',
                'Koszt outstanding',
                'Zysk uznany',
                'Marża %',
                'Należności',
                'Zobowiązania',
                'Fallback oferty',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['event_name'] ?? '',
                    $row['event_code'] ?? '',
                    $row['client_name'] ?? '',
                    $row['template_name'] ?? '',
                    $row['event_date'] ?? '',
                    $row['phase_label'] ?? '',
                    $row['recognition_mode_label'] ?? '',
                    $row['event_status_label'] ?? '',
                    $row['status_label'] ?? '',
                    $row['revenue_pln'] ?? 0,
                    $row['revenue_due_pln'] ?? 0,
                    $row['revenue_paid_pln'] ?? 0,
                    $row['costs_pln'] ?? 0,
                    $row['cost_planned_pln'] ?? 0,
                    $row['cost_paid_pln'] ?? 0,
                    $row['cost_outstanding_pln'] ?? 0,
                    $row['net_result_pln'] ?? 0,
                    $row['margin_recognized_percent'] ?? '',
                    $row['receivables_pln'] ?? 0,
                    $row['payables_pln'] ?? 0,
                    ! empty($row['from_offer_fallback']) ? 'tak' : 'nie',
                ]);
            }

            fclose($out);
        }, 'panel-zyskow-strat-'.now()->format('Ymd-His').'.csv');
    }

    private function syncFormFromFilters(): void
    {
        $this->form->fill([
            'filterDateFrom' => $this->filterDateFrom,
            'filterDateTo' => $this->filterDateTo,
            'filterDateAxis' => $this->filterDateAxis ?: 'start_date',
            'filterPhase' => $this->filterPhase,
            'filterEventStatuses' => $this->filterEventStatuses,
            'filterStatus' => $this->filterStatus,
            'filterTemplateId' => $this->filterTemplateId,
            'filterClient' => $this->filterClient,
            'filterRecognitionMode' => $this->filterRecognitionMode ?: 'auto',
            'filterSearch' => $this->filterSearch,
        ]);
    }
}

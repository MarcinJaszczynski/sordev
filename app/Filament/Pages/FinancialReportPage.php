<?php

namespace App\Filament\Pages;

use App\Models\Contractor;
use App\Models\Event;
use App\Services\FinancialReportService;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.pages.financial-report';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Raport finansowy';

    protected static ?int $navigationSort = 9;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public ?string $filterType = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public ?int $filterEventId = null;

    public ?int $filterContractorId = null;

    public ?string $filterSearch = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || $user->can('view_any_event::settlement'));
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Główny raport finansowy';
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('financial-report');
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    Select::make('filterType')
                        ->label('Typ operacji')
                        ->options([
                            '' => 'Wszystkie',
                            'participant_payment' => 'Wpłata uczestnika',
                            'vendor_invoice' => 'Faktura kosztowa',
                            'settlement_cost' => 'Koszt rozliczenia',
                        ]),
                    DatePicker::make('filterDateFrom')->label('Od')->native(false),
                    DatePicker::make('filterDateTo')->label('Do')->native(false),
                    Select::make('filterEventId')
                        ->label('Impreza')
                        ->searchable()
                        ->options(fn (): array => Event::query()->orderByDesc('start_date')->limit(300)->pluck('name', 'id')->all())
                        ->nullable(),
                    Select::make('filterContractorId')
                        ->label('Kontrahent')
                        ->searchable()
                        ->options(fn (): array => Contractor::query()->orderBy('name')->limit(300)->pluck('name', 'id')->all())
                        ->nullable(),
                    TextInput::make('filterSearch')
                        ->label('Szukaj')
                        ->placeholder('Nazwa, numer, NIP, referencja…'),
                ])
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->statePath(''),
        ];
    }

    /** @return array<string, mixed> */
    public function getReportFilters(): array
    {
        return [
            'type' => filled($this->filterType) ? $this->filterType : null,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
            'event_id' => $this->filterEventId,
            'contractor_id' => $this->filterContractorId,
            'search' => $this->filterSearch,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getReportRows(): array
    {
        return app(FinancialReportService::class)
            ->rows($this->getReportFilters())
            ->all();
    }

    /** @return array<string, mixed> */
    public function getReportSummary(): array
    {
        $rows = collect($this->getReportRows());

        return app(FinancialReportService::class)->summarize($rows);
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->getReportRows();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Typ', 'Etykieta', 'Kontrahent', 'Impreza', 'Kwota PLN', 'Kierunek', 'Status', 'Data']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['operation_label'] ?? '',
                    $row['label'] ?? '',
                    $row['counterparty'] ?? '',
                    $row['event_code'] ?? '',
                    $row['amount_pln'] ?? 0,
                    $row['direction'] ?? '',
                    $row['status'] ?? '',
                    $row['operation_date'] ?? '',
                ]);
            }

            fclose($out);
        }, 'raport-finansowy-'.now()->format('Ymd-His').'.csv');
    }
}

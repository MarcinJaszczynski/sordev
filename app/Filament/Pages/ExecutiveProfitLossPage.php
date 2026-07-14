<?php

namespace App\Filament\Pages;

use App\Models\EventSettlement;
use App\Services\ExecutiveProfitLossService;
use App\Support\ExecutiveAccess;
use App\Support\ExecutiveModuleNavigation;
use App\Support\FilamentNavigation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutiveProfitLossPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.executive-profit-loss';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EXECUTIVE;

    protected static ?string $navigationLabel = 'Zyski i straty';

    protected static ?int $navigationSort = 1;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public ?string $filterStatus = null;

    public ?string $filterSearch = null;

    public static function canAccess(): bool
    {
        return ExecutiveAccess::canAccessProfitLossPanel();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Panel zysków i strat';
    }

    public function getNavigationTabs(): array
    {
        return ExecutiveModuleNavigation::tabs('profit-loss');
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    DatePicker::make('filterDateFrom')->label('Wyjazd od')->native(false),
                    DatePicker::make('filterDateTo')->label('Wyjazd do')->native(false),
                    Select::make('filterStatus')
                        ->label('Status rozliczenia')
                        ->options(['' => 'Wszystkie', ...EventSettlement::$statuses]),
                    TextInput::make('filterSearch')->label('Szukaj imprezy')->placeholder('Kod lub nazwa…'),
                ])
                ->columns(4)
                ->statePath(''),
        ];
    }

    /** @return array<string, mixed> */
    public function getFilters(): array
    {
        return [
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
            'status' => filled($this->filterStatus) ? $this->filterStatus : null,
            'search' => $this->filterSearch,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getRows(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->eventRows($this->getFilters())
            ->all();
    }

    /** @return array<string, float|int> */
    public function getSummary(): array
    {
        return app(ExecutiveProfitLossService::class)
            ->summarize(collect($this->getRows()));
    }

    /** @return array<int, array<string, mixed>> */
    public function getMonthlyTrend(): array
    {
        return app(ExecutiveProfitLossService::class)->monthlyTrend();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->getRows();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Impreza', 'Kod', 'Data', 'Status', 'Przychód', 'Koszty', 'Wynik netto', 'Należności', 'Zobowiązania']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['event_name'] ?? '',
                    $row['event_code'] ?? '',
                    $row['event_date'] ?? '',
                    $row['status_label'] ?? '',
                    $row['revenue_pln'] ?? 0,
                    $row['costs_pln'] ?? 0,
                    $row['net_result_pln'] ?? 0,
                    $row['receivables_pln'] ?? 0,
                    $row['payables_pln'] ?? 0,
                ]);
            }

            fclose($out);
        }, 'panel-zyskow-strat-'.now()->format('Ymd-His').'.csv');
    }
}

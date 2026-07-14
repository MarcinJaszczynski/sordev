<?php

namespace App\Filament\Pages;

use App\Models\Contract;
use App\Models\Event;
use App\Services\Tfg\TfgCsvExportService;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TfgCsvExportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.pages.tfg-csv-export';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Eksport TFG (CSV)';

    protected static ?string $title = 'Eksport TFG - Wykaz umów (CSV)';

    protected static ?int $navigationSort = 11;

    public string $operation = Contract::OP_NOWEDANE;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $eventId = null;

    public bool $onlyNotSynced = false;

    public bool $previewed = false;

    public int $previewContracts = 0;

    public int $previewRows = 0;

    /** @var array<int, array{number: string, errors: array<int, string>}> */
    public array $previewErrors = [];

    /** @var array<int, string> */
    public array $fileErrors = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || $user->can('view_any_contract'));
    }

    /**
     * @return array<string, string>
     */
    public function operationOptions(): array
    {
        return [
            Contract::OP_NOWEDANE => 'Nowe dane',
            Contract::OP_KOREKTA => 'Korekta',
            Contract::OP_ROZWIAZANIE => 'Rozwiązanie',
            Contract::OP_USUNIECIE => 'Usunięcie',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function eventOptions(): array
    {
        return Event::query()
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('name', 'id')
            ->all();
    }

    public function preview(): void
    {
        $contracts = $this->resolveContracts();
        $service = app(TfgCsvExportService::class);

        $errors = $service->validate($contracts, $this->operation);

        $this->fileErrors = $errors['_file'] ?? [];
        unset($errors['_file']);

        $numbersById = $contracts->pluck('contract_number', 'id');

        $this->previewErrors = collect($errors)
            ->map(fn (array $messages, int $id) => [
                'number' => (string) ($numbersById->get($id) ?: ('#'.$id)),
                'errors' => $messages,
            ])
            ->values()
            ->all();

        $export = app(\App\Services\Tfg\TfgWykazCsvExporter::class)->export($contracts, $this->operation);

        $this->previewContracts = $contracts->count();
        $this->previewRows = $export['rows_count'];
        $this->previewed = true;

        if ($contracts->isEmpty()) {
            Notification::make()->title('Brak umów spełniających kryteria')->warning()->send();
        }
    }

    public function download(): ?StreamedResponse
    {
        $contracts = $this->resolveContracts();

        if ($contracts->isEmpty()) {
            Notification::make()->title('Brak umów do eksportu')->warning()->send();

            return null;
        }

        $service = app(TfgCsvExportService::class);

        try {
            $result = $service->generate($contracts, $this->operation);
        } catch (\App\Services\Tfg\Exceptions\TfgValidationException $exception) {
            $this->preview();

            Notification::make()
                ->title('Eksport zablokowany - popraw błędy walidacji')
                ->body('Znaleziono błędy w '.count($this->previewErrors).' umowach.')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('Wygenerowano plik CSV')
            ->body($result['filename'].' • umów: '.$result['contracts_count'].' • wierszy: '.$result['rows_count'])
            ->success()
            ->send();

        $content = $result['content'];
        $filename = $result['filename'];

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveContracts(): Collection
    {
        $query = app(TfgCsvExportService::class)->eligibleQuery($this->operation);

        if (filled($this->dateFrom)) {
            $query->whereDate('contract_date', '>=', $this->dateFrom);
        }

        if (filled($this->dateTo)) {
            $query->whereDate('contract_date', '<=', $this->dateTo);
        }

        if (filled($this->eventId)) {
            $query->where('event_id', $this->eventId);
        }

        if ($this->onlyNotSynced) {
            $query->whereNull('tfg_status');
        }

        return $query->orderBy('contract_date')->orderBy('id')->get();
    }
}

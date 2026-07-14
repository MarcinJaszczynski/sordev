<?php

namespace App\Livewire;

use App\Models\BankPaymentImportBatch;
use App\Models\BankPaymentImportLine;
use App\Services\BankPayments\BankPaymentImportEventScope;
use App\Services\BankPayments\BankPaymentImportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EventBankPaymentImportPanel extends Component
{
    use WithFileUploads;

    public int $eventId;

    /** @var TemporaryUploadedFile|null */
    public $csvFile = null;

    public ?int $batchId = null;

    /** @var array<int, array<string, mixed>> */
    public array $previewLines = [];

    public ?array $lastImportSummary = null;

    public ?array $lastApplySummary = null;

    public int $hiddenOtherEventsCount = 0;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        return view('livewire.event-bank-payment-import-panel');
    }

    public function runImport(): void
    {
        $this->validate([
            'csvFile' => 'required|file|max:10240',
        ], [
            'csvFile.required' => 'Wybierz plik CSV z Banku Millennium.',
            'csvFile.max' => 'Plik CSV jest za duży (max 10 MB).',
        ]);

        if (! Schema::hasTable('bank_payment_import_batches')) {
            Notification::make()
                ->title('Brak tabel importu')
                ->body('Uruchom migracje bazy danych (php artisan migrate).')
                ->danger()
                ->send();

            return;
        }

        $path = $this->resolveTempPath($this->csvFile);

        if (! $path) {
            Notification::make()
                ->title('Nie udało się odczytać pliku')
                ->danger()
                ->send();

            return;
        }

        try {
            $summary = app(BankPaymentImportService::class)->createPreviewFromCsv(
                file_get_contents($path),
                $this->csvFile?->getClientOriginalName(),
                auth()->id(),
            );

            $this->batchId = $summary['batch_id'];
            $this->lastImportSummary = $summary;
            $this->lastApplySummary = null;
            $this->loadPreviewLines();

            Notification::make()
                ->title('Wczytano wyciąg')
                ->body(sprintf(
                    'Wpływy dla tej imprezy: %d • ukryte (inne imprezy): %d',
                    count($this->previewLines),
                    $this->hiddenOtherEventsCount,
                ))
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Błąd importu CSV')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleLineSelection(int $lineId): void
    {
        $line = $this->findOwnedLine($lineId);

        if (! $line || $line->applied) {
            return;
        }

        $line->update(['selected' => ! $line->selected]);
        $this->loadPreviewLines();
    }

    public function applySelected(): void
    {
        if (! $this->batchId) {
            return;
        }

        $batch = BankPaymentImportBatch::query()->find($this->batchId);

        if (! $batch) {
            return;
        }

        $lineIds = collect($this->previewLines)
            ->filter(fn (array $line) => ! empty($line['selected']) && empty($line['applied']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($lineIds === []) {
            Notification::make()
                ->title('Brak zaznaczonych wpłat')
                ->warning()
                ->send();

            return;
        }

        BankPaymentImportLine::query()
            ->where('batch_id', $batch->id)
            ->whereIn('id', $lineIds)
            ->update(['selected' => true]);

        $result = app(BankPaymentImportService::class)->applyLines($batch, $lineIds);
        $this->lastApplySummary = $result;
        $this->loadPreviewLines();

        $this->dispatch('settlement-data-changed');

        Notification::make()
            ->title('Zaksięgowano wpłaty')
            ->body(sprintf('Zastosowano: %d • pominięto: %d', $result['applied'], $result['skipped']))
            ->success()
            ->send();
    }

    public function loadPreviewLines(): void
    {
        if (! $this->batchId) {
            $this->previewLines = [];
            $this->hiddenOtherEventsCount = 0;

            return;
        }

        $lines = BankPaymentImportLine::query()
            ->with(['contract.event', 'participantPayment.settlement.event', 'event'])
            ->where('batch_id', $this->batchId)
            ->orderBy('operation_date')
            ->orderBy('id')
            ->get();

        $this->hiddenOtherEventsCount = $lines
            ->reject(fn (BankPaymentImportLine $line): bool => BankPaymentImportEventScope::lineBelongsToEvent($line, $this->eventId))
            ->count();

        $this->previewLines = $lines
            ->filter(fn (BankPaymentImportLine $line): bool => BankPaymentImportEventScope::lineBelongsToEvent($line, $this->eventId))
            ->map(fn (BankPaymentImportLine $line): array => [
                'id' => $line->id,
                'operation_date' => $line->operation_date?->format('d.m.Y') ?? '—',
                'title' => $line->title,
                'counterparty' => $line->counterparty,
                'amount_pln' => number_format((float) $line->amount_pln, 2, ',', ' '),
                'match_status' => $line->match_status,
                'match_reason' => $line->match_reason,
                'target_label' => $this->resolveTargetLabel($line),
                'selected' => (bool) $line->selected,
                'applied' => (bool) $line->applied,
            ])
            ->values()
            ->all();
    }

    private function resolveTargetLabel(BankPaymentImportLine $line): string
    {
        if ($line->match_status === 'matched_pilot_advance') {
            $event = $line->event?->code ?? ($line->event_id ? '#'.$line->event_id : null);

            return trim('Zaliczka pilota'.($event ? ' • '.$event : ''));
        }

        if ($line->contract) {
            $event = $line->contract->event?->code;

            return trim('Umowa '.$line->contract->contract_number.($event ? ' • '.$event : ''));
        }

        if ($line->participantPayment) {
            $event = $line->participantPayment->settlement?->event?->code;

            return trim($line->participantPayment->participant_name.($event ? ' • '.$event : ''));
        }

        return '—';
    }

    private function findOwnedLine(int $lineId): ?BankPaymentImportLine
    {
        if (! $this->batchId) {
            return null;
        }

        $line = BankPaymentImportLine::query()
            ->with(['contract', 'participantPayment.settlement', 'event'])
            ->where('batch_id', $this->batchId)
            ->whereKey($lineId)
            ->first();

        if (! $line || ! BankPaymentImportEventScope::lineBelongsToEvent($line, $this->eventId)) {
            return null;
        }

        return $line;
    }

    private function resolveTempPath(?TemporaryUploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $path = $file->getRealPath();

        return ($path && is_file($path)) ? $path : null;
    }
}

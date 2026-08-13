<?php

namespace App\Livewire;

use App\Livewire\Concerns\HandlesPilotExpenseLedger;
use App\Models\Currency;
use App\Models\Event;
use App\Services\PilotAdvanceService;
use App\Services\PilotSettlementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PilotTripSettlementForm extends Component
{
    use HandlesPilotExpenseLedger;
    use WithFileUploads;

    public Event $event;

    public string $pilot_report_notes = '';

    public ?int $reported_participant_count = null;

    public ?int $odometer_start = null;

    public ?int $odometer_end = null;

    public ?int $documentCurrencyId = null;

    public string $documentVendor = '';

    public string $documentAmount = '';

    public string $documentType = 'invoice';

    public string $documentNumber = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $documentFiles = [];

    public bool $showTripHeader = true;

    public bool $readOnly = false;

    public function mount(Event $event, bool $showTripHeader = true, bool $readOnly = false): void
    {
        abort_unless(Auth::user()?->can('viewPilotDetails', $event), 403);

        $this->event = $event;
        $this->showTripHeader = $showTripHeader;
        $this->readOnly = $readOnly || app(\App\Services\PilotAccessService::class)->isPreviewReadOnly();

        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($event);

        $this->pilot_report_notes = (string) ($settlement->pilot_report_notes ?? '');
        $this->reported_participant_count = $settlement->reported_participant_count;
        $this->odometer_start = $settlement->odometer_start;
        $this->odometer_end = $settlement->odometer_end;
        $this->documentCurrencyId = Currency::query()->where('code', 'PLN')->value('id')
            ?? Currency::defaultPlnId();
        $this->mountExpenseLedgerState();
    }

    public function getSettlementProperty(): \App\Models\EventSettlement
    {
        return app(PilotSettlementService::class)->getOrCreateSettlement($this->event);
    }

    public function getEditableProperty(): bool
    {
        if ($this->readOnly) {
            return false;
        }

        return $this->settlement->isEditableByPilot();
    }

    public function getShowBusCollectionsProperty(): bool
    {
        return $this->event->showsPilotBusCollections();
    }

    public function getPaidAdvanceLinesProperty()
    {
        return app(PilotAdvanceService::class)->paidLines($this->event);
    }

    public function getPlannedAdvanceLinesProperty()
    {
        return app(PilotAdvanceService::class)->plannedLines($this->event);
    }

    public function save(bool $submitToOffice = false): void
    {
        $this->validate([
            'pilot_report_notes' => 'nullable|string',
            'reported_participant_count' => 'nullable|integer|min:0',
            'odometer_start' => 'nullable|integer|min:0',
            'odometer_end' => 'nullable|integer|min:0',
        ]);

        app(PilotSettlementService::class)->saveReport($this->event, [
            'pilot_report_notes' => $this->pilot_report_notes,
            'reported_participant_count' => $this->reported_participant_count,
            'odometer_start' => $this->odometer_start,
            'odometer_end' => $this->odometer_end,
            'submit_to_office' => $submitToOffice,
        ]);

        session()->flash('status', $submitToOffice ? 'Rozliczenie zgłoszone do biura.' : 'Zapisano.');
    }

    public function updatedDocumentFiles(): void
    {
        if ($this->documentFiles !== []) {
            $this->uploadDocument();
        }
    }

    public function uploadDocument(): void
    {
        $this->validate([
            'documentFiles' => 'required|array|min:1',
            'documentFiles.*' => 'file|max:10240',
            'documentType' => 'required|in:'.implode(',', array_keys(\App\Models\EventSettlementDocument::$documentTypes)),
            'documentNumber' => 'nullable|string|max:255',
        ]);

        $uploadedFiles = $this->normalizeUploadedFiles($this->documentFiles);

        app(PilotSettlementService::class)->uploadDocument(
            $this->event,
            [
                'vendor_name' => $this->documentVendor ?: null,
                'total_amount' => $this->documentAmount !== '' ? $this->documentAmount : null,
                'currency_id' => $this->documentCurrencyId,
                'document_type' => $this->documentType,
                'document_number' => $this->documentNumber !== '' ? $this->documentNumber : null,
            ],
            $uploadedFiles,
        );

        $this->reset(['documentVendor', 'documentAmount', 'documentNumber', 'documentFiles']);
        $this->documentType = 'invoice';

        session()->flash('status', 'Dokument dodany.');
    }

    public function render()
    {
        return view('livewire.pilot-trip-settlement-form', [
            'settlement' => $this->settlement,
            'documents' => $this->settlement->documents()->latest('id')->get(),
        ])->layout('pilot.layouts.mobile', [
            'title' => 'Rozliczenie: '.$this->event->name,
        ]);
    }
}

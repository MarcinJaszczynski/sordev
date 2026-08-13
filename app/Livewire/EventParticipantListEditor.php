<?php

namespace App\Livewire;

use App\Actions\Events\UpsertEventParticipantAction;
use App\Data\UpsertEventParticipantData;
use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\EventMessageLog;
use App\Models\EventParticipant;
use App\Services\EventBulkMessageService;
use App\Services\EventParticipantImporter;
use App\Services\EventParticipantPropagationService;
use App\Services\EventParticipantVerificationService;
use App\Services\ParentParticipantAccessService;
use App\Filament\Concerns\InteractsWithClientInvoiceRequestActions;
use App\Support\EventParticipantConsents;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EventParticipantListEditor extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithClientInvoiceRequestActions;
    use InteractsWithForms;
    use WithFileUploads;

    public int $eventId;

    public string $activeTab = 'list';

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    public string $importMode = 'append';

    /** @var array<int, array<string, mixed>> */
    public array $participants = [];

    /** @var array<string, mixed> */
    public array $verificationSummary = [];

    /** @var array<int, array<string, mixed>> */
    public array $verificationRows = [];

    public ?string $editParticipantId = null;

    public string $formFirstName = '';

    public string $formLastName = '';

    public ?string $formBirthDate = null;

    public string $formPesel = '';

    public string $formEmail = '';

    public string $formPhone = '';

    public string $formBookingReference = '';

    public string $formDiet = '';

    public bool $formParentConsent = false;

    /** @var array<string, bool> */
    public array $formConsents = [];

    /** @var array<string, mixed>|null */
    public ?array $detailPanel = null;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->formConsents = array_fill_keys(EventParticipantConsents::allKeys(), false);
        $this->loadParticipants();
    }

    public function loadParticipants(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->participants = [];

            return;
        }

        $this->participants = EventParticipant::query()
            ->where('event_id', $this->eventId)
            ->with(['participantPayment', 'contract'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (EventParticipant $participant) => [
                'id' => $participant->id,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'full_name' => $participant->fullName(),
                'birth_date' => $participant->birth_date?->format('d.m.Y'),
                'pesel' => $participant->pesel,
                'email' => $participant->email,
                'phone' => $participant->phone,
                'booking_reference' => $participant->booking_reference,
                'diet' => $participant->diet,
                'parent_consent' => $participant->hasParentConsent(),
                'consents_label' => $participant->consentsCompletedLabel(),
                'consents' => $participant->consentChecklist(),
                'payment_id' => $participant->participant_payment_id,
                'paid_amount_pln' => $participant->participantPayment
                    ? (float) $participant->participantPayment->paid_amount_pln
                    : null,
                'due_amount_pln' => $participant->participantPayment
                    ? (float) $participant->participantPayment->due_amount_pln
                    : null,
                'contract_id' => $participant->contract_id,
                'contract_number' => $participant->contract?->contract_number
                    ?? $participant->contract?->operational_number,
                'source' => EventParticipant::$sources[$participant->source] ?? $participant->source,
                'status' => EventParticipant::$statuses[$participant->status] ?? $participant->status,
            ])
            ->all();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'verification') {
            $this->runVerification();
        }
    }

    public function runVerification(): void
    {
        $event = Event::findOrFail($this->eventId);
        $result = app(EventParticipantVerificationService::class)->verify($event);
        $this->verificationSummary = $result['summary'];
        $this->verificationRows = $result['rows'];
    }

    public function importParticipants(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'importMode' => ['in:append,replace'],
        ]);

        $path = $this->importFile?->getRealPath();
        if (! $path || ! is_file($path)) {
            Notification::make()->title('Nie udało się odczytać pliku')->danger()->send();

            return;
        }

        $event = Event::findOrFail($this->eventId);

        try {
            $result = app(EventParticipantImporter::class)->importFromPath($event, $path, $this->importMode);
            app(EventParticipantPropagationService::class)->propagateToPayments($event);
            $this->importFile = null;
            $this->loadParticipants();
            $this->activeTab = 'list';

            $body = "Zaimportowano {$result['imported']} osób.";
            if ($result['linked'] > 0) {
                $body .= " Powiązano z umowami: {$result['linked']}.";
            }
            if ($result['skipped'] > 0) {
                $body .= " Pominięto {$result['skipped']} wierszy.";
            }
            $body .= ' Widoczni też na liście wpłat.';

            Notification::make()->title('Import zakończony')->body($body)->success()->send();

            if ($result['warnings'] !== []) {
                Notification::make()
                    ->title('Uwagi z importu')
                    ->body(implode("\n", array_slice($result['warnings'], 0, 5)))
                    ->warning()
                    ->send();
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Import nieudany')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncFromAgreements(): void
    {
        $event = Event::findOrFail($this->eventId);
        $result = app(EventParticipantPropagationService::class)->syncFromAgreements($event);
        $this->loadParticipants();

        Notification::make()
            ->title('Synchronizacja z umowami')
            ->body("Dodano {$result['imported']} osób, pominięto {$result['skipped']} duplikatów.")
            ->success()
            ->send();
    }

    public function propagateToPayments(): void
    {
        $event = Event::findOrFail($this->eventId);
        $result = app(EventParticipantPropagationService::class)->propagateToPayments($event);
        $this->loadParticipants();

        Notification::make()
            ->title('Wpłaty uczestników')
            ->body("Utworzono {$result['created']}, powiązano {$result['linked']}, pominięto {$result['skipped']}.")
            ->success()
            ->send();
    }

    public function propagateToHotel(): void
    {
        $event = Event::findOrFail($this->eventId);
        $result = app(EventParticipantPropagationService::class)->propagateToHotelPlan($event);
        $this->loadParticipants();

        $body = "Przypisano {$result['assigned']} osób, pominięto {$result['skipped']}.";
        if ($result['warnings'] !== []) {
            $body .= ' '.implode(' ', array_slice($result['warnings'], 0, 2));
        }

        Notification::make()
            ->title('Plan noclegów')
            ->body($body)
            ->success()
            ->send();
    }

    public function sendBulkNotice(string $segment): void
    {
        if (! in_array($segment, [
            EventMessageLog::SEGMENT_ALL,
            EventMessageLog::SEGMENT_OVERDUE,
            EventMessageLog::SEGMENT_MISSING_CONSENTS,
        ], true)) {
            Notification::make()->title('Nieznany segment')->danger()->send();

            return;
        }

        $event = Event::findOrFail($this->eventId);
        $result = app(EventBulkMessageService::class)->send($event, $segment);

        Notification::make()
            ->title('Wysłano komunikaty')
            ->body("Adresaci: {$result['recipients']}, wysłano: {$result['sent']}, błędy: {$result['failed']}.")
            ->success()
            ->send();
    }

    public function startEdit(int $participantId): void
    {
        $participant = EventParticipant::query()
            ->with(['participantPayment', 'contract'])
            ->where('event_id', $this->eventId)
            ->findOrFail($participantId);

        $this->editParticipantId = (string) $participant->id;
        $this->formFirstName = (string) ($participant->first_name ?? '');
        $this->formLastName = (string) ($participant->last_name ?? '');
        $this->formBirthDate = $participant->birth_date?->format('Y-m-d');
        $this->formPesel = (string) ($participant->pesel ?? '');
        $this->formEmail = (string) ($participant->email ?? '');
        $this->formPhone = (string) ($participant->phone ?? '');
        $this->formBookingReference = (string) ($participant->booking_reference ?? '');
        $this->formDiet = (string) ($participant->diet ?? '');
        $this->formConsents = $participant->consentChecklist();
        $this->formParentConsent = $participant->hasParentConsent();
        $this->detailPanel = [
            'payment_id' => $participant->participant_payment_id,
            'paid' => $participant->participantPayment
                ? (float) $participant->participantPayment->paid_amount_pln
                : null,
            'due' => $participant->participantPayment
                ? (float) $participant->participantPayment->due_amount_pln
                : null,
            'payments_url' => EventResource::getUrl('finance-participant-payments', ['record' => $this->eventId]),
            'contract_id' => $participant->contract_id,
            'contract_number' => $participant->contract?->contract_number
                ?? $participant->contract?->operational_number,
            'contracts_url' => EventResource::getUrl('contracts', ['record' => $this->eventId]),
            'consents_label' => $participant->consentsCompletedLabel(),
        ];
    }

    public function copyParentLink(int $participantId): void
    {
        if (! Schema::hasColumn('event_participants', 'parent_access_token')) {
            Notification::make()
                ->title('Brak migracji tokenów rodzica')
                ->danger()
                ->send();

            return;
        }

        $participant = EventParticipant::query()
            ->where('event_id', $this->eventId)
            ->findOrFail($participantId);

        $url = app(ParentParticipantAccessService::class)->urlFor($participant);

        $this->dispatch('copy-to-clipboard', url: $url);

        Notification::make()
            ->title('Link dla rodzica')
            ->body($url)
            ->success()
            ->send();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function saveParticipant(): void
    {
        $this->validate([
            'formFirstName' => ['required_without:formLastName', 'nullable', 'string', 'max:120'],
            'formLastName' => ['nullable', 'string', 'max:120'],
            'formBirthDate' => ['nullable', 'date'],
            'formPesel' => ['nullable', 'string', 'max:11'],
            'formEmail' => ['nullable', 'email', 'max:255'],
            'formPhone' => ['nullable', 'string', 'max:50'],
            'formBookingReference' => ['nullable', 'string', 'max:120'],
            'formDiet' => ['nullable', 'string', 'max:255'],
            'formParentConsent' => ['boolean'],
            'formConsents' => ['array'],
        ]);

        $event = Event::findOrFail($this->eventId);
        $existing = $this->editParticipantId
            ? EventParticipant::query()
                ->where('event_id', $this->eventId)
                ->findOrFail((int) $this->editParticipantId)
            : null;

        app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $event,
            participant: $existing,
            firstName: $this->formFirstName,
            lastName: $this->formLastName,
            birthDate: $this->formBirthDate,
            pesel: $this->formPesel,
            email: $this->formEmail,
            phone: $this->formPhone,
            bookingReference: $this->formBookingReference,
            diet: $this->formDiet,
            parentConsent: EventParticipantConsents::hasRequired($this->formConsents) || $this->formParentConsent,
            consentFlags: $this->formConsents,
            ensurePayment: true,
        ));

        Notification::make()
            ->title($existing ? 'Zapisano zmiany' : 'Dodano uczestnika')
            ->body($existing ? null : 'Uczestnik jest też widoczny na liście wpłat (bez pierwszej raty).')
            ->success()
            ->send();

        $this->resetForm();
        $this->loadParticipants();
    }

    public function deleteParticipant(int $participantId): void
    {
        EventParticipant::query()
            ->where('event_id', $this->eventId)
            ->findOrFail($participantId)
            ->delete();

        $this->loadParticipants();
        Notification::make()->title('Usunięto uczestnika')->success()->send();
    }

    public function getEventProperty(): Event
    {
        return Event::findOrFail($this->eventId);
    }

    public function getTemplateUrlProperty(): string
    {
        return route('admin.events.participants.import-template', ['event' => $this->eventId, 'format' => 'csv']);
    }

    public function getExportUrlProperty(): string
    {
        return route('admin.events.participants.insurance-export', ['event' => $this->eventId, 'format' => 'xlsx']);
    }

    private function resetForm(): void
    {
        $this->editParticipantId = null;
        $this->formFirstName = '';
        $this->formLastName = '';
        $this->formBirthDate = null;
        $this->formPesel = '';
        $this->formEmail = '';
        $this->formPhone = '';
        $this->formBookingReference = '';
        $this->formDiet = '';
        $this->formParentConsent = false;
        $this->formConsents = array_fill_keys(EventParticipantConsents::allKeys(), false);
        $this->detailPanel = null;
    }

    /**
     * @return array<string, string>
     */
    public function getConsentLabelsProperty(): array
    {
        return EventParticipantConsents::labels();
    }

    public function render()
    {
        return view('livewire.event-participant-list-editor');
    }
}

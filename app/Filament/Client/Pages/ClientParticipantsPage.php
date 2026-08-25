<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Actions\Events\UpsertEventParticipantAction;
use App\Data\UpsertEventParticipantData;
use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Services\ClientAccessService;
use App\Services\ParentParticipantAccessService;
use App\Support\EventParticipantConsents;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientParticipantsPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-participants-page';

    protected static ?string $slug = 'participants/{event}';

    public Event $event;

    public string $formFirstName = '';

    public string $formLastName = '';

    public string $formDiet = '';

    /** @var array<string, bool> */
    public array $formConsents = [];

    public ?int $editId = null;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);
        abort_unless(app(ClientAccessService::class)->isGuardian(Auth::user(), $event), 403);
        abort_unless(Schema::hasTable('event_participants'), 404);

        $this->event = $event;
        $this->resetConsentForm();
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'participants';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * @return array{enabled: bool, daily_pln: float, days: int, per_person_pln: float, options: list<string>, base_pln: float, surcharge_pln: float}
     */
    public function getDietCatalogProperty(): array
    {
        $contract = app(ClientAccessService::class)
            ->accessibleContract(Auth::user(), $this->event, \App\Models\EventPortalAccess::ROLE_GUARDIAN);

        if (! $contract) {
            return [
                'enabled' => false,
                'daily_pln' => 0.0,
                'days' => 1,
                'per_person_pln' => 0.0,
                'options' => [],
                'base_pln' => 0.0,
                'surcharge_pln' => 0.0,
            ];
        }

        return app(\App\Services\ContractDietSurchargeService::class)->presentation($contract);
    }

    /** @return array<string, string> */
    public function dietSelectOptions(): array
    {
        $catalog = $this->dietCatalog;
        $options = ['' => 'Bez diety specjalnej'];
        foreach ($catalog['options'] as $label) {
            $options[$label] = $label.(
                $catalog['enabled'] && $catalog['per_person_pln'] > 0
                    ? ' (+'.number_format($catalog['per_person_pln'], 2, ',', ' ').' PLN)'
                    : ''
            );
        }

        return $options;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    /** @return array<int, EventParticipant> */
    public function getParticipantsProperty(): array
    {
        return app(ClientAccessService::class)
            ->guardianParticipantsQuery(Auth::user(), $this->event)
            ->with([
                'participantPayment',
                'contract',
            ])
            ->get()
            ->all();
    }

    /**
     * @return array{total: int, with_name: int, with_consent: int, overdue: int, completeness_pct: int, overdue_total_pln: float}
     */
    public function getCompletenessProperty(): array
    {
        $participants = collect($this->participants);
        $total = $participants->count();
        $withName = $participants->filter(fn (EventParticipant $p) => $p->fullName() !== '')->count();
        $withConsent = $participants->filter(fn (EventParticipant $p) => $p->hasParentConsent())->count();
        $overdue = 0;
        $overdueTotal = 0.0;
        $balanceService = app(\App\Services\ParticipantPaymentBalanceService::class);

        foreach ($participants as $participant) {
            $balance = $balanceService->forParticipant($participant);
            if (($balance['coverage_status'] ?? null) === \App\Services\SettlementPaymentHealthService::STATUS_OVERDUE) {
                $overdue++;
                $overdueTotal += (float) ($balance['remaining_pln'] ?? 0);
            }
        }

        $scoreParts = $total > 0
            ? (($withName / $total) + ($withConsent / $total)) / 2
            : 0;

        return [
            'total' => $total,
            'with_name' => $withName,
            'with_consent' => $withConsent,
            'overdue' => $overdue,
            'completeness_pct' => (int) round($scoreParts * 100),
            'overdue_total_pln' => round($overdueTotal, 2),
        ];
    }

    /**
     * @return array{
     *     payment_label: string,
     *     payment_tone: string,
     *     contract_label: string,
     *     contract_tone: string,
     *     due_pln: float,
     *     paid_pln: float,
     *     remaining_pln: float
     * }
     */
    public function statusFor(EventParticipant $participant): array
    {
        $balance = app(\App\Services\ParticipantPaymentBalanceService::class)->forParticipant($participant);
        if (($balance['source'] ?? null) === 'none') {
            $paymentLabel = 'Brak wpłat';
            $paymentTone = 'slate';
        } else {
            $paymentLabel = (string) ($balance['display_status_label'] ?? '—');
            $paymentTone = match ($balance['coverage_status'] ?? null) {
                \App\Services\SettlementPaymentHealthService::STATUS_OK => 'emerald',
                \App\Services\SettlementPaymentHealthService::STATUS_OVERDUE => 'rose',
                \App\Services\SettlementPaymentHealthService::STATUS_DUE => 'amber',
                \App\Services\SettlementPaymentHealthService::STATUS_SHORTFALL => 'amber',
                default => 'slate',
            };
        }

        $contract = $participant->contract;
        if (! $contract && $participant->contract_id) {
            $contract = \App\Models\Contract::query()->find($participant->contract_id);
        }

        if (! $contract) {
            $contractLabel = 'Brak umowy';
            $contractTone = 'slate';
        } else {
            $status = (string) ($contract->status ?? 'draft');
            $contractLabel = \App\Models\Contract::$statuses[$status] ?? $status;
            if (in_array($status, ['signed', 'completed'], true)) {
                $contractTone = 'emerald';
                $payStatus = (string) ($contract->payment_status ?? '');
                if ($payStatus === 'partial') {
                    $contractLabel .= ' · częściowo opłacona';
                    $contractTone = 'amber';
                } elseif ($payStatus === 'paid') {
                    $contractLabel .= ' · opłacona';
                }
            } elseif ($status === 'sent') {
                $contractTone = 'amber';
                $contractLabel .= ' · oczekuje podpisu';
            } else {
                $contractTone = 'slate';
            }
        }

        return [
            'payment_label' => $paymentLabel,
            'payment_tone' => $paymentTone,
            'contract_label' => $contractLabel,
            'contract_tone' => $contractTone,
            'due_pln' => (float) ($balance['due_pln'] ?? 0),
            'paid_pln' => (float) ($balance['paid_pln'] ?? 0),
            'remaining_pln' => (float) ($balance['remaining_pln'] ?? 0),
        ];
    }

    private function findScopedParticipant(int $id): EventParticipant
    {
        return app(ClientAccessService::class)
            ->guardianParticipantsQuery(Auth::user(), $this->event)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'uczestnicy-'.$this->event->id.'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Imię', 'Nazwisko', 'Dieta', 'Zgody', 'Należne', 'Wpłacone', 'Różnica'], ';');

            $balances = app(\App\Services\ParticipantPaymentBalanceService::class);
            foreach ($this->participants as $p) {
                $balance = $balances->forParticipant($p);
                $due = (float) ($balance['due_pln'] ?? 0);
                $paid = (float) ($balance['paid_pln'] ?? 0);
                fputcsv($out, [
                    $p->first_name,
                    $p->last_name,
                    $p->diet,
                    $p->consentsCompletedLabel(),
                    number_format($due, 2, ',', ''),
                    number_format($paid, 2, ',', ''),
                    number_format((float) ($balance['remaining_pln'] ?? max(0, $due - $paid)), 2, ',', ''),
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function startEdit(int $id): void
    {
        $p = $this->findScopedParticipant($id);
        $this->editId = $p->id;
        $this->formFirstName = (string) ($p->first_name ?? '');
        $this->formLastName = (string) ($p->last_name ?? '');
        $this->formDiet = (string) ($p->diet ?? '');
        $this->formConsents = EventParticipantConsents::checklist($p->consents);
    }

    public function cancelEdit(): void
    {
        $this->editId = null;
        $this->formFirstName = '';
        $this->formLastName = '';
        $this->formDiet = '';
        $this->resetConsentForm();
    }

    public function save(): void
    {
        app(ClientAccessService::class)->assertPortalMutationsAllowed();

        $this->validate([
            'formFirstName' => ['required_without:formLastName', 'nullable', 'string', 'max:120'],
            'formLastName' => ['nullable', 'string', 'max:120'],
            'formDiet' => ['nullable', 'string', 'max:255'],
        ]);

        $flags = [];
        foreach (EventParticipantConsents::allKeys() as $key) {
            $flags[$key] = (bool) ($this->formConsents[$key] ?? false);
        }

        $participant = $this->editId
            ? $this->findScopedParticipant($this->editId)
            : null;

        $guardianContractId = app(ClientAccessService::class)
            ->accessFor(Auth::user(), $this->event, \App\Models\EventPortalAccess::ROLE_GUARDIAN)
            ?->contract_id;

        // Portal klienta: ten sam write-path co biuro; bez auto-tworzenia wiersza wpłat.
        $saved = app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $this->event,
            participant: $participant,
            firstName: trim($this->formFirstName) ?: null,
            lastName: trim($this->formLastName) ?: null,
            diet: trim($this->formDiet) ?: null,
            consentFlags: $flags,
            ensurePayment: false,
            source: EventParticipant::SOURCE_MANUAL,
        ));

        if (! $participant && $guardianContractId && Schema::hasColumn('event_participants', 'contract_id') && blank($saved->contract_id)) {
            $saved->forceFill(['contract_id' => $guardianContractId])->save();
            app(\App\Services\ContractDietSurchargeService::class)->applyForParticipant($saved->fresh() ?? $saved);
        }

        $this->cancelEdit();
        Notification::make()->title('Zapisano')->success()->send();
    }

    public function delete(int $id): void
    {
        app(ClientAccessService::class)->assertPortalMutationsAllowed();

        $this->findScopedParticipant($id)->delete();

        Notification::make()->title('Usunięto')->success()->send();
    }

    public function copyParentLink(int $id): void
    {
        if (! Schema::hasColumn('event_participants', 'parent_access_token')) {
            Notification::make()->title('Brak migracji tokenów rodzica')->danger()->send();

            return;
        }

        $participant = $this->findScopedParticipant($id);

        $url = app(ParentParticipantAccessService::class)->urlFor($participant);

        $this->dispatch('copy-to-clipboard', url: $url);

        Notification::make()
            ->title('Link dla rodzica')
            ->body($url)
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    public function consentLabels(): array
    {
        return EventParticipantConsents::labels();
    }

    /** @return list<string> */
    public function requiredConsentKeys(): array
    {
        return EventParticipantConsents::requiredKeys();
    }

    private function resetConsentForm(): void
    {
        $this->formConsents = array_fill_keys(EventParticipantConsents::allKeys(), false);
    }
}

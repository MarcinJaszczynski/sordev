<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Models\Event;
use App\Models\User;
use App\Services\PilotAdvanceService;
use App\Services\PilotChecklistService;
use App\Services\PilotContractorAssignmentService;
use App\Services\PilotSettlementService;
use App\Support\CurrencyAmountDisplay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

class ManageEventPilot extends EditRecord
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Pilot';

    protected static ?string $title = 'Pilot';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-pilot';

    protected bool $pendingPilotPaymentApproval = false;

    /** @var 'briefing'|'cash'|'checklist' */
    public string $pilotTab = 'briefing';

    public function setPilotTab(string $tab): void
    {
        if (! in_array($tab, ['briefing', 'cash', 'checklist'], true)) {
            return;
        }

        $this->pilotTab = $tab;
    }

    /**
     * Dane paska statusu, kafelków i stripu przepływu gotówki (tylko prezentacja).
     *
     * @return array{
     *     pilot_name: string,
     *     pilot_phone: ?string,
     *     pilot_email: ?string,
     *     pilot_settlement_form_label: ?string,
     *     contractor_url: ?string,
     *     participants_label: string,
     *     shared: bool,
     *     shared_at_label: ?string,
     *     email_sent_label: ?string,
     *     preview_url: string,
     *     preview_label: string,
     *     can_preview: bool,
     *     check_in_label: string,
     *     checklist_done: int,
     *     checklist_total: int,
     *     paid_label: string,
     *     return_label: string,
     *     return_danger: bool,
     *     plan_label: string,
     *     spent_label: string,
     *     expense_count: int
     * }
     */
    public function pilotPageSummary(): array
    {
        $event = $this->getRecord();
        $event->loadMissing(['pilotContractor', 'assignedUser', 'sharedWithPilotByUser']);

        $advance = app(PilotAdvanceService::class);
        $checklist = app(PilotChecklistService::class)->progressFor($event);

        $paying = max(0, (int) ($event->participant_count ?? 0));
        $gratis = max(0, (int) $event->resolveGratisCountForParticipantCount($paying ?: null));
        $participantsLabel = $gratis > 0
            ? sprintf('%d + %d (płacący + opiekunowie)', $paying, $gratis)
            : (string) $paying;

        $checkInKey = $event->check_in_status ?? 'pending';
        $checkInLabel = Event::getCheckInStatusOptions()[$checkInKey] ?? (string) $checkInKey;

        $contractor = $event->pilotContractor;
        $user = $event->assignedUser;
        $pilotName = $contractor?->name ?? $user?->name ?? 'brak pilota';
        $pilotPhone = $contractor?->phone ?: ($user?->phone ?: null);
        $pilotEmail = $contractor?->email ?: ($user?->email ?: null);
        $settlementFormLabel = $event->resolvedPilotSettlementFormLabel();


        $contractorUrl = null;
        if ($contractor && class_exists(\App\Filament\Resources\ContractorResource::class)) {
            try {
                $contractorUrl = \App\Filament\Resources\ContractorResource::getUrl('edit', [
                    'record' => $contractor->getKey(),
                ]);
            } catch (\Throwable) {
                $contractorUrl = null;
            }
        }

        $previewName = app(PilotContractorAssignmentService::class)
            ->pilotDisplayNameForEvent($event) ?? 'pilot';

        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($event);
        $rows = app(PilotSettlementService::class)->getCashReconciliation($settlement);
        $expenseCount = app(PilotSettlementService::class)->getExpenseLines($settlement)->count();

        $spentParts = [];
        $returnParts = [];
        $hasReturn = false;

        foreach ($rows as $row) {
            $code = (string) ($row->currency_code ?? '—');
            $spent = (float) ($row->actual_spent ?? 0);
            $toReturn = (float) ($row->to_return ?? 0);

            if ($spent > 0.009) {
                $spentParts[] = number_format($spent, 0, ',', ' ').' '.$code;
            }

            if ($toReturn > 0.009) {
                $hasReturn = true;
                $returnParts[] = number_format($toReturn, 0, ',', ' ').' '.$code;
            }
        }

        $shared = Schema::hasColumn('events', 'shared_with_pilot') && (bool) $event->shared_with_pilot;
        $sharedAtLabel = null;
        if ($shared && $event->shared_with_pilot_at) {
            $sharedAtLabel = $event->shared_with_pilot_at->format('d.m.Y H:i');
            if ($event->sharedWithPilotByUser) {
                $sharedAtLabel .= ' · '.$event->sharedWithPilotByUser->name;
            }
        }

        $emailSentLabel = null;
        if (Schema::hasColumn('events', 'pilot_trip_email_sent_at') && $event->pilot_trip_email_sent_at) {
            $emailSentLabel = $event->pilot_trip_email_sent_at->format('d.m.Y H:i');
        }

        return [
            'pilot_name' => $pilotName,
            'pilot_phone' => $pilotPhone,
            'pilot_email' => $pilotEmail,
            'pilot_settlement_form_label' => $settlementFormLabel,
            'contractor_url' => $contractorUrl,
            'participants_label' => $participantsLabel,
            'shared' => $shared,
            'shared_at_label' => $sharedAtLabel,
            'email_sent_label' => $emailSentLabel,
            'preview_url' => $this->pilotPreviewUrl(),
            'preview_label' => 'Podgląd jako '.$previewName,
            'can_preview' => (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro']),
            'check_in_label' => $checkInLabel,
            'checklist_done' => (int) ($checklist['done'] ?? 0),
            'checklist_total' => (int) ($checklist['total'] ?? 0),
            'paid_label' => $advance->formatOfficePayoutLabel($event),
            'return_label' => $returnParts !== [] ? implode(' / ', $returnParts) : '—',
            'return_danger' => $hasReturn,
            'plan_label' => $this->formatPlannedAdvanceLabel($event, $advance),
            'spent_label' => $spentParts !== [] ? implode(' / ', $spentParts) : '—',
            'expense_count' => $expenseCount,
        ];
    }

    protected function formatPlannedAdvanceLabel(Event $event, PilotAdvanceService $advance): string
    {
        $lines = $advance->plannedLines($event);

        if ($lines->isNotEmpty()) {
            $parts = [];
            foreach ($lines as $line) {
                $amount = (float) $line->amount;
                if ($amount <= 0.009) {
                    continue;
                }
                $parts[] = CurrencyAmountDisplay::formatIndicative($amount, $line->currency);
            }

            return $parts !== [] ? implode(' + ', $parts) : '—';
        }

        if (filled($event->pilot_advance_planned_amount)) {
            return CurrencyAmountDisplay::formatIndicative(
                (float) $event->pilot_advance_planned_amount,
                $event->pilotAdvancePaidCurrency,
            );
        }

        return '—';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make([
                Forms\Components\Placeholder::make('pilot_empty_state')
                    ->hiddenLabel()
                    ->visible(fn (): bool => blank($this->record->pilot_contractor_id) && blank($this->record->assigned_to))
                    ->content(new \Illuminate\Support\HtmlString(
                        '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-800/50">'
                        .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">Brak przypisanego pilota</p>'
                        .'<p class="mt-1 text-sm text-gray-500">Wybierz pilota w sekcji „Przypisanie pilota” poniżej.</p>'
                        .'</div>'
                    ))
                    ->columnSpanFull(),
            ])
                ->extraAttributes(['class' => 'pilot-form-tab pilot-form-tab--briefing'])
                ->columnSpanFull(),

            ...\App\Filament\Forms\EventReadinessFields::pilotPageSchema(),
        ]);
    }

    protected function pilotPreviewUrl(): string
    {
        $url = PilotEventResource::getUrl(
            'view',
            ['record' => $this->record->getKey()],
            panel: 'pilot',
        ).'?preview=1';

        $pilotId = app(PilotContractorAssignmentService::class)
            ->resolvePortalUserIdForEvent($this->record);

        if ($pilotId) {
            $url .= '&pilot='.$pilotId;
        }

        return $url;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zapisano dane pilota';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $assignmentService = app(PilotContractorAssignmentService::class);

        $this->record->loadMissing(['assignedUser', 'pilotContractor']);

        if (Schema::hasColumn('events', 'pilot_contractor_id')) {
            $data['pilot_contractor_id'] = $assignmentService->resolveContractorIdForEvent($this->record);

            if (filled($data['pilot_contractor_id'])) {
                $contractor = $this->record->pilotContractor
                    ?? \App\Models\Contractor::query()->find($data['pilot_contractor_id']);
                $data['pilot_birth_date'] = $contractor?->birth_date?->format('Y-m-d');
                $data['pilot_pesel'] = $contractor?->pesel;
            } else {
                $data['pilot_birth_date'] = $this->record->assignedUser?->birth_date?->format('Y-m-d');
                $data['pilot_pesel'] = $this->record->assignedUser?->pesel;
                $data['pilot_phone'] = $this->record->assignedUser?->phone;
                $data['pilot_email'] = $this->record->assignedUser?->email;
            }
        } else {
            $data['pilot_birth_date'] = $this->record->assignedUser?->birth_date?->format('Y-m-d');
            $data['pilot_pesel'] = $this->record->assignedUser?->pesel;
            $data['pilot_phone'] = $this->record->assignedUser?->phone;
            $data['pilot_email'] = $this->record->assignedUser?->email;
        }

        $data['pilot_advance_planned_lines'] = app(PilotAdvanceService::class)->plannedLinesFormState($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $assignmentService = app(PilotContractorAssignmentService::class);

        if (Schema::hasColumn('events', 'pilot_contractor_id')) {
            $previousContractor = (int) ($this->record->pilot_contractor_id ?? 0);
            $newContractor = (int) ($data['pilot_contractor_id'] ?? 0);
            $previousPilot = (int) ($this->record->assigned_to ?? 0);

            if ($previousContractor !== $newContractor) {
                if (Schema::hasColumn('events', 'shared_with_pilot')) {
                    $data['shared_with_pilot'] = false;
                    $data['shared_with_pilot_at'] = null;
                    $data['shared_with_pilot_by'] = null;

                    if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
                        $data['pilot_trip_email_sent_at'] = null;
                    }
                }
            }

            $contractor = $newContractor > 0
                ? \App\Models\Contractor::query()->find($newContractor)
                : null;
            $data['assigned_to'] = $assignmentService->resolvePortalUserId($contractor);

            if ($previousPilot !== (int) ($data['assigned_to'] ?? 0) && $previousContractor === $newContractor) {
                if (Schema::hasColumn('events', 'shared_with_pilot')) {
                    $data['shared_with_pilot'] = false;
                    $data['shared_with_pilot_at'] = null;
                    $data['shared_with_pilot_by'] = null;

                    if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
                        $data['pilot_trip_email_sent_at'] = null;
                    }
                }
            }
        } elseif (Schema::hasColumn('events', 'shared_with_pilot')) {
            $previousPilot = (int) ($this->record->assigned_to ?? 0);
            $newPilot = (int) ($data['assigned_to'] ?? 0);

            if ($previousPilot !== $newPilot) {
                $data['shared_with_pilot'] = false;
                $data['shared_with_pilot_at'] = null;
                $data['shared_with_pilot_by'] = null;

                if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
                    $data['pilot_trip_email_sent_at'] = null;
                }
            }
        }

        if (Schema::hasColumn('events', 'pilot_advance_planned_amount') && ! $this->record->pilot_funds_paid) {
            if (Schema::hasTable('pilot_advance_lines')) {
                unset($data['pilot_advance_planned_amount'], $data['pilot_advance_planned_at'], $data['pilot_advance_planned_by']);
            } else {
                $planned = $data['pilot_advance_planned_amount'] ?? null;
                $planned = $planned !== null && $planned !== '' ? round((float) $planned, 2) : null;
                $data['pilot_advance_planned_amount'] = $planned;

                if ($planned !== null && $planned > 0 && ! $this->record->pilot_advance_planned_at) {
                    $data['pilot_advance_planned_at'] = now();
                    $data['pilot_advance_planned_by'] = Auth::id();
                } elseif ($planned === null) {
                    $data['pilot_advance_planned_at'] = null;
                    $data['pilot_advance_planned_by'] = null;
                }
            }
        } elseif ($this->record->pilot_funds_paid) {
            unset($data['pilot_advance_planned_amount']);
        }

        unset($data['pilot_advance_planned_lines']);

        if (Schema::hasColumn('events', 'pilot_settlement_form') && array_key_exists('pilot_settlement_form', $data)) {
            $data['pilot_settlement_form'] = filled($data['pilot_settlement_form'] ?? null)
                ? (string) $data['pilot_settlement_form']
                : null;
        }

        if (Schema::hasColumn('events', 'pilot_funds_paid')) {
            $this->pendingPilotPaymentApproval = ! empty($data['pilot_funds_paid']) && ! $this->record->pilot_funds_paid;
            unset($data['pilot_funds_paid']);
        }

        unset($data['pilot_birth_date'], $data['pilot_pesel'], $data['pilot_phone'], $data['pilot_email']);

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();
        $assignmentService = app(PilotContractorAssignmentService::class);
        $contractorId = Schema::hasColumn('events', 'pilot_contractor_id')
            ? (filled($state['pilot_contractor_id'] ?? null) ? (int) $state['pilot_contractor_id'] : null)
            : null;

        if ($contractorId) {
            $assignmentService->syncContractorDemographics(
                $contractorId,
                $state['pilot_birth_date'] ?? null,
                $state['pilot_pesel'] ?? null,
            );
        }

        $assignmentService->syncPortalUserDemographicsFromContractor(
            $contractorId,
            filled($this->record->assigned_to) ? (int) $this->record->assigned_to : null,
        );

        if (! Schema::hasColumn('events', 'pilot_contractor_id')) {
            User::syncPilotDemographics(
                $this->record->assigned_to,
                $state['pilot_birth_date'] ?? null,
                $state['pilot_pesel'] ?? null,
                $state['pilot_phone'] ?? null,
            );
        }

        if (Schema::hasTable('pilot_advance_lines') && ! $this->record->pilot_funds_paid) {
            app(PilotAdvanceService::class)->syncPlannedLines(
                $this->record->fresh(),
                $state['pilot_advance_planned_lines'] ?? [],
            );
        }

        if ($this->pendingPilotPaymentApproval) {
            $advanceService = app(PilotAdvanceService::class);
            $plannedLines = $state['pilot_advance_planned_lines'] ?? null;

            if (Schema::hasTable('pilot_advance_lines') && is_array($plannedLines) && $plannedLines !== []) {
                $advanceService->approvePayment(
                    $this->record->fresh(),
                    comment: $state['pilot_advance_paid_comment'] ?? null,
                    paidLines: collect($plannedLines)->map(fn (array $line) => [
                        'amount' => $line['amount'] ?? 0,
                        'currency_id' => (int) ($line['currency_id'] ?? 0),
                    ])->all(),
                );
            } else {
                $advanceService->approvePayment(
                    $this->record->fresh(),
                    isset($state['pilot_advance_paid_amount']) ? (float) $state['pilot_advance_paid_amount'] : null,
                    $state['pilot_advance_paid_currency_id'] ?? null,
                    $state['pilot_advance_paid_comment'] ?? null,
                );
            }

            $this->pendingPilotPaymentApproval = false;
        }

        $this->record->refresh();

        if (
            Schema::hasColumn('events', 'shared_with_pilot')
            && (filled($this->record->assigned_to) || filled($this->record->pilot_contractor_id))
            && ! $this->record->shared_with_pilot
        ) {
            Notification::make()
                ->title('Pilot przypisany — udostępnij wycieczkę')
                ->body('Kliknij «Udostępnij pilotowi» w sekcji Pilot powyżej. Bez tego pilot zobaczy 403 w panelu /pilot.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    #[On('pilot-portal-visibility-updated')]
    public function refreshPilotPortalVisibility(?int $eventId = null): void
    {
        if ($eventId !== null && (int) $this->record->getKey() !== $eventId) {
            return;
        }

        $this->record->refresh();
    }

    #[On('pilot-checklist-updated')]
    public function refreshPilotChecklistStats(): void
    {
        // Statystyki w pasku liczą się z bazy przy re-renderze.
    }

    public function scrollToPilotCashDesk(): void
    {
        $this->setPilotTab('cash');
        $this->js(<<<'JS'
            setTimeout(() => {
                document.getElementById('pilot-cash-desk')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        JS);
    }

    /**
     * Cofa omyłkowo zatwierdzoną wypłatę gotówki (flaga + saldo „Od biura”).
     */
    public function revokePilotOfficePayout(): void
    {
        abort_unless((bool) $this->record->pilot_funds_paid, 403);

        try {
            app(PilotAdvanceService::class)->clearAllOfficeCashPayouts($this->record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Nie można cofnąć wypłaty')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->record->refresh();
        $this->fillForm();

        Notification::make()
            ->title('Cofnięto wypłatę gotówki')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        // Wymiana / zbiórka / podgląd / PDF — w belce statusu i kolumnie rozliczenia, nie w headerze Filament.
        return [];
    }
}

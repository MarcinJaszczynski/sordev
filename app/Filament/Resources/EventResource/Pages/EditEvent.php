<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Actions\Events\ChangeEventStatusAction;
use App\Actions\Events\RecalculateEventTotalsAction;
use App\Data\ChangeEventStatusData;
use App\Data\RecalculateEventTotalsData;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventOrderingPartyLookups;
use App\Models\Event;
use App\Services\EventHotelPlanService;
use App\Services\EventManualPricePerPersonService;
use App\Services\EventParticipantCountChangeService;
use App\Services\NotificationService;
use App\Support\MoneyFormatter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EditEvent extends EditRecord
{
    use HasEventWorkflowContext;
    use InteractsWithEventOrderingPartyLookups;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.edit-event';

    protected static ?string $navigationLabel = 'Podsumowanie';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getSubheading(): ?string
    {
        return 'Uzupełnij gotowość imprezy, potem przejdź do Programu, Operacji i Finansów';
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        return $this->eventRecordBreadcrumbs(sectionLabel: 'Podsumowanie');
    }

    protected int $pendingGratisCount = 0;

    protected int $pendingStaffCount = 0;

    protected int $pendingDriverCount = 0;

    /** @var array{use: bool, lines: array<int, array{amount?: mixed, currency_id?: mixed}>}|null */
    protected ?array $pendingManualPriceSync = null;

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $pendingOrderingParties = null;

    protected ?int $previousParticipantCount = null;

    protected ?string $previousStatus = null;

    protected ?string $pendingStatusChange = null;

    /**
     * null = auto (przebuduj gdy brak struktury do ochrony),
     * true = przebuduj ze szablonu,
     * false = zapisz liczbę bez przebudowy pokoi.
     */
    protected ?bool $refreshRoomStructureOnSave = null;

    protected function getHeaderActions(): array
    {
        // Wspólne: Oferta Word / Nowe zadanie — tylko w boxie workflow (HasEventWorkflowContext).
        return [
            Actions\Action::make('open_participants')
                ->label('Uczestnicy')
                ->icon('heroicon-o-users')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('participants', ['record' => $this->record]))
                ->visible(fn (): bool => Schema::hasTable('event_participants')),
            Actions\Action::make('open_finance')
                ->label('Finanse')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('finance', ['record' => $this->record])),
            Actions\Action::make('change_status')
                ->label('Zmień status')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->slideOver()
                ->modalHeading('Zmień status imprezy')
                ->modalWidth('md')
                ->fillForm(fn (): array => [
                    'status' => $this->record->status,
                ])
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(Event::getStatusOptions())
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('reason')
                        ->label('Powód (opcjonalnie)')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
                        event: $this->record,
                        status: (string) $data['status'],
                        reason: $data['reason'] ?? null,
                    ));

                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Zmieniono status')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $participantCount = max(1, (int) ($data['participant_count'] ?? 1));
        $gratisCount = $this->record->resolveGratisCountForParticipantCount($participantCount);

        try {
            $data['total_cost'] = app(RecalculateEventTotalsAction::class)(new RecalculateEventTotalsData(
                event: $this->record,
                participantCount: $participantCount,
                gratisCount: $gratisCount,
                startPlaceId: $this->record->start_place_id ? (int) $this->record->start_place_id : null,
            ));
        } catch (\Throwable $e) {
            // keep existing total_cost value when recalculation fails
        }

        $data['gratis_count'] = $gratisCount;
        $data['staff_count'] = $this->record->resolveStaffCountForParticipantCount($participantCount);
        $data['driver_count'] = $this->record->resolveDriverCountForParticipantCount($participantCount);

        $data['ordering_parties'] = app(\App\Services\EventOrderingPartyService::class)
            ->partiesToFormState($this->record);
        $this->seedLookupOrderingParties($data['ordering_parties']);

        $data = array_merge($data, app(EventManualPricePerPersonService::class)->formState($this->record));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousParticipantCount = max(1, (int) ($this->record->participant_count ?? 1));
        $this->previousStatus = (string) ($this->record->status ?? '');

        $newStatus = isset($data['status']) ? (string) $data['status'] : '';
        if ($newStatus !== '' && $newStatus !== $this->previousStatus) {
            $this->pendingStatusChange = $newStatus;
            unset($data['status']);
        } else {
            $this->pendingStatusChange = null;
        }

        $data = $this->normalizeScheduleData($data);

        $participantCount = max(1, (int) ($data['participant_count'] ?? 1));
        $gratisCount = max(0, (int) ($data['gratis_count'] ?? 0));
        $staffCount = max(0, (int) ($data['staff_count'] ?? 0));
        $driverCount = max(0, (int) ($data['driver_count'] ?? 0));
        $startPlaceId = array_key_exists('start_place_id', $data)
            ? ($data['start_place_id'] !== null && $data['start_place_id'] !== '' ? (int) $data['start_place_id'] : null)
            : ($this->record->start_place_id ? (int) $this->record->start_place_id : null);

        $this->pendingGratisCount = $gratisCount;
        $this->pendingStaffCount = $staffCount;
        $this->pendingDriverCount = $driverCount;
        $this->pendingOrderingParties = $this->resolvedOrderingParties();
        unset($data['gratis_count'], $data['staff_count'], $data['driver_count'], $data['ordering_parties']);

        // Przygotuj dane dla synchronizacji ceny ręcznej
        $useManual = (bool) ($data['use_manual_price_per_person'] ?? false);
        $lines = $useManual ? ($data['manual_price_per_person_lines'] ?? []) : [];

        $this->pendingManualPriceSync = [
            'use' => $useManual,
            'lines' => $lines,
        ];
        unset($data['use_manual_price_per_person'], $data['manual_price_per_person_lines']);

        // Atrybuty transportu z formularza muszą trafić do modelu przed kalkulacją
        // (EventTransportCostCalculator czyta transfer_km / program_km / bus z rekordu).
        $this->applyPendingTransportAttributesForCalculation($data, $participantCount, $startPlaceId);

        try {
            $data['total_cost'] = app(RecalculateEventTotalsAction::class)(new RecalculateEventTotalsData(
                event: $this->record,
                participantCount: $participantCount,
                gratisCount: $gratisCount,
                staffCount: $staffCount,
                driverCount: $driverCount,
                startPlaceId: $startPlaceId,
            ));
        } catch (\Throwable $e) {
            // keep existing total_cost when recalculation fails
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        if ($this->refreshRoomStructureOnSave !== null) {
            return;
        }

        $newCount = max(1, (int) data_get($this->data, 'participant_count', 1));
        $oldCount = max(1, (int) ($this->record->participant_count ?? 1));

        if ($newCount === $oldCount) {
            return;
        }

        if (! app(EventHotelPlanService::class)->eventHasRoomStructureWorthProtecting($this->record)) {
            return;
        }

        $this->mountAction('confirmParticipantCountRoomRefresh', [
            'oldCount' => $oldCount,
            'newCount' => $newCount,
        ]);

        $this->halt();
    }

    public function confirmParticipantCountRoomRefreshAction(): Actions\Action
    {
        return Actions\Action::make('confirmParticipantCountRoomRefresh')
            ->label('Struktura pokoi')
            ->modalHeading('Zmiana liczby uczestników a struktura pokoi')
            ->modalDescription(function (array $arguments): string {
                $oldCount = (int) ($arguments['oldCount'] ?? 0);
                $newCount = (int) ($arguments['newCount'] ?? 0);

                return "Zmieniasz liczbę uczestników z {$oldCount} na {$newCount}. "
                    .'Przebudowa struktury pokoi ze szablonu usunie ręczne poprawki pokoi i obsadę. '
                    .'Przy małej zmianie możesz zapisać liczbę bez przebudowy i skorygować plan hotelowy ręcznie.';
            })
            ->modalSubmitActionLabel('Zapisz i przebuduj pokoje')
            ->modalCancelActionLabel('Anuluj')
            ->color('warning')
            ->extraModalFooterActions([
                Actions\Action::make('saveWithoutRoomRefresh')
                    ->label('Zapisz bez przebudowy')
                    ->color('gray')
                    ->action(function (): void {
                        $this->refreshRoomStructureOnSave = false;
                        $this->save();
                    }),
            ])
            ->action(function (): void {
                $this->refreshRoomStructureOnSave = true;
                $this->save();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyPendingTransportAttributesForCalculation(
        array $data,
        int $participantCount,
        ?int $startPlaceId,
    ): void {
        if (array_key_exists('transfer_km', $data)) {
            $this->record->transfer_km = $data['transfer_km'];
        }

        if (array_key_exists('program_km', $data)) {
            $this->record->program_km = $data['program_km'];
        }

        if (array_key_exists('bus_id', $data)) {
            $busId = $data['bus_id'] !== null && $data['bus_id'] !== '' ? (int) $data['bus_id'] : null;
            if ((int) ($this->record->bus_id ?? 0) !== (int) ($busId ?? 0)) {
                $this->record->bus_id = $busId;
                $this->record->unsetRelation('bus');
            }
        }

        if (array_key_exists('use_manual_transport_cost', $data)) {
            $this->record->use_manual_transport_cost = (bool) $data['use_manual_transport_cost'];
        }

        if (array_key_exists('manual_transport_cost', $data)) {
            $this->record->manual_transport_cost = $data['manual_transport_cost'];
        }

        $this->record->start_place_id = $startPlaceId;
        $this->record->participant_count = $participantCount;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeScheduleData(array $data): array
    {
        if (empty($data['start_date'])) {
            return $data;
        }

        $start = \Carbon\Carbon::parse($data['start_date']);
        $durationDays = max(1, (int) ($data['duration_days'] ?? $this->record->duration_days ?? 1));
        $oldStart = $this->record->start_date?->toDateString();
        $oldEnd = $this->record->end_date?->toDateString();
        $newStart = $start->toDateString();
        $newEnd = ! empty($data['end_date'])
            ? \Carbon\Carbon::parse($data['end_date'])->toDateString()
            : null;

        // Start zmieniony, a koniec stary/pusty → przesuń koniec wg liczby dni (nie skracaj wyjazdu).
        $startChanged = $oldStart !== null && $oldStart !== $newStart;
        $endUnchanged = $newEnd === null || ($oldEnd !== null && $oldEnd === $newEnd);

        if (($startChanged && $endUnchanged) || $newEnd === null) {
            $data['end_date'] = $start->copy()->addDays($durationDays - 1)->toDateString();
            $data['duration_days'] = $durationDays;

            return $data;
        }

        $end = \Carbon\Carbon::parse($data['end_date']);

        if ($end->lt($start)) {
            $data['end_date'] = $start->toDateString();
            $data['duration_days'] = 1;
        } else {
            $data['duration_days'] = max(1, $start->diffInDays($end) + 1);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $newParticipantCount = max(1, (int) ($this->record->participant_count ?? 1));
        $participantCountChanged = $this->previousParticipantCount !== null
            && $this->previousParticipantCount !== $newParticipantCount;

        if ($participantCountChanged) {
            app(EventParticipantCountChangeService::class)->notifyOffice(
                $this->record,
                $this->previousParticipantCount,
                $newParticipantCount,
                EventParticipantCountChangeService::REASON_MANUAL_EDIT,
                ['editor_name' => Auth::user()?->name],
            );
        }

        $this->previousParticipantCount = null;

        if (filled($this->pendingStatusChange)) {
            app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
                event: $this->record->fresh() ?? $this->record,
                status: $this->pendingStatusChange,
            ));
            $this->pendingStatusChange = null;
            $this->previousStatus = null;
        }

        if ($this->pendingManualPriceSync !== null) {
            app(EventManualPricePerPersonService::class)->sync(
                $this->record->fresh(),
                $this->pendingManualPriceSync['use'],
                $this->pendingManualPriceSync['lines'] ?? [],
            );
            $this->pendingManualPriceSync = null;
            $this->dispatch('event-price-table-refresh');
        }

        $parties = $this->pendingOrderingParties;
        $this->pendingOrderingParties = null;

        if (is_array($parties)) {
            $this->record->syncOrderingParties($parties);
        } else {
            $this->record->syncPrimaryClientFromOrderingParties();
        }

        $state = $this->form->getState();

        if ($this->record->requiresInsuranceWorkflow()) {
            $this->record->updateInsuranceFromFormData($state);
        }

        try {
            $this->record->syncQtyVariantForGroup(
                max(1, (int) ($this->record->participant_count ?? 1)),
                $this->pendingGratisCount,
                $this->pendingStaffCount,
                $this->pendingDriverCount,
            );
        } catch (\Throwable $e) {
            // ignore qty sync failures after save
        }

        if ($participantCountChanged && $this->refreshRoomStructureOnSave !== false) {
            try {
                app(EventHotelPlanService::class)->refreshRoomStructureFromTemplate($this->record->fresh());
            } catch (\Throwable $e) {
                // ignore hotel structure refresh failures
            }
        }

        $this->refreshRoomStructureOnSave = null;

        // Zawsze przelicz ilości w punktach programu po zapisie
        try {
            $this->record->resyncProgramPointQuantities();
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $this->record->refreshActiveSettlementCosts();
        } catch (\Throwable $e) {
            // ignore settlement refresh failures silently
        }

        if ($userId = Auth::id()) {
            NotificationService::clearCacheForUser($userId);
        }

        $this->dispatch('event-program-points-refresh');
    }

    public function financials(): array
    {
        $record = $this->record;
        $participantCount = max(1, (int) ($record->participant_count ?? 1));
        $gratisCount = max(0, $record->resolveGratisCountForParticipantCount($participantCount));

        $calc = '—';
        $perPersonLabel = '—';
        try {
            $summary = app(\App\Services\EventPriceSummaryService::class)->forEvent(
                $record,
                $participantCount,
                $gratisCount,
                includeNearest: false,
            );
            if ($summary['ready'] ?? false) {
                $calc = MoneyFormatter::format((float) $summary['total_pln'], 'PLN');
                $perPersonLabel = (string) $summary['price_per_person_label'];
            }
        } catch (\Throwable) {
            $calc = 'Brak danych kalkulacji';
        }

        $effectivePerPerson = $record->resolvedPricePerPerson($participantCount);
        if (($perPersonLabel === '—' || $perPersonLabel === '') && $effectivePerPerson > 0) {
            $perPersonLabel = MoneyFormatter::format($effectivePerPerson, 'PLN');
        }

        $settlement = null;
        try {
            $settlement = $record->settlements()
                ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                ->latest('id')->first();
        } catch (\Throwable) {
        }

        $planned = $settlement && $settlement->planned_cost_pln !== null
            ? MoneyFormatter::format((float) $settlement->planned_cost_pln, 'PLN')
            : '— (brak rozliczenia)';

        $actual = $settlement && $settlement->actual_cost_pln !== null
            ? MoneyFormatter::format((float) $settlement->actual_cost_pln, 'PLN')
            : '— (brak rozliczenia)';

        $clientsPaid = '—';
        try {
            // Prefer ledger totals from active settlement (SSoT) over raw agreement projections.
            if ($settlement && $settlement->participant_paid_pln !== null) {
                $clientsPaid = MoneyFormatter::format((float) $settlement->participant_paid_pln, 'PLN');
            } elseif ($record->agreements()->exists()) {
                $clientsPaid = MoneyFormatter::format((float) $record->agreements()->sum('amount_paid'), 'PLN');
            } else {
                $clientsPaid = '— (brak umów)';
            }
        } catch (\Throwable) {
        }

        return [
            'calc' => $calc,
            'per_person' => $perPersonLabel,
            'planned_cost' => $planned,
            'actual_cost' => $actual,
            'clients_paid' => $clientsPaid,
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Dane podstawowe';
    }
}

<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Actions\Events\ChangeEventStatusAction;
use App\Actions\Events\RecalculateEventTotalsAction;
use App\Data\ChangeEventStatusData;
use App\Data\RecalculateEventTotalsData;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Models\Event;
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

    /** @var array{use: bool, lines: array<int, array{amount?: mixed, currency_id?: mixed}>}|null */
    protected ?array $pendingManualPriceSync = null;

    protected ?int $previousParticipantCount = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_offer')
                ->label('Oferta Word')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('admin.events.offer.word', $this->record))
                ->openUrlInNewTab(),
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

        $data['ordering_parties'] = app(\App\Services\EventOrderingPartyService::class)
            ->partiesToFormState($this->record);

        $data = array_merge($data, app(EventManualPricePerPersonService::class)->formState($this->record));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousParticipantCount = max(1, (int) ($this->record->participant_count ?? 1));

        $data = $this->normalizeScheduleData($data);

        $participantCount = max(1, (int) ($data['participant_count'] ?? 1));
        $gratisCount = max(0, (int) ($data['gratis_count'] ?? 0));
        $startPlaceId = array_key_exists('start_place_id', $data)
            ? ($data['start_place_id'] !== null && $data['start_place_id'] !== '' ? (int) $data['start_place_id'] : null)
            : ($this->record->start_place_id ? (int) $this->record->start_place_id : null);

        $this->pendingGratisCount = $gratisCount;
        unset($data['gratis_count'], $data['ordering_parties']);

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
                startPlaceId: $startPlaceId,
            ));
        } catch (\Throwable $e) {
            // keep existing total_cost when recalculation fails
        }

        return $data;
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
        if (! empty($data['start_date']) && empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $durationDays = max(1, (int) ($data['duration_days'] ?? $this->record->duration_days ?? 1));
            $data['end_date'] = $start->copy()->addDays($durationDays - 1)->toDateString();
        }

        if (! empty($data['start_date']) && ! empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $end = \Carbon\Carbon::parse($data['end_date']);

            if ($end->lt($start)) {
                $data['end_date'] = $start->toDateString();
                $data['duration_days'] = 1;
            } else {
                $data['duration_days'] = max(1, $start->diffInDays($end) + 1);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $newParticipantCount = max(1, (int) ($this->record->participant_count ?? 1));

        if ($this->previousParticipantCount !== null && $this->previousParticipantCount !== $newParticipantCount) {
            app(EventParticipantCountChangeService::class)->notifyOffice(
                $this->record,
                $this->previousParticipantCount,
                $newParticipantCount,
                EventParticipantCountChangeService::REASON_MANUAL_EDIT,
                ['editor_name' => Auth::user()?->name],
            );
        }

        $this->previousParticipantCount = null;

        if ($this->pendingManualPriceSync !== null) {
            app(EventManualPricePerPersonService::class)->sync(
                $this->record->fresh(),
                $this->pendingManualPriceSync['use'],
                $this->pendingManualPriceSync['lines'] ?? [],
            );
            $this->pendingManualPriceSync = null;
            $this->dispatch('event-price-table-refresh');
        }

        $parties = $this->form->getState()['ordering_parties'] ?? null;
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
                $this->pendingGratisCount
            );
        } catch (\Throwable $e) {
            // ignore qty sync failures after save
        }

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

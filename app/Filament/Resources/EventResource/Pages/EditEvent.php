<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Services\EventManualPricePerPersonService;
use App\Services\EventParticipantCountChangeService;
use App\Services\NotificationService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditEvent extends EditRecord
{
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.edit-event';

    protected static ?string $navigationLabel = 'Podsumowanie i dane';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected int $pendingGratisCount = 0;

    /** @var array{use: bool, lines: array<int, array{amount?: mixed, currency_id?: mixed}>}|null */
    protected ?array $pendingManualPriceSync = null;

    protected ?int $previousParticipantCount = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $participantCount = max(1, (int) ($data['participant_count'] ?? 1));
        $gratisCount = $this->record->resolveGratisCountForParticipantCount($participantCount);

        try {
            $data['total_cost'] = $this->record->resolvedBaseTotalCost(
                $participantCount,
                $gratisCount,
                $this->record->start_place_id ? (int) $this->record->start_place_id : null
            );
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
        $startPlaceId = $this->record->start_place_id ? (int) $this->record->start_place_id : null;

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

        try {
            $data['total_cost'] = $this->record->resolvedBaseTotalCost(
                $participantCount,
                $gratisCount,
                $startPlaceId
            );
        } catch (\Throwable $e) {
            // keep existing total_cost when recalculation fails
        }

        return $data;
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

        $calc = '—';
        $perPerson = null;
        try {
            $calcData = app(EventManualPricePerPersonService::class)->calculatedForEvent($record, $participantCount);
            if ($calcData) {
                $total = round((float) ($calcData['total_pln'] ?? 0), 2);
                $perPerson = (float) ($calcData['price_per_person_rounded'] ?? $calcData['price_per_person'] ?? 0);
                $calc = number_format($total, 2, ',', ' ').' PLN';
            }
        } catch (\Throwable) {
            $calc = 'Brak danych kalkulacji';
        }

        $effectivePerPerson = $record->resolvedPricePerPerson($participantCount);
        if ($effectivePerPerson > 0 && $perPerson !== null && abs($effectivePerPerson - $perPerson) > 0.009) {
            $perPersonLabel = number_format($perPerson, 2, ',', ' ').' PLN (kalkulacja) · '
                .number_format($effectivePerPerson, 2, ',', ' ').' PLN (obowiązująca)';
        } elseif ($effectivePerPerson > 0) {
            $perPersonLabel = number_format($effectivePerPerson, 2, ',', ' ').' PLN';
        } else {
            $perPersonLabel = '—';
        }

        $settlement = null;
        try {
            $settlement = $record->settlements()
                ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                ->latest('id')->first();
        } catch (\Throwable) {
        }

        $planned = $settlement && $settlement->planned_cost_pln !== null
            ? number_format((float) $settlement->planned_cost_pln, 2, ',', ' ').' PLN'
            : '— (brak rozliczenia)';

        $actual = $settlement && $settlement->actual_cost_pln !== null
            ? number_format((float) $settlement->actual_cost_pln, 2, ',', ' ').' PLN'
            : '— (brak rozliczenia)';

        $clientsPaid = '—';
        try {
            if ($record->agreements()->exists()) {
                $clientsPaid = number_format((float) $record->agreements()->sum('amount_paid'), 2, ',', ' ').' PLN';
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

    public function keyInfo(): array
    {
        $record = $this->record;
        $start = $record->start_date?->format('d.m.Y') ?? '—';
        $end = $record->end_date?->format('d.m.Y');
        $termin = $end && $end !== $start ? "{$start} – {$end}" : $start;

        $participants = (int) ($record->participant_count ?? 0);
        $gratis = $record->resolveGratisCountForParticipantCount($participants);
        $participantsDisplay = $gratis > 0 ? "{$participants}+{$gratis}" : (string) $participants;

        return [
            ['label' => 'Kod', 'value' => $record->code ?: '—'],
            ['label' => 'Status', 'value' => \App\Models\Event::getStatusOptions()[$record->status] ?? (string) $record->status],
            ['label' => 'Termin', 'value' => $termin],
            ['label' => 'Liczba dni', 'value' => (string) ($record->duration_days ?? '—')],
            ['label' => 'Uczestnicy', 'value' => $participantsDisplay],
            ['label' => 'Szablon', 'value' => $record->eventTemplate?->name ?? 'Bez szablonu'],
            ['label' => 'Pilot', 'value' => $record->assignedUser?->name ?? 'Nieprzypisany'],
            ['label' => 'Miejsce podstawienia', 'value' => $record->startPlace?->name ?? '—'],
            ['label' => 'Zamawiający', 'value' => $record->client_name ?: '—'],
        ];
    }

    public function shortcuts(): array
    {
        $links = [
            ['label' => 'Program', 'icon' => 'heroicon-o-list-bullet', 'page' => 'edit-program'],
            ['label' => 'Uczestnicy', 'icon' => 'heroicon-o-users', 'page' => 'participants'],
            ['label' => 'Rezerwacje', 'icon' => 'heroicon-o-calendar', 'page' => 'reservations'],
            ['label' => 'Transport', 'icon' => 'heroicon-o-truck', 'page' => 'transport'],
            ['label' => 'Hotele', 'icon' => 'heroicon-o-building-office-2', 'page' => 'hotel-planning'],
            ['label' => 'Pilot', 'icon' => 'heroicon-o-user-circle', 'page' => 'pilot'],
            ['label' => 'Finanse', 'icon' => 'heroicon-o-banknotes', 'page' => 'finance'],
            ['label' => 'Dokumenty', 'icon' => 'heroicon-o-folder', 'page' => 'documents'],
            ['label' => 'Rezygnacje', 'icon' => 'heroicon-o-user-minus', 'page' => 'resignations'],
        ];

        return array_map(fn (array $link): array => [
            'label' => $link['label'],
            'icon' => $link['icon'],
            'url' => EventResource::getUrl($link['page'], ['record' => $this->record]),
        ], $links);
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

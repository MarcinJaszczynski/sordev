<?php

namespace App\Filament\Forms;

use App\Models\Event;
use App\Services\EventCostCalculator;
use App\Services\EventTransportCostCalculator;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

/**
 * Podgląd kosztu transportu ze stanu formularza (live), bez osobnego silnika wyceny.
 */
class EventTransportCostSummaryFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function placeholder(): array
    {
        return [
            Forms\Components\Placeholder::make('transport_cost_summary')
                ->hiddenLabel()
                ->content(fn (?Event $record, Get $get): HtmlString|string => self::render($record, $get))
                ->columnSpanFull(),
        ];
    }

    public static function render(?Event $record, Get $get): HtmlString|string
    {
        if (! $record) {
            return '';
        }

        // Dotknięcie pól live — Filament odświeża Placeholder przy ich zmianie.
        $get('bus_id');
        $get('transfer_km');
        $get('program_km');
        $get('duration_days');
        if (Schema::hasColumn('events', 'use_manual_transport_cost')) {
            $get('use_manual_transport_cost');
            $get('manual_transport_cost');
        }

        $original = self::snapshotTransportAttributes($record);

        try {
            self::applyFormState($record, $get, $original);
            EventCostCalculator::clearRequestCache();

            $calculator = new EventTransportCostCalculator($record);
            $variant = self::resolveVariant($record);

            // Render od razu — finally przywraca atrybuty na $record zanim View się narysuje.
            return new HtmlString(view('partials.event-transport-summary', [
                'record' => $record,
                'transportCost' => $calculator->effectiveTransportCost($variant),
                'eventTransportKm' => $calculator->resolveTransportKm(),
            ])->render());
        } finally {
            foreach ($original as $attribute => $value) {
                $record->setAttribute($attribute, $value);
            }
            $record->unsetRelation('bus');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotTransportAttributes(Event $record): array
    {
        $attrs = [
            'bus_id' => $record->getAttributes()['bus_id'] ?? $record->bus_id,
            'transfer_km' => $record->getAttributes()['transfer_km'] ?? $record->transfer_km,
            'program_km' => $record->getAttributes()['program_km'] ?? $record->program_km,
            'duration_days' => $record->getAttributes()['duration_days'] ?? $record->duration_days,
        ];

        if (Schema::hasColumn('events', 'use_manual_transport_cost')) {
            $attrs['use_manual_transport_cost'] = $record->getAttributes()['use_manual_transport_cost']
                ?? $record->use_manual_transport_cost;
            $attrs['manual_transport_cost'] = $record->getAttributes()['manual_transport_cost']
                ?? $record->manual_transport_cost;
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $original
     */
    private static function applyFormState(Event $record, Get $get, array $original): void
    {
        $formBusId = $get('bus_id');
        if ($formBusId !== null && $formBusId !== '' && (int) $formBusId > 0) {
            $record->bus_id = (int) $formBusId;
        } elseif (($formBusId === null || $formBusId === '') && self::formLooksHydrated($get)) {
            // Po hydrate formularza puste Select = odpięty autokar (podgląd na żywo).
            $record->bus_id = null;
        }
        $record->unsetRelation('bus');

        self::applyNumericOverride($record, 'transfer_km', $get('transfer_km'), $original['transfer_km'] ?? null);
        self::applyNumericOverride($record, 'program_km', $get('program_km'), $original['program_km'] ?? null);

        $formDuration = $get('duration_days');
        if ($formDuration !== null && $formDuration !== '' && (int) $formDuration > 0) {
            $record->duration_days = (int) $formDuration;
        }

        if (! Schema::hasColumn('events', 'use_manual_transport_cost')) {
            return;
        }

        $formManual = $get('use_manual_transport_cost');
        if ($formManual !== null) {
            $record->use_manual_transport_cost = (bool) $formManual;
        }

        $formManualCost = $get('manual_transport_cost');
        if ($formManualCost !== null && $formManualCost !== '') {
            $record->manual_transport_cost = $formManualCost;
        }
    }

    /**
     * Odróżnia stan przed fill (puste Select) od świadomego odpięcia autokaru.
     */
    private static function formLooksHydrated(Get $get): bool
    {
        return filled($get('start_date'))
            || filled($get('transport_contractor_id'))
            || filled($get('program_km'))
            || filled($get('transfer_km'))
            || (bool) $get('use_manual_transport_cost');
    }

    private static function applyNumericOverride(
        Event $record,
        string $attribute,
        mixed $formValue,
        mixed $originalValue,
    ): void {
        if ($formValue === null || $formValue === '') {
            return;
        }

        $formFloat = (float) $formValue;
        $originalFloat = (float) ($originalValue ?? 0);

        // default(0) nie może wyzerować realnych km z rekordu przed sensowną edycją.
        if ($formFloat <= 0.0 && $originalFloat > 0.0) {
            return;
        }

        $record->setAttribute($attribute, $formValue);
    }

    /**
     * Ten sam wariant qty co EventCostCalculator (najbliższy do participant_count).
     *
     * @return array{qty:int,gratis:int,staff:int,driver:int}
     */
    private static function resolveVariant(Event $record): array
    {
        $paying = max(1, (int) ($record->participant_count ?? 1));

        $record->loadMissing('qtyVariants');
        $variant = $record->qtyVariants
            ->sortBy(fn ($v) => abs((int) ($v->qty ?? 0) - $paying))
            ->first();

        return [
            'qty' => $paying,
            'gratis' => max(0, (int) ($variant->gratis ?? 0)),
            'staff' => max(0, (int) ($variant->staff ?? 1)),
            'driver' => max(0, (int) ($variant->driver ?? 1)),
        ];
    }
}

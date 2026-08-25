<?php

namespace App\Support;

use App\Models\Bus;
use App\Models\Event;
use App\Models\Vehicle;
use Illuminate\Support\HtmlString;

/**
 * Ostrzeżenie UI: uczestnicy + opiekunowie vs pojemność autokaru / pojazdu floty.
 * Nie obejmuje pilota/obsługi/kierowcy (te role są tylko w kalkulacji kosztów).
 * Dla floty liczy Vehicle.capacity (pasażerowie), bez crew_seats.
 */
final class EventBusSeatCapacity
{
    public static function exceeds(int $paying, int $gratis, int $capacity): bool
    {
        if ($capacity <= 0) {
            return false;
        }

        return (max(0, $paying) + max(0, $gratis)) > $capacity;
    }

    public static function message(int $paying, int $gratis, int $capacity, ?string $busName = null): ?string
    {
        $paying = max(0, $paying);
        $gratis = max(0, $gratis);
        $capacity = max(0, $capacity);

        if (! self::exceeds($paying, $gratis, $capacity)) {
            return null;
        }

        $total = $paying + $gratis;
        $shortage = $total - $capacity;
        $busLabel = filled($busName) ? " „{$busName}”" : '';
        $opiekunLabel = $gratis === 1 ? 'opiekun' : 'opiekunów';
        $miejscLabel = $shortage === 1 ? 'miejsce' : 'miejsc';

        return sprintf(
            'Grupa (%d uczestników + %d %s = %d) przekracza pojemność autokaru%s (%d miejsc). Brakuje %d %s.',
            $paying,
            $gratis,
            $opiekunLabel,
            $total,
            $busLabel,
            $capacity,
            $shortage,
            $miejscLabel
        );
    }

    /**
     * @param  callable(string): mixed  $get
     */
    public static function warningHtml(callable $get, ?Event $record = null): ?HtmlString
    {
        return self::wrapWarning(self::resolveMessage($get, $record));
    }

    /**
     * Soft warning dla pojazdu floty (Vehicle.capacity — tylko miejsca pasażerskie).
     *
     * @param  callable(string): mixed  $get
     */
    public static function fleetWarningHtml(callable $get, ?Event $record = null): ?HtmlString
    {
        return self::wrapWarning(self::resolveFleetMessage($get, $record));
    }

    /**
     * @param  callable(string): mixed  $get
     */
    public static function resolveMessage(callable $get, ?Event $record = null): ?string
    {
        $busId = (int) ($get('bus_id') ?: ($record?->bus_id ?? 0));
        if ($busId <= 0) {
            return null;
        }

        $bus = null;
        if ($record && (int) ($record->bus_id ?? 0) === $busId && $record->relationLoaded('bus')) {
            $bus = $record->getRelation('bus');
        }
        if (! $bus instanceof Bus || (int) ($bus->id ?? 0) !== $busId || ! array_key_exists('capacity', $bus->getAttributes())) {
            $bus = Bus::query()->find($busId);
        }
        if (! $bus) {
            return null;
        }

        $paying = self::resolvePaying($get, $record);
        $gratis = self::resolveGratis($get, $record, $paying);

        return self::message(
            $paying,
            $gratis,
            (int) ($bus->capacity ?? 0),
            $bus->name
        );
    }

    /**
     * @param  callable(string): mixed  $get
     */
    public static function resolveFleetMessage(callable $get, ?Event $record = null): ?string
    {
        $vehicleId = (int) ($get('main_fleet_vehicle_id') ?: 0);
        if ($vehicleId <= 0) {
            return null;
        }

        $vehicle = Vehicle::query()->find($vehicleId);
        if (! $vehicle) {
            return null;
        }

        $capacity = (int) ($vehicle->capacity ?? 0);
        if ($capacity <= 0) {
            return null;
        }

        $paying = self::resolvePaying($get, $record);
        $gratis = self::resolveGratis($get, $record, $paying);

        $label = filled($vehicle->registration_number)
            ? (string) $vehicle->registration_number
            : $vehicle->displayLabel();

        return self::message($paying, $gratis, $capacity, $label);
    }

    private static function wrapWarning(?string $message): ?HtmlString
    {
        if ($message === null) {
            return null;
        }

        return new HtmlString(
            '<div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-300">'
            .e($message)
            .'</div>'
        );
    }

    /**
     * @param  callable(string): mixed  $get
     */
    private static function resolvePaying(callable $get, ?Event $record): int
    {
        $fromForm = $get('participant_count');
        if ($fromForm !== null && $fromForm !== '') {
            return max(0, (int) $fromForm);
        }

        return max(0, (int) ($record?->participant_count ?? 0));
    }

    /**
     * @param  callable(string): mixed  $get
     */
    private static function resolveGratis(callable $get, ?Event $record, int $paying): int
    {
        $fromForm = $get('gratis_count');
        if ($fromForm !== null && $fromForm !== '') {
            return max(0, (int) $fromForm);
        }

        if ($record) {
            return max(0, (int) $record->resolveGratisCountForParticipantCount($paying > 0 ? $paying : null));
        }

        return 0;
    }
}

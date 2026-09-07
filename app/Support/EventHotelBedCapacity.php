<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Ostrzeżenie UI: zaplanowane łóżka vs potrzeby grupy na noc hotelową.
 *
 * Potrzeby grupy = qty + gratis + pilot/obsługa + kierowcy (jak {@see \App\Services\EventHotelOccupancyService}).
 * Zaplanowane miejsca = suma (people_count × quantity) linii pokoi na stay.
 */
final class EventHotelBedCapacity
{
    public static function exceeds(int $required, int $beds): bool
    {
        if ($required <= 0 || $beds <= 0) {
            return false;
        }

        return $required > $beds;
    }

    public static function shortage(int $required, int $beds): int
    {
        if ($required <= 0 || $beds <= 0) {
            return 0;
        }

        return max(0, $required - $beds);
    }

    public static function message(int $required, int $beds, int $day, ?string $stayLabel = null): ?string
    {
        if (! self::exceeds($required, $beds)) {
            return null;
        }

        $shortage = self::shortage($required, $beds);
        $nocLabel = $day > 0 ? "noc {$day}" : 'tej nocy';
        $hotelSuffix = filled($stayLabel) ? " ({$stayLabel})" : '';
        $miejscLabel = $shortage === 1 ? 'miejsce' : 'miejsc';

        return sprintf(
            'Na %s%s zaplanowano %d miejsc, a grupa potrzebuje %d. Brakuje %d %s.',
            $nocLabel,
            $hotelSuffix,
            $beds,
            $required,
            $shortage,
            $miejscLabel
        );
    }

    /**
     * @param  array{
     *   required_beds_per_night?: int,
     *   stays?: list<array<string, mixed>>
     * }  $occupancy
     * @return array{
     *   required: int,
     *   min_beds: int|null,
     *   has_deficiency: bool,
     *   deficient_stays: list<array{
     *     day: int,
     *     label: string|null,
     *     beds: int,
     *     required: int,
     *     shortage: int,
     *     message: string
     *   }>
     * }
     */
    public static function analyzeOccupancy(array $occupancy): array
    {
        $required = max(0, (int) ($occupancy['required_beds_per_night'] ?? 0));

        return self::analyzeStays($required, $occupancy['stays'] ?? [], static fn (array $stay): int => max(0, (int) ($stay['beds'] ?? 0)));
    }

    /**
     * @param  list<array<string, mixed>>  $stays
     * @param  array<int|string, string|null>  $hotelLabels
     */
    public static function analyzeStayPayloads(
        array $stays,
        int $required,
        ?Collection $hotelRoomsById = null,
        array $hotelLabels = [],
    ): array {
        return self::analyzeStays(
            max(0, $required),
            $stays,
            static fn (array $stay): int => EventHotelPlanFormatting::countPersonSlots($stay, $hotelRoomsById),
            static function (array $stay) use ($hotelLabels): ?string {
                $contractorId = (int) ($stay['contractor_id'] ?? 0);
                if ($contractorId <= 0) {
                    return null;
                }

                $label = $hotelLabels[$contractorId] ?? null;

                return filled($label) ? (string) $label : null;
            },
        );
    }

    /**
     * @param  array{
     *   required: int,
     *   min_beds: int|null,
     *   has_deficiency: bool,
     *   deficient_stays: list<array<string, mixed>>
     * }  $analysis
     */
    public static function warningsHtmlFromAnalysis(array $analysis): ?HtmlString
    {
        $messages = array_values(array_filter(array_map(
            static fn (array $stay): ?string => $stay['message'] ?? null,
            $analysis['deficient_stays'] ?? []
        )));

        if ($messages === []) {
            return null;
        }

        return self::wrapWarnings($messages);
    }

    public static function warningHtml(?string $message): ?HtmlString
    {
        if ($message === null || $message === '') {
            return null;
        }

        return self::wrapWarnings([$message]);
    }

    /**
     * @param  list<string>  $messages
     */
    public static function wrapWarnings(array $messages): ?HtmlString
    {
        $messages = array_values(array_filter($messages, static fn (string $message): bool => $message !== ''));

        if ($messages === []) {
            return null;
        }

        $body = '';
        foreach ($messages as $index => $message) {
            $class = $index === 0 ? 'm-0' : 'm-0 mt-2';
            $body .= '<p class="'.$class.'">'.e($message).'</p>';
        }

        return new HtmlString(
            '<div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-300">'
            .$body
            .'</div>'
        );
    }

    /**
     * @param  list<array<string, mixed>>  $stays
     * @param  callable(array<string, mixed>): int  $bedsResolver
     * @param  (callable(array<string, mixed>): ?string)|null  $labelResolver
     * @return array{
     *   required: int,
     *   min_beds: int|null,
     *   has_deficiency: bool,
     *   deficient_stays: list<array{
     *     day: int,
     *     label: string|null,
     *     beds: int,
     *     required: int,
     *     shortage: int,
     *     message: string
     *   }>
     * }
     */
    private static function analyzeStays(int $required, array $stays, callable $bedsResolver, ?callable $labelResolver = null): array
    {
        $deficient = [];
        $minBeds = null;

        foreach ($stays as $stay) {
            $beds = max(0, (int) $bedsResolver($stay));
            if ($beds <= 0) {
                continue;
            }

            $minBeds = $minBeds === null ? $beds : min($minBeds, $beds);

            if (! self::exceeds($required, $beds)) {
                continue;
            }

            $day = max(0, (int) ($stay['day'] ?? 0));
            $label = $labelResolver !== null
                ? $labelResolver($stay)
                : (filled($stay['label'] ?? null) ? (string) $stay['label'] : null);

            $message = self::message($required, $beds, $day, $label);
            if ($message === null) {
                continue;
            }

            $deficient[] = [
                'day' => $day,
                'label' => $label,
                'beds' => $beds,
                'required' => $required,
                'shortage' => self::shortage($required, $beds),
                'message' => $message,
            ];
        }

        return [
            'required' => $required,
            'min_beds' => $minBeds,
            'has_deficiency' => $deficient !== [],
            'deficient_stays' => $deficient,
        ];
    }
}

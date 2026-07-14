<?php

namespace App\Support;

/**
 * Grupy menu panelu pilota.
 */
final class PilotNavigation
{
    public const GROUP_TRIPS = 'Moje wycieczki';

    public const GROUP_SETTLEMENTS = 'Rozliczenia';

    /** @return array<string, bool> group => collapsed */
    public static function panelGroups(): array
    {
        return [
            self::GROUP_TRIPS => false,
            self::GROUP_SETTLEMENTS => false,
        ];
    }

    public static function groupIcon(string $group): ?string
    {
        return match ($group) {
            self::GROUP_TRIPS => 'heroicon-o-paper-airplane',
            self::GROUP_SETTLEMENTS => 'heroicon-o-wallet',
            default => 'heroicon-o-folder',
        };
    }

    public static function groupCssClass(string $group): string
    {
        return match ($group) {
            self::GROUP_TRIPS => 'sor-nav-group sor-nav-group--pilot-trips',
            self::GROUP_SETTLEMENTS => 'sor-nav-group sor-nav-group--pilot-settlements',
            default => 'sor-nav-group',
        };
    }
}

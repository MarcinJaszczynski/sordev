<?php

namespace App\Support;

/**
 * Grupy menu panelu klienta.
 */
final class ClientNavigation
{
    public const GROUP_TRIPS = 'Moje wycieczki';

    /** @return array<string, bool> group => collapsed */
    public static function panelGroups(): array
    {
        return [
            self::GROUP_TRIPS => false,
        ];
    }

    public static function groupIcon(string $group): ?string
    {
        return match ($group) {
            self::GROUP_TRIPS => 'heroicon-o-map',
            default => 'heroicon-o-folder',
        };
    }

    public static function groupCssClass(string $group): string
    {
        return match ($group) {
            self::GROUP_TRIPS => 'sor-nav-group sor-nav-group--client-trips',
            default => 'sor-nav-group',
        };
    }
}

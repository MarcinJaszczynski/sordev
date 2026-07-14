<?php

namespace App\Support;

/**
 * Uproszczone grupy menu panelu admin (workflow imprezowy na pierwszym planie).
 */
final class FilamentNavigation
{
    /** Imprezy operacyjne, rezerwacje, zadania */
    public const GROUP_EVENTS = 'Obsługa imprez';

    /** Szablony ofert, punkty programu, warianty qty */
    public const GROUP_EVENT_TEMPLATES = 'Szablony imprez';

    /** Wpłaty, faktury, umowy UFG, raporty */
    public const GROUP_FINANCE = 'Finanse';

    /** P&L, statystyki właścicielskie */
    public const GROUP_EXECUTIVE = 'Zarządzanie';

    /** Kontrahenci, kontakty, czat */
    public const GROUP_CONTACTS = 'Kontakty';

    /** Słowniki i konfiguracja operacyjna (zwinięte domyślnie) */
    public const GROUP_DICTIONARIES = 'Słowniki';

    /** Użytkownicy systemu, role, narzędzia IT */
    public const GROUP_SYSTEM = 'System';

    /** @deprecated Use GROUP_EVENTS */
    public const GROUP_OPERATIONS = self::GROUP_EVENTS;

    /** @deprecated Use GROUP_DICTIONARIES */
    public const GROUP_SETTINGS = self::GROUP_DICTIONARIES;

    /** @deprecated Use GROUP_DICTIONARIES */
    public const GROUP_CONFIG = self::GROUP_DICTIONARIES;

    /** @deprecated Use GROUP_EVENTS */
    public const GROUP_TEMPLATES = self::GROUP_EVENTS;

    /** @deprecated Use GROUP_EVENTS */
    public const GROUP_TASKS = self::GROUP_EVENTS;

    /** @deprecated Use GROUP_FINANCE */
    public const GROUP_INVOICES = self::GROUP_FINANCE;

    /** @deprecated Use GROUP_CONTACTS */
    public const GROUP_COMMUNICATION = self::GROUP_CONTACTS;

    /** @deprecated Use GROUP_DICTIONARIES */
    public const GROUP_TOOLS = self::GROUP_DICTIONARIES;

    /** @deprecated Use GROUP_SYSTEM */
    public const GROUP_ARCHIVE = self::GROUP_SYSTEM;

    /** @deprecated Use GROUP_SYSTEM */
    public const GROUP_ADMIN = self::GROUP_SYSTEM;

    /** @return array<string, bool> group => collapsed */
    public static function panelGroups(): array
    {
        return [
            self::GROUP_EVENTS => false,
            self::GROUP_EVENT_TEMPLATES => false,
            self::GROUP_FINANCE => false,
            self::GROUP_EXECUTIVE => true,
            self::GROUP_CONTACTS => true,
            self::GROUP_DICTIONARIES => true,
            self::GROUP_SYSTEM => true,
        ];
    }

    public static function groupIcon(string $group): ?string
    {
        return match ($group) {
            self::GROUP_EVENTS => 'heroicon-o-map',
            self::GROUP_EVENT_TEMPLATES => 'heroicon-o-rectangle-stack',
            self::GROUP_FINANCE => 'heroicon-o-banknotes',
            self::GROUP_EXECUTIVE => 'heroicon-o-chart-bar-square',
            self::GROUP_CONTACTS => 'heroicon-o-users',
            self::GROUP_DICTIONARIES => 'heroicon-o-book-open',
            self::GROUP_SYSTEM => 'heroicon-o-cog-6-tooth',
            default => 'heroicon-o-folder',
        };
    }

    public static function groupCssClass(string $group): string
    {
        return match ($group) {
            self::GROUP_EVENTS => 'sor-nav-group sor-nav-group--events',
            self::GROUP_EVENT_TEMPLATES => 'sor-nav-group sor-nav-group--templates',
            self::GROUP_FINANCE => 'sor-nav-group sor-nav-group--finance',
            self::GROUP_EXECUTIVE => 'sor-nav-group sor-nav-group--executive',
            self::GROUP_CONTACTS => 'sor-nav-group sor-nav-group--contacts',
            self::GROUP_DICTIONARIES => 'sor-nav-group sor-nav-group--dictionaries',
            self::GROUP_SYSTEM => 'sor-nav-group sor-nav-group--system',
            default => 'sor-nav-group',
        };
    }
}

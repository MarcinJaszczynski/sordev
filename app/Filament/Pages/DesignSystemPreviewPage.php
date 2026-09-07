<?php

namespace App\Filament\Pages;

use App\Support\FilamentNavigation;
use Filament\Pages\Page;

class DesignSystemPreviewPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static string $view = 'filament.pages.design-system-preview';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?string $navigationLabel = 'Podgląd UI';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Podgląd systemu projektowego';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin']);
    }

    /** @return list<array{token: string, hex: string, label: string}> */
    public function getColorTokens(): array
    {
        return [
            ['token' => '--sor-brand-primary', 'hex' => '#B45309', 'label' => 'Primary'],
            ['token' => '--sor-brand-secondary', 'hex' => '#1E3A5F', 'label' => 'Secondary'],
            ['token' => '--sor-surface', 'hex' => '#F8FAFC', 'label' => 'Surface'],
            ['token' => '--sor-border', 'hex' => '#E2E8F0', 'label' => 'Border'],
            ['token' => '--sor-success', 'hex' => '#059669', 'label' => 'Success'],
            ['token' => '--sor-warning', 'hex' => '#D97706', 'label' => 'Warning'],
            ['token' => '--sor-danger', 'hex' => '#DC2626', 'label' => 'Danger'],
            ['token' => '--sor-info', 'hex' => '#0284C7', 'label' => 'Info'],
            ['token' => '--sor-module-events', 'hex' => '#2563EB', 'label' => 'Moduł: imprezy'],
            ['token' => '--sor-module-finance', 'hex' => '#059669', 'label' => 'Moduł: finanse'],
        ];
    }

    /** @return list<array{group: string, icon: string, class: string}> */
    public function getNavigationGroups(): array
    {
        return [
            ['group' => FilamentNavigation::GROUP_EVENTS, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_EVENTS), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_EVENTS)],
            ['group' => FilamentNavigation::GROUP_FINANCE, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_FINANCE), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_FINANCE)],
            ['group' => FilamentNavigation::GROUP_EXECUTIVE, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_EXECUTIVE), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_EXECUTIVE)],
            ['group' => FilamentNavigation::GROUP_CONTACTS, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_CONTACTS), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_CONTACTS)],
            ['group' => FilamentNavigation::GROUP_PEOPLE, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_PEOPLE), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_PEOPLE)],
            ['group' => FilamentNavigation::GROUP_DICTIONARIES, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_DICTIONARIES), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_DICTIONARIES)],
            ['group' => FilamentNavigation::GROUP_SYSTEM, 'icon' => FilamentNavigation::groupIcon(FilamentNavigation::GROUP_SYSTEM), 'class' => FilamentNavigation::groupCssClass(FilamentNavigation::GROUP_SYSTEM)],
        ];
    }
}

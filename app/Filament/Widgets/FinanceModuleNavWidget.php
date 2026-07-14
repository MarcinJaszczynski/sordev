<?php

namespace App\Filament\Widgets;

use App\Support\FinanceModuleNavigation;
use Filament\Widgets\Widget;

class FinanceModuleNavWidget extends Widget
{
    protected static string $view = 'filament.widgets.finance-module-nav-widget';

    protected int|string|array $columnSpan = 'full';

    public string $activeTab = 'overview';

    public static function canView(): bool
    {
        return false;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTabs(): array
    {
        return FinanceModuleNavigation::tabs($this->activeTab);
    }
}

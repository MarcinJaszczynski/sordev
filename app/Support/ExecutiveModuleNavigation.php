<?php

namespace App\Support;

use App\Filament\Pages\ExecutiveProfitLossPage;
use App\Filament\Pages\ExecutiveStatisticsPage;

final class ExecutiveModuleNavigation
{
    /** @return array<int, array{key: string, label: string, description: string, url: string, icon: string, badge: ?string, active?: bool}> */
    public static function tabs(?string $active = null): array
    {
        $tabs = [
            [
                'key' => 'profit-loss',
                'label' => 'Zyski i straty',
                'description' => 'Marża i bilans płatności',
                'url' => ExecutiveProfitLossPage::getUrl(),
                'icon' => 'heroicon-o-presentation-chart-line',
                'badge' => null,
            ],
            [
                'key' => 'statistics',
                'label' => 'Statystyki',
                'description' => 'Przekrój imprez i trendów',
                'url' => ExecutiveStatisticsPage::getUrl(),
                'icon' => 'heroicon-o-chart-bar-square',
                'badge' => null,
            ],
        ];

        return WorkflowModuleNavigation::markActive($tabs, $active);
    }
}

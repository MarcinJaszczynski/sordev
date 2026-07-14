<?php

namespace App\Filament\Pilot\Widgets;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Services\PilotAccessService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PilotOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $base = app(PilotAccessService::class)->visibleTripsQuery($user);

        $upcoming = (clone $base)->where('start_date', '>=', now()->startOfDay())->count();
        $needsSettlement = (clone $base)
            ->whereHas('activeSettlement', fn ($q) => $q->whereIn('status', ['draft', 'active']))
            ->count();
        $submitted = (clone $base)
            ->whereHas('activeSettlement', fn ($q) => $q->where('status', 'pilot_settled'))
            ->count();
        $closed = (clone $base)
            ->whereHas('activeSettlement', fn ($q) => $q->where('status', 'closed'))
            ->count();

        return [
            Stat::make('Nadchodzące', (string) $upcoming)
                ->description('Wycieczki od dziś')
                ->url(PilotEventResource::getUrl('index'))
                ->color('primary'),
            Stat::make('Do rozliczenia', (string) $needsSettlement)
                ->description('Wymaga raportu')
                ->url(PilotEventResource::getUrl('index'))
                ->color($needsSettlement > 0 ? 'warning' : 'success'),
            Stat::make('Zgłoszone', (string) $submitted)
                ->description('Czeka na biuro')
                ->color('info'),
            Stat::make('Zamknięte', (string) $closed)
                ->description('Rozliczone przez biuro')
                ->color('gray'),
        ];
    }
}

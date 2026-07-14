<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Filament\Pilot\Widgets\PilotOverviewWidget;
use App\Filament\Pilot\Widgets\PilotTripCalendarWidget;
use App\Filament\Pilot\Widgets\PilotUpcomingTripsWidget;
use App\Http\Middleware\PilotPreviewMiddleware;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class PilotDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Pulpit';

    protected static ?string $title = 'Portal pilota';

    public function getWidgets(): array
    {
        return [
            PilotOverviewWidget::class,
            PilotUpcomingTripsWidget::class,
            PilotTripCalendarWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('all_trips')
                ->label('Wszystkie wycieczki')
                ->url(PilotEventResource::getUrl('index'))
                ->icon('heroicon-o-map'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('pilot')) {
            return true;
        }

        return $user->hasRole(['admin', 'super_admin']) && PilotPreviewMiddleware::isActive();
    }
}

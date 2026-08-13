<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Resources\ClientEventResource;
use App\Http\Middleware\ClientPreviewMiddleware;
use App\Services\ClientAccessService;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class ClientDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Pulpit';

    protected static ?string $title = 'Portal klienta';

    protected static string $view = 'filament.client.pages.client-dashboard';

    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            return true;
        }

        return $user->hasRole(['admin', 'super_admin', 'biuro']) && ClientPreviewMiddleware::isActive();
    }

    /**
     * @return array{trips: \Illuminate\Support\Collection, allTripsUrl: string}
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $trips = $user
            ? app(ClientAccessService::class)
                ->visibleTripsQuery($user)
                ->with(['eventTemplate', 'startPlace'])
                ->orderByDesc('start_date')
                ->limit(12)
                ->get()
            : collect();

        return [
            'trips' => $trips,
            'allTripsUrl' => ClientEventResource::getUrl('index', panel: 'portal'),
        ];
    }
}

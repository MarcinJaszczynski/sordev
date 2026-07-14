<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Resources\ClientEventResource;
use App\Http\Middleware\ClientPreviewMiddleware;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class ClientDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Pulpit';

    protected static ?string $title = 'Portal klienta';

    public function getColumns(): int|string|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('all_trips')
                ->label('Wszystkie wycieczki')
                ->url(ClientEventResource::getUrl('index'))
                ->icon('heroicon-o-map'),
        ];
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
}

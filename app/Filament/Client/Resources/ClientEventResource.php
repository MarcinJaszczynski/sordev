<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\ClientEventResource\Pages;
use App\Models\Event;
use App\Services\ClientAccessService;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ClientEventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Moje wycieczki';

    protected static ?string $modelLabel = 'Wycieczka';

    protected static ?string $pluralModelLabel = 'Moje wycieczki';

    protected static ?string $navigationGroup = \App\Support\ClientNavigation::GROUP_TRIPS;

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return app(ClientAccessService::class)
            ->visibleTripsQuery($user)
            ->with(['eventTemplate', 'startPlace']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            return true;
        }

        return $user->hasRole(['admin', 'super_admin', 'biuro'])
            && \App\Http\Middleware\ClientPreviewMiddleware::isActive();
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('viewClientPortal', $record) ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        // Lista używa custom view z kartami — tabela zostaje tylko jako API zasobu.
        return $table
            ->columns([])
            ->paginated(false);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $detailsVisible = fn (Event $record): bool => Auth::user()?->can('viewClientPortalDetails', $record) ?? false;

        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informacje o wycieczce')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->label('Nazwa'),
                        Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                        Infolists\Components\TextEntry::make('start_date')->label('Data startu')->date('d.m.Y'),
                        Infolists\Components\TextEntry::make('end_date')->label('Data końca')->date('d.m.Y'),
                        Infolists\Components\TextEntry::make('startPlace.name')
                            ->label('Miejsce startu')
                            ->visible($detailsVisible),
                        Infolists\Components\TextEntry::make('client_name')
                            ->label('Organizator / klient')
                            ->visible($detailsVisible),
                        Infolists\Components\TextEntry::make('client_phone')
                            ->label('Telefon biura')
                            ->placeholder('—')
                            ->visible($detailsVisible),
                        Infolists\Components\TextEntry::make('client_email')
                            ->label('E-mail biura')
                            ->placeholder('—')
                            ->visible($detailsVisible),
                    ]),
                Infolists\Components\Section::make('Diety')
                    ->visible(fn (Event $record): bool => $detailsVisible($record) && filled($record->diet_info))
                    ->schema([
                        Infolists\Components\TextEntry::make('diet_info')
                            ->label('Informacje o dietach (organizacyjne)')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Dostęp archiwalny')
                    ->visible(fn (Event $record): bool => ! $detailsVisible($record))
                    ->schema([
                        Infolists\Components\TextEntry::make('archive_notice')
                            ->label('')
                            ->state(fn (Event $record): string => app(ClientAccessService::class)->archiveMessage($record))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientEvents::route('/'),
            'view' => Pages\ViewClientEvent::route('/{record}'),
        ];
    }
}

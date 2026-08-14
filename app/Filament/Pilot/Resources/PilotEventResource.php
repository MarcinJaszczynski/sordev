<?php

namespace App\Filament\Pilot\Resources;

use App\Filament\Pilot\Resources\PilotEventResource\Pages;
use App\Models\Event;
use App\Services\PilotAccessService;
use App\Support\ContractorContactDetails;
use App\Support\PilotNavigation;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PilotEventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Moje wycieczki';

    protected static ?string $modelLabel = 'Wycieczka';

    protected static ?string $pluralModelLabel = 'Moje wycieczki';

    protected static ?string $navigationGroup = PilotNavigation::GROUP_TRIPS;

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return app(PilotAccessService::class)->visibleTripsQuery($user);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('pilot')) {
            return true;
        }

        return $user->hasRole(['admin', 'super_admin', 'biuro'])
            && \App\Http\Middleware\PilotPreviewMiddleware::isActive();
    }

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        // Lista używa custom view z kartami.
        return $table
            ->columns([])
            ->paginated(false);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $pilotDetailsVisible = fn (Event $record): bool => Auth::user()?->can('viewPilotDetails', $record) ?? false;

        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informacje o wycieczce')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->label('Nazwa'),
                        Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                        Infolists\Components\TextEntry::make('start_date')->label('Data startu')->date('d.m.Y'),
                        Infolists\Components\TextEntry::make('end_date')->label('Data końca')->date('d.m.Y'),
                        Infolists\Components\TextEntry::make('participant_count')
                            ->label('Liczba osób')
                            ->formatStateUsing(function ($state, $record): string {
                                $paying = max(0, (int) ($state ?? $record->participant_count ?? 0));
                                $gratis = max(0, (int) $record->resolveGratisCountForParticipantCount($paying ?: null));

                                return $gratis > 0
                                    ? sprintf('%d + %d (płacący + opiekunowie)', $paying, $gratis)
                                    : (string) $paying;
                            })
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('pickup_place_details')
                            ->label('Adres podstawienia autokaru')
                            ->state(function (Event $record): string {
                                $details = Schema::hasColumn('events', 'pickup_place_details')
                                    ? trim(strip_tags((string) ($record->pickup_place_details ?? '')))
                                    : '';

                                if ($details !== '') {
                                    return $details;
                                }

                                $record->loadMissing('startPlace');

                                return (string) ($record->startPlace?->name ?: '—');
                            })
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('substitution_time')
                            ->label('Godzina podstawienia')
                            ->formatStateUsing(fn ($state): string => self::formatClock($state))
                            ->placeholder('—')
                            ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && Schema::hasColumn('events', 'substitution_time')),
                        Infolists\Components\TextEntry::make('departure_time')
                            ->label('Godzina wyjazdu')
                            ->formatStateUsing(fn ($state): string => self::formatClock($state))
                            ->placeholder('—')
                            ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && Schema::hasColumn('events', 'departure_time')),
                        Infolists\Components\TextEntry::make('return_time')
                            ->label('Godzina powrotu')
                            ->formatStateUsing(fn ($state): string => self::formatClock($state))
                            ->placeholder('—')
                            ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && Schema::hasColumn('events', 'return_time')),
                        Infolists\Components\TextEntry::make('assignedUser.name')
                            ->label('Pilot')
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('assignedUser.phone')
                            ->label('Telefon pilota')
                            ->placeholder('—')
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('client_name')
                            ->label('Klient')
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('client_phone')
                            ->label('Telefon klienta')
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('client_email')
                            ->label('E-mail klienta')
                            ->placeholder('—')
                            ->visible($pilotDetailsVisible),
                    ]),
                Infolists\Components\Section::make('Transport')
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible($pilotDetailsVisible)
                    ->schema([
                        Infolists\Components\TextEntry::make('transport_company_name')->label('Firma transportowa'),
                        Infolists\Components\TextEntry::make('transportContractorDetails')
                            ->label('Dane firmy transportowej')
                            ->state(function (Event $record): ?string {
                                $record->loadMissing('transportContractor');

                                return ContractorContactDetails::inlineSummary($record->transportContractor)
                                    ?: null;
                            })
                            ->placeholder('—')
                            ->visible(fn (Event $record): bool => filled($record->transportContractor)),
                        Infolists\Components\TextEntry::make('driver_name')->label('Kierowca'),
                        Infolists\Components\TextEntry::make('driver_phone')->label('Telefon kierowcy'),
                        Infolists\Components\TextEntry::make('vehicle_registration')->label('Rejestracja autokaru'),
                    ]),
                Infolists\Components\Section::make('Diety')
                    ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && filled($record->diet_info))
                    ->schema([
                        Infolists\Components\TextEntry::make('diet_info')
                            ->label('Diety specjalne')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Uwagi dla pilota')
                    ->visible($pilotDetailsVisible)
                    ->schema([
                        Infolists\Components\TextEntry::make('pilot_notes')
                            ->label('Uwagi biura')
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Dostęp archiwalny')
                    ->visible(fn (Event $record): bool => ! ($pilotDetailsVisible)($record))
                    ->schema([
                        Infolists\Components\TextEntry::make('archive_notice')
                            ->label('')
                            ->state(fn (Event $record): string => app(PilotAccessService::class)->archiveMessage($record))
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
            'index' => Pages\ListPilotEvents::route('/'),
            'view' => Pages\ViewPilotEvent::route('/{record}'),
        ];
    }

    private static function formatClock(mixed $state): string
    {
        if (blank($state)) {
            return '—';
        }

        return substr((string) $state, 0, 5);
    }
}

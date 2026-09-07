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

        return app(PilotAccessService::class)->canStaffPreviewPortal($user);
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
                Infolists\Components\Section::make('Podstawienie i wyjazd')
                    ->columns(['default' => 1, 'md' => 3])
                    ->visible($pilotDetailsVisible)
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->columnSpanFull(),
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
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('substitution_time')
                            ->label('Godzina podstawienia')
                            ->formatStateUsing(fn ($state): string => self::formatClock($state))
                            ->placeholder('—')
                            ->visible(fn (): bool => Schema::hasColumn('events', 'substitution_time')),
                        Infolists\Components\TextEntry::make('departure_time')
                            ->label('Godzina wyjazdu')
                            ->formatStateUsing(fn ($state): string => self::formatClock($state))
                            ->placeholder('—')
                            ->visible(fn (): bool => Schema::hasColumn('events', 'departure_time')),
                        Infolists\Components\TextEntry::make('return_time')
                            ->label('Godzina powrotu')
                            ->formatStateUsing(fn ($state): string => self::formatClock($state))
                            ->placeholder('—')
                            ->visible(fn (): bool => Schema::hasColumn('events', 'return_time')),
                        Infolists\Components\TextEntry::make('participant_count')
                            ->label('Liczba osób')
                            ->formatStateUsing(function ($state, $record): string {
                                $paying = max(0, (int) ($state ?? $record->participant_count ?? 0));
                                $gratis = max(0, (int) $record->resolveGratisCountForParticipantCount($paying ?: null));

                                return $gratis > 0
                                    ? sprintf('%d + %d (płacący + opiekunowie)', $paying, $gratis)
                                    : (string) $paying;
                            })
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Trasy przejazdu')
                    ->description('Ramowa trasa autokaru na każdy dzień wycieczki.')
                    ->visible(fn (Event $record): bool => $pilotDetailsVisible($record)
                        && Schema::hasColumn('events', 'program_day_routes'))
                    ->schema(function (Infolists\Components\Section $component): array {
                        $record = $component->getRecord();

                        return self::pilotRouteEntries($record instanceof Event ? $record : null);
                    }),
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
                        Infolists\Components\TextEntry::make('fleet_manufacture_year')
                            ->label('Rocznik')
                            ->state(function (Event $record): ?string {
                                $record->loadMissing(['eventVehicles.vehicle']);

                                return $record->mainFleetVehicle()?->manufactureYearLabel();
                            })
                            ->placeholder('—')
                            ->visible(fn (Event $record): bool => filled($record->mainFleetVehicle()?->manufacture_year)),
                        Infolists\Components\TextEntry::make('fleet_brand_model')
                            ->label('Marka / model')
                            ->state(function (Event $record): ?string {
                                $record->loadMissing(['eventVehicles.vehicle']);
                                $vehicle = $record->mainFleetVehicle();
                                if (! $vehicle) {
                                    return null;
                                }

                                $label = trim(implode(' ', array_filter([(string) $vehicle->brand, (string) $vehicle->model])));

                                return $label !== '' ? $label : null;
                            })
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Hotele')
                    ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && self::pilotHotelLines($record) !== [])
                    ->schema([
                        Infolists\Components\TextEntry::make('pilot_hotel_summary')
                            ->label('')
                            ->state(fn (Event $record): string => implode("\n\n", self::pilotHotelLines($record)))
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
                Infolists\Components\Section::make('Kontakty')
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible($pilotDetailsVisible)
                    ->schema([
                        Infolists\Components\TextEntry::make('client_name')->label('Klient'),
                        Infolists\Components\TextEntry::make('client_phone')->label('Telefon klienta'),
                        Infolists\Components\TextEntry::make('client_email')
                            ->label('E-mail klienta')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('assignedUser.name')->label('Pilot'),
                        Infolists\Components\TextEntry::make('assignedUser.phone')
                            ->label('Telefon pilota')
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Uwagi dla pilota')
                    ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && self::hasPilotFacingNotes($record))
                    ->schema([
                        Infolists\Components\TextEntry::make('pilot_notes')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Diety')
                    ->visible(fn (Event $record): bool => $pilotDetailsVisible($record) && filled($record->diet_info))
                    ->schema([
                        Infolists\Components\TextEntry::make('diet_info')
                            ->label('Diety specjalne')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Dostęp archiwalny')
                    ->visible(fn (Event $record): bool => ! ($pilotDetailsVisible)($record))
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
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

    private static function hasPilotFacingNotes(Event $record): bool
    {
        if (! Schema::hasColumn('events', 'pilot_notes')) {
            return false;
        }

        return trim(strip_tags((string) ($record->pilot_notes ?? ''))) !== '';
    }

    /**
     * @return array<int, Infolists\Components\Component>
     */
    private static function pilotRouteEntries(?Event $record): array
    {
        if (! $record || ! Schema::hasColumn('events', 'program_day_routes')) {
            return [];
        }

        $days = $record->resolveCoreProgramDaysCount();
        $entries = [];

        for ($day = 1; $day <= $days; $day++) {
            $currentDay = $day;
            $date = $record->dateForProgramDay($currentDay)?->format('d.m.Y');

            $entries[] = Infolists\Components\TextEntry::make("program_day_routes.{$currentDay}")
                ->label($date ? "Dzień {$currentDay} ({$date})" : "Dzień {$currentDay}")
                ->state(fn (Event $event): string => $event->programDayRoute($currentDay) ?: '—')
                ->placeholder('—')
                ->columnSpanFull();
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private static function pilotHotelLines(Event $record): array
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return [];
        }

        $record->loadMissing([
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelStays.programPoint.contractor',
            'hotelStays.programPoint.contractorLocation',
        ]);

        $lines = [];

        foreach ($record->hotelStays as $stay) {
            $contractor = $stay->contractor ?? $stay->programPoint?->contractor;
            if (! $contractor) {
                continue;
            }

            $location = $stay->contractorLocation ?? $stay->programPoint?->contractorLocation;
            $meta = ContractorContactDetails::operationalMeta($contractor, $location);
            $name = trim((string) $contractor->name);
            if ($name === '') {
                continue;
            }

            if (filled($meta['branch_name'])) {
                $name .= ' — '.$meta['branch_name'];
            }

            $block = 'Noc '.(int) $stay->day.': '.$name;
            if (filled($meta['address'])) {
                $block .= "\n".$meta['address'];
            }

            $lines[] = $block;
        }

        return $lines;
    }
}

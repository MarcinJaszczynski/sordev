<?php

namespace App\Filament\Pilot\Resources;

use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Pilot\Pages\PilotProgramPage;
use App\Filament\Pilot\Pages\PilotSettlementPage;
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

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->wrap()
                    ->sortable()
                    ->description(function (Event $record): ?string {
                        $parts = [];
                        if ($record->start_date) {
                            $parts[] = $record->start_date->format('d.m.Y')
                                .($record->end_date ? ' – '.$record->end_date->format('d.m.Y') : '');
                        }
                        if (app(PilotAccessService::class)->isArchived($record)) {
                            $parts[] = 'Archiwum — pełny dostęp wygasł';
                        }

                        return $parts !== [] ? implode(' · ', $parts) : null;
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d.m.Y')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Koniec')
                    ->date('d.m.Y')
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Plan osób')
                    ->numeric()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('activeSettlement.status')
                    ->label('Rozliczenie')
                    ->formatStateUsing(fn ($state) => $state ? (\App\Models\EventSettlement::$statuses[$state] ?? $state) : '—')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('trip_phase')
                    ->label('Faza')
                    ->options([
                        'upcoming' => 'Nadchodzące',
                        'in_progress' => 'W trakcie',
                        'past' => 'Zakończone',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value === 'upcoming') {
                            return $query->where('start_date', '>', now());
                        }
                        if ($value === 'in_progress') {
                            return $query->where('start_date', '<=', now())->where('end_date', '>=', now()->startOfDay());
                        }
                        if ($value === 'past') {
                            return $query->where('end_date', '<', now()->startOfDay());
                        }

                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('settlement_status')
                    ->label('Rozliczenie')
                    ->options(\App\Models\EventSettlement::$statuses)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('activeSettlement', fn ($q) => $q->where('status', $data['value']))
                        : $query),
                Tables\Filters\TernaryFilter::make('needs_settlement')
                    ->label('Wymaga rozliczenia')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('activeSettlement', fn ($q) => $q->whereIn('status', ['draft', 'active'])),
                        false: fn (Builder $query) => $query->whereDoesntHave('activeSettlement', fn ($q) => $q->whereIn('status', ['draft', 'active'])),
                    ),
            ])
            ->defaultSort('start_date', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('program')
                    ->label('Program')
                    ->icon('heroicon-o-calendar-days')
                    ->url(fn (Event $record) => PilotProgramPage::urlFor($record))
                    ->visible(fn (Event $record): bool => Auth::user()?->can('viewPilotDetails', $record) ?? false),
                Tables\Actions\Action::make('checklist')
                    ->label('Checklista')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (Event $record) => PilotChecklistPage::urlFor($record))
                    ->visible(fn (Event $record): bool => Auth::user()?->can('view', $record) ?? false),
                Tables\Actions\Action::make('settle')
                    ->label('Rozlicz')
                    ->icon('heroicon-o-calculator')
                    ->url(fn (Event $record) => PilotSettlementPage::settleUrl($record))
                    ->visible(fn (Event $record): bool => Auth::user()?->can('viewPilotDetails', $record) ?? false),
            ]);
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
                            ->label('Planowana liczba osób')
                            ->visible($pilotDetailsVisible),
                        Infolists\Components\TextEntry::make('startPlace.name')
                            ->label('Miejsce startu')
                            ->visible($pilotDetailsVisible),
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
                        Infolists\Components\TextEntry::make('transfer_km')->label('Km transferu'),
                        Infolists\Components\TextEntry::make('program_km')->label('Km programu'),
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
                Infolists\Components\Section::make('Finanse setów')
                    ->visible($pilotDetailsVisible)
                    ->schema([
                        Infolists\Components\View::make('filament.pilot.components.pilot-set-finance-section')
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
}

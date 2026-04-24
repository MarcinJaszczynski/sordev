<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventSettlementResource\Pages;
use App\Filament\Resources\EventSettlementResource\RelationManagers;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventSettlementResource extends Resource
{
    protected static ?string $model = EventSettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Rozliczenia imprez';

    protected static ?string $navigationGroup = 'Finanse';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Rozliczenie';

    protected static ?string $pluralModelLabel = 'Rozliczenia imprez';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe informacje')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Impreza')
                        ->options(fn () => Event::orderByDesc('start_date')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn ($e) => [
                                $e->id => "[{$e->id}] {$e->name} ({$e->start_date?->format('d.m.Y')})",
                            ])
                        )
                        ->searchable()
                        ->required()
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => static::fillEventDefaults($state, $set)),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(EventSettlement::$statuses)
                        ->default('draft')
                        ->required(),

                    Forms\Components\Select::make('pilot_id')
                        ->label('Pilot')
                        ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Forms\Components\RichEditor::make('notes')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Sumy (auto-obliczane)')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('planned_cost_pln')
                        ->label('Koszt planowany (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('actual_cost_pln')
                        ->label('Koszt rzeczywisty (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('participant_due_pln')
                        ->label('Należne od uczestników (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('participant_paid_pln')
                        ->label('Wpłacone przez uczestników (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('pilot_expenses_planned')
                        ->label('Wydatki pilota – plan (PLN)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                            $record
                                ? round((float) $record->costs()->where('paid_by', 'pilot')->whereNotIn('payment_status', ['cancelled'])->sum('planned_amount_pln'), 2)
                                : 0
                        )),

                    Forms\Components\TextInput::make('pilot_expenses_calculated')
                        ->label('Wydatki pilota – gotówka (PLN)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                            $record
                                ? round((float) $record->pilotCashPreparations()->sum('pln_equivalent'), 2)
                                : 0
                        )),
                ]),
        ]);
    }

    protected static function fillEventDefaults($eventId, Forms\Set $set): void
    {
        if (! $eventId) {
            return;
        }
        $event = Event::with('programPoints')->find($eventId);
        if (! $event) {
            return;
        }
        // Jeśli pilot jest przypisany do imprezy
        if ($event->assigned_to) {
            $set('pilot_id', $event->assigned_to);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width(60),

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Impreza')
                    ->searchable()
                    ->wrap()
                    ->url(fn ($record) => $record->event_id ? EventResource::getUrl('edit', ['record' => $record->event_id]) : null)
                    ->description(fn ($record) => $record->event?->start_date?->format('d.m.Y')),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => EventSettlement::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'active',
                        'info' => 'pilot_settled',
                        'success' => 'closed',
                    ]),

                Tables\Columns\TextColumn::make('planned_cost_pln')
                    ->label('Plan (PLN)')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('actual_cost_pln')
                    ->label('Rzeczywiste (PLN)')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_diff')
                    ->label('Różnica')
                    ->state(fn ($record) => $record->actual_cost_pln - $record->planned_cost_pln)
                    ->money('PLN')
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray')),

                Tables\Columns\TextColumn::make('participant_paid_pln')
                    ->label('Wpłacono')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('participant_balance')
                    ->label('Saldo uczestników')
                    ->state(fn ($record) => (float) $record->participant_paid_pln - (float) $record->participant_due_pln)
                    ->money('PLN')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('pilot.name')
                    ->label('Pilot')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(EventSettlement::$statuses),

                Tables\Filters\SelectFilter::make('pilot_id')
                    ->label('Pilot')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open_event')
                    ->label('Impreza')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => $record->event_id ? EventResource::getUrl('edit', ['record' => $record->event_id]) : null)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('import')
                    ->label('Importuj z imprezy')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Importuj koszty z imprezy')
                    ->modalDescription('Importuje wszystkie aktywne punkty programu jako planowane koszty. Istniejące pozycje zostaną pominięte.')
                    ->action(function ($record) {
                        $record->importFromEvent();
                        $record->refresh();
                    })
                    ->visible(fn ($record) => $record->status === 'draft'),

                Tables\Actions\Action::make('recalc_pilot')
                    ->label('Oblicz gotówkę pilota')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->recalculatePilotCash();
                        $record->refresh();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Podsumowanie rozliczenia')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('event.name')
                        ->label('Impreza')
                        ->columnSpan(2),

                    Infolists\Components\TextEntry::make('status_label')
                        ->label('Status')
                        ->badge(),

                    Infolists\Components\TextEntry::make('pilot.name')
                        ->label('Pilot')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('planned_cost_pln')
                        ->label('Koszt planowany')
                        ->money('PLN'),

                    Infolists\Components\TextEntry::make('actual_cost_pln')
                        ->label('Koszt rzeczywisty')
                        ->money('PLN'),

                    Infolists\Components\TextEntry::make('cost_diff')
                        ->label('Różnica kosztów')
                        ->state(fn ($record) => $record->actual_cost_pln - $record->planned_cost_pln)
                        ->money('PLN')
                        ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray')),

                    Infolists\Components\TextEntry::make('participant_balance')
                        ->label('Saldo uczestników')
                        ->state(fn ($record) => $record->participant_paid_pln - $record->participant_due_pln)
                        ->money('PLN')
                        ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                    Infolists\Components\TextEntry::make('pilot_expenses_planned')
                        ->label('Wydatki pilota – plan')
                        ->state(fn ($record) => round((float) $record->costs()->where('paid_by', 'pilot')->whereNotIn('payment_status', ['cancelled'])->sum('planned_amount_pln'), 2))
                        ->money('PLN'),

                    Infolists\Components\TextEntry::make('pilot_expenses_calculated')
                        ->label('Wydatki pilota – gotówka')
                        ->state(fn ($record) => round((float) $record->pilotCashPreparations()->sum('pln_equivalent'), 2))
                        ->money('PLN'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SettlementCostsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\ParticipantPaymentsRelationManager::class,
            RelationManagers\PilotCashRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventSettlements::route('/'),
            'create' => Pages\CreateEventSettlement::route('/create'),
            'edit' => Pages\EditEventSettlement::route('/{record}/edit'),
        ];
    }
}

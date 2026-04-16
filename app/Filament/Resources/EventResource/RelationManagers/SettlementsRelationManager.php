<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Resources\EventSettlementResource;
use App\Models\EventSettlement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SettlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'settlements';
    protected static ?string $title = 'Rozliczenia';
    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(EventSettlement::$statuses)
                ->default('draft')
                ->required(),

            Forms\Components\Select::make('pilot_id')
                ->label('Pilot')
                ->options(fn() => \App\Models\User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable(),

            Forms\Components\RichEditor::make('notes')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->width(60),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn($state) => EventSettlement::$statuses[$state] ?? $state)
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'active',
                        'info'    => 'pilot_settled',
                        'success' => 'closed',
                    ]),

                Tables\Columns\TextColumn::make('planned_cost_pln')
                    ->label('Plan (PLN)')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('actual_cost_pln')
                    ->label('Rzeczywiste (PLN)')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('pilot.name')
                    ->label('Pilot')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->date('d.m.Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nowe rozliczenie')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('import')
                    ->label('Importuj koszty')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->importFromEvent();
                        Notification::make()->success()->title('Zaimportowano koszty')->send();
                    })
                    ->visible(fn($record) => $record->status === 'draft'),

                Tables\Actions\Action::make('open')
                    ->label('Otwórz')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn($record) => EventSettlementResource::getUrl('edit', ['record' => $record])),

                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

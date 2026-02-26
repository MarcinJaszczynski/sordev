<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventStartingPlaceAvailability;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class StartingPlaceAvailabilityRelationManager extends RelationManager
{
    protected static string $relationship = 'startingPlaceAvailabilities';
    protected static ?string $recordTitleAttribute = 'start_place_id';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Select::make('start_place_id')->label('Miejsce startu')->relationship('startPlace','name')->preload()->required(),
            Select::make('end_place_id')->label('Miejsce zakończenia')->relationship('endPlace','name')->preload()->required(),
            Toggle::make('available')->label('Dostępne')->default(true),
            Forms\Components\Textarea::make('note')->label('Notatka')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('startPlace.name')->label('Start'),
            Tables\Columns\TextColumn::make('endPlace.name')->label('Koniec'),
            Tables\Columns\IconColumn::make('available')->label('Dostępne')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}

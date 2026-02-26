<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventQty;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class QtyVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'qtyVariants';
    protected static ?string $recordTitleAttribute = 'qty';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            TextInput::make('qty')->label('Ilość')->numeric()->required(),
            Toggle::make('gratis')->label('Gratis')->default(false),
            Toggle::make('staff')->label('Personel')->default(false),
            Toggle::make('driver')->label('Kierowca')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('qty')->label('Ilość'),
            Tables\Columns\IconColumn::make('gratis')->label('Gratis')->boolean(),
            Tables\Columns\IconColumn::make('staff')->label('Personel')->boolean(),
            Tables\Columns\IconColumn::make('driver')->label('Kierowca')->boolean(),
        ])->filters([])->headerActions([])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}

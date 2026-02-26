<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventDayInsurance;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class DayInsurancesRelationManager extends RelationManager
{
    protected static string $relationship = 'dayInsurances';
    protected static ?string $recordTitleAttribute = 'day';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            TextInput::make('day')->label('Dzień')->numeric()->required(),
            Select::make('insurance_id')->label('Ubezpieczenie')->relationship('insurance','name')->preload()->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('day')->label('Dzień'),
            Tables\Columns\TextColumn::make('insurance.name')->label('Ubezpieczenie'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}

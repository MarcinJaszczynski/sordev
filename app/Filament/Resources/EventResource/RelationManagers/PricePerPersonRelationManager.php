<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventPricePerPerson;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class PricePerPersonRelationManager extends RelationManager
{
    protected static string $relationship = 'pricePerPerson';
    protected static ?string $recordTitleAttribute = 'price_per_person';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            TextInput::make('price_per_person')->label('Cena za osobę')->numeric()->required()->step(0.01),
            TextInput::make('transport_cost')->label('Koszt transportu')->numeric()->step(0.01),
            TextInput::make('price_with_tax')->label('Cena z podatkiem')->numeric()->step(0.01),
            Textarea::make('tax_breakdown')->label('Rozbicie VAT')->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('price_per_person')->label('Cena/os')->money('PLN'),
            Tables\Columns\TextColumn::make('transport_cost')->label('Transport')->money('PLN'),
            Tables\Columns\TextColumn::make('price_with_tax')->label('Cena z VAT')->money('PLN'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}

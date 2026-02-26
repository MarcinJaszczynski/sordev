<?php

namespace App\Filament\Resources\EventTemplateResource\RelationManagers;

use App\Models\EventTemplateQty;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class QtyVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'qtyVariants';
    protected static ?string $recordTitleAttribute = 'qty';
    // Polskie tłumaczenia dla menu, nagłówków, komunikatów, przycisków
    protected static ?string $label = 'Wariant ilości uczestników';
    protected static ?string $pluralLabel = 'Warianty ilości uczestników';
    protected static ?string $navigationLabel = 'Warianty ilości uczestników';
    protected static ?string $navigationGroup = 'Ustawienia';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return 'Warianty ilości uczestników';
    }

    public static function getModelLabel(): string
    {
        return 'wariant ilości uczestników';
    }

    public static function getPluralModelLabel(): string
    {
        return 'warianty ilości uczestników';
    }

    public static function getNavigationLabel(): string
    {
        return 'Warianty ilości uczestników';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ustawienia';
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('qty')
                ->label('Ilość uczestników')
                ->numeric()
                ->minValue(1)
                ->required()
                ->placeholder('Podaj ilość uczestników'),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('qty')
                ->label('Ilość uczestników')
                ->sortable(),
        ])
        ->headerActions([
            Tables\Actions\CreateAction::make()
                ->label('Dodaj wariant'),
        ])
        ->actions([
            Tables\Actions\EditAction::make()->label('Edytuj'),
            Tables\Actions\DeleteAction::make()->label('Usuń'),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make()->label('Usuń zaznaczone'),
        ])
        ->emptyStateHeading('Brak wariantów ilości uczestników')
        ->emptyStateDescription('Dodaj pierwszy wariant, aby rozpocząć kalkulacje dla różnych grup.');
    }
}

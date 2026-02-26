<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventPriceDescriptionResource\Pages;
use App\Models\EventPriceDescription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;

class EventPriceDescriptionResource extends Resource
{
    protected static ?string $model = EventPriceDescription::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-euro';
    protected static ?string $navigationGroup = 'Ustawienia';
    protected static ?string $label = 'Opis ceny imprezy';
    protected static ?string $pluralLabel = 'Opisy cen imprez';
    protected static ?int $navigationSort = 120;
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa')
                    ->required(),
                Forms\Components\RichEditor::make('description')
                    ->label('Opis (możesz używać <b>, <ul>, <li> itd.)')
                    ->required()
                    ->toolbarButtons([
                        'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa'),
                Tables\Columns\TextColumn::make('description')->label('Opis')->limit(80)->html(),
            ])
            ->filters([
                // Można dodać filtry po nazwie
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventPriceDescriptions::route('/'),
            'create' => Pages\CreateEventPriceDescription::route('/create'),
            'edit' => Pages\EditEventPriceDescription::route('/{record}/edit'),
        ];
    }
}

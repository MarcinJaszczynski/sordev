<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportTypeResource\Pages;
use App\Models\TransportType;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportTypeResource extends Resource
{
    protected static ?string $model = TransportType::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Rodzaj transportu';

    protected static ?string $modelLabel = 'rodzaj transportu';

    protected static ?string $pluralModelLabel = 'rodzaje transportu';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SETTINGS;

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa')
                    ->required()
                    ->maxLength(255),
                \FilamentTiptapEditor\TiptapEditor::make('description')
                    ->label('Opis')
                    ->maxLength(1000),
                Forms\Components\FileUpload::make('icon_path')
                    ->label('Ikona (PNG/SVG)')
                    ->image()
                    ->directory('transport-types/icons')
                    ->disk('public')
                    ->imageEditor()
                    ->maxSize(1024)
                    ->helperText('Kwadratowa ikona używana na liście ofert (np. 64x64 px).')
                    ->visibility('public'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon_path')
                    ->label('Ikona')
                    ->disk('public')
                    ->height(40)
                    ->width(40),
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Opis')->html(false)->limit(80),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportTypes::route('/'),
            'create' => Pages\CreateTransportType::route('/create'),
            'edit' => Pages\EditTransportType::route('/{record}/edit'),
        ];
    }
}

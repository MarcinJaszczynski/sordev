<?php

namespace App\Filament\Resources;

use App\Models\Place;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Resources\PlaceResource\Pages;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;
protected static ?string $navigationIcon = 'heroicon-o-map';
protected static ?string $navigationLabel = 'Miejsca';
protected static ?string $navigationGroup = 'Ustawienia ogólne';
protected static ?string $pluralLabel = 'Miejsca';
protected static ?string $label = 'Miejsce';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $coords = \App\Services\PlaceGeocodeService::getCoordinates($state);
                            if ($coords) {
                                $set('latitude', $coords['lat']);
                                $set('longitude', $coords['lon']);
                            }
                        }
                    }),
                Textarea::make('description'),
                TagsInput::make('tags'),
                Toggle::make('starting_place')->label('Miejsce początkowe')->default(true),
                TextInput::make('latitude')
                    ->label('Szerokość geograficzna')
                    ->numeric()
                    ->nullable()
                    ->suffixAction(
                        \Filament\Forms\Components\Actions\Action::make('fetch_latlon')
                            ->label('Pobierz z API')
                            ->icon('heroicon-o-arrow-path')
                            ->action(function ($get, $set) {
                                $name = $get('name');
                                if ($name) {
                                    $coords = \App\Services\PlaceGeocodeService::getCoordinates($name);
                                    if ($coords) {
                                        $set('latitude', $coords['lat']);
                                        $set('longitude', $coords['lon']);
                                    }
                                }
                            })
                    ),
                TextInput::make('longitude')
                    ->label('Długość geograficzna')
                    ->numeric()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('description')->limit(50),
                TextColumn::make('tags')->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state),
                BooleanColumn::make('starting_place')->label('Początkowe'),
                TextColumn::make('latitude')->label('Szerokość')->sortable(),
                TextColumn::make('longitude')->label('Długość')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->filters([
                // Możesz dodać filtry według potrzeb
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaces::route('/'),
            'create' => Pages\CreatePlace::route('/create'),
            'edit' => Pages\EditPlace::route('/{record}/edit'),
        ];
    }
}

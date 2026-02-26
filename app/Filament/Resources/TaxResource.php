<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxResource\Pages;
use App\Filament\Resources\TaxResource\RelationManagers;
use App\Models\Tax;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxResource extends Resource
{
    protected static ?string $model = Tax::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Podatki';
    protected static ?string $modelLabel = 'Podatek';
    protected static ?string $pluralModelLabel = 'Podatki';
    protected static ?string $navigationGroup = 'Konfiguracja';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa podatku')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('np. VAT, Podatek miejski'),
                        Forms\Components\TextInput::make('percentage')
                            ->label('Procent podatku')
                            ->required()
                            ->numeric()
                            ->suffix('%')
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->placeholder('np. 23.00'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true)
                            ->helperText('Czy podatek jest aktywny i może być używany w kalkulacjach'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Podstawa naliczania')
                    ->description('Wybierz od czego ma być naliczany podatek')
                    ->schema([
                        Forms\Components\Toggle::make('apply_to_base')
                            ->label('Naliczaj od sumy bez narzutu')
                            ->helperText('Podatek będzie naliczony od podstawowej kwoty (bez PLN)'),
                        Forms\Components\Toggle::make('apply_to_markup')
                            ->label('Naliczaj od narzutu')
                            ->helperText('Podatek będzie naliczony od kwoty narzutu'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Dodatkowe informacje')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->placeholder('Dodatkowe informacje o podatku...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('percentage')
                    ->label('Procent')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('apply_to_base')
                    ->label('Od sumy bez narzutu')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('apply_to_markup')
                    ->label('Od narzutu')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle') 
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Tylko aktywne')
                    ->falseLabel('Tylko nieaktywne')
                    ->native(false),
                Tables\Filters\TernaryFilter::make('apply_to_base')
                    ->label('Od sumy bez narzutu')
                    ->boolean()
                    ->trueLabel('Tak')
                    ->falseLabel('Nie')
                    ->native(false),
                Tables\Filters\TernaryFilter::make('apply_to_markup')
                    ->label('Od narzutu')
                    ->boolean()
                    ->trueLabel('Tak')
                    ->falseLabel('Nie')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListTaxes::route('/'),
            'create' => Pages\CreateTax::route('/create'),
            'edit' => Pages\EditTax::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresUfgContractsTable;
use App\Filament\Resources\TfgDictionaryResource\Pages;
use App\Models\TfgDictionaryItem;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TfgDictionaryResource extends Resource
{
    use RequiresUfgContractsTable;

    protected static ?string $model = TfgDictionaryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Słowniki TFG';

    protected static ?string $modelLabel = 'Pozycja słownika';

    protected static ?string $pluralModelLabel = 'Słowniki TFG';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('Typ słownika')
                ->options([
                    TfgDictionaryItem::TYPE_SUBJECT => 'Przedmiot umowy',
                    TfgDictionaryItem::TYPE_PAYMENT_METHOD => 'Forma wpłaty',
                    TfgDictionaryItem::TYPE_TRANSPORT => 'Transport',
                    TfgDictionaryItem::TYPE_COUNTRY => 'Kraj',
                    TfgDictionaryItem::TYPE_SCOPE => 'Zakres lokalizacji',
                    TfgDictionaryItem::TYPE_OPERATION => 'Operacja TFG',
                    TfgDictionaryItem::TYPE_CORRECTION_REASON => 'Powód korekty',
                ])
                ->required(),
            Forms\Components\TextInput::make('code')->label('Kod')->required()->maxLength(50),
            Forms\Components\TextInput::make('label')->label('Etykieta')->required(),
            Forms\Components\Toggle::make('is_active')->label('Aktywny')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('Kolejność')->numeric()->default(0),
            Forms\Components\KeyValue::make('meta')->label('Meta (np. requires_icao)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Typ')->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kod')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('label')->label('Etykieta')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywny')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Kolejność')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Typ')->options([
                    TfgDictionaryItem::TYPE_SUBJECT => 'Przedmiot',
                    TfgDictionaryItem::TYPE_PAYMENT_METHOD => 'Forma wpłaty',
                    TfgDictionaryItem::TYPE_TRANSPORT => 'Transport',
                    TfgDictionaryItem::TYPE_COUNTRY => 'Kraj',
                    TfgDictionaryItem::TYPE_SCOPE => 'Zakres',
                    TfgDictionaryItem::TYPE_OPERATION => 'Operacja',
                    TfgDictionaryItem::TYPE_CORRECTION_REASON => 'Powód korekty',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTfgDictionaryItems::route('/'),
            'create' => Pages\CreateTfgDictionaryItem::route('/create'),
            'edit' => Pages\EditTfgDictionaryItem::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyRateSnapshotResource\Pages;
use App\Models\Currency;
use App\Models\CurrencyRateSnapshot;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyRateSnapshotResource extends Resource
{
    protected static ?string $model = CurrencyRateSnapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationLabel = 'Kursy walut';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_DICTIONARIES;

    protected static ?int $navigationSort = 80;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $modelLabel = 'Kurs waluty';

    protected static ?string $pluralModelLabel = 'Kursy walut';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('currency_id')
                    ->label('Waluta')
                    ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\DatePicker::make('rate_date')
                    ->label('Data kursu')
                    ->required()
                    ->default(today()),

                Forms\Components\TextInput::make('rate')
                    ->label('Kurs (domyślny/sprzedaży)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('purchase_rate')
                    ->label('Kurs zakupu')
                    ->numeric()
                    ->nullable(),

                Forms\Components\TextInput::make('sale_rate')
                    ->label('Kurs sprzedaży banku')
                    ->numeric()
                    ->nullable(),

                Forms\Components\Select::make('source')
                    ->label('Źródło')
                    ->options([
                        'NBP' => 'NBP',
                        'manual' => 'Ręczny',
                        'bank' => 'Bank',
                        'other' => 'Inne',
                    ])
                    ->default('manual')
                    ->nullable(),

                \FilamentTiptapEditor\TiptapEditor::make('notes')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('currency.name')
                    ->label('Waluta')
                    ->sortable()
                    ->searchable()
                    ->description(fn ($r) => $r->currency?->symbol),

                Tables\Columns\TextColumn::make('rate_date')
                    ->label('Data')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Kurs')
                    ->numeric(4),

                Tables\Columns\TextColumn::make('purchase_rate')
                    ->label('Zakupu')
                    ->numeric(4)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sale_rate')
                    ->label('Sprzedaży')
                    ->numeric(4)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('source')
                    ->label('Źródło')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Uwagi')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency_id')
                    ->label('Waluta')
                    ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Źródło')
                    ->options(['NBP' => 'NBP', 'manual' => 'Ręczny', 'bank' => 'Bank', 'other' => 'Inne']),
            ])
            ->defaultSort('rate_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencyRateSnapshots::route('/'),
            'create' => Pages\CreateCurrencyRateSnapshot::route('/create'),
            'edit' => Pages\EditCurrencyRateSnapshot::route('/{record}/edit'),
        ];
    }
}

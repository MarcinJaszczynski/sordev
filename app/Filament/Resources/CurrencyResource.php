<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament dla modelu Currency.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z walutami.
 */
class CurrencyResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<Currency>
     */
    protected static ?string $model = Currency::class;

    // Ikona i etykiety nawigacji w panelu
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Waluty';
    protected static ?string $navigationGroup = 'Ustawienia kalkulacji';
    protected static ?string $pluralLabel = 'Waluty';
    protected static ?string $singularLabel = 'Waluta';

    /**
     * Definicja formularza do edycji/dodawania waluty
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa')
                ->required(),
            Forms\Components\TextInput::make('symbol')
                ->label('Symbol')
                ->required(),
            Forms\Components\TextInput::make('exchange_rate')
                ->label('Kurs wymiany')
                ->numeric()
                ->default(1)
                ->required(),
        ]);
    }

    /**
     * Definicja tabeli walut w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa'),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Symbol'),
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Kurs wymiany'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Ostatnia zmiana kursu')
                    ->dateTime(),
            ])
            ->filters([
                // Brak filtrów
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    /**
     * Relacje powiązane z walutą (brak w tym przypadku)
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canViewAny(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view currency')) {
            return true;
        }
        return false;
    }
}

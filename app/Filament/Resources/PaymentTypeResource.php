<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTypeResource\Pages;
use App\Models\PaymentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Resource Filament dla modelu PaymentType.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z typami płatności.
 */
class PaymentTypeResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<PaymentType>
     */    protected static ?string $model = PaymentType::class;
    protected static ?string $navigationGroup = 'Ustawienia kalkulacji';
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Typy płatności';
    protected static ?string $pluralModelLabel = 'Typy płatności';
    protected static ?string $modelLabel = 'Typ płatności';

    /**
     * Definicja formularza do edycji/dodawania typu płatności
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa')
                ->required(),
            Forms\Components\RichEditor::make('description')
                ->label('Opis')
                ->nullable(),
        ]);
    }

    /**
     * Definicja tabeli typów płatności w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                TextColumn::make('description')->label('Opis')->html(false)->limit(50),
                TextColumn::make('created_at')->label('Utworzono')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTypes::route('/'),
            'edit' => Pages\EditPaymentType::route('/{record}/edit'),
        ];
    }

    /**
     * Uprawnienia do tworzenia typu płatności
     */
    public static function canCreate(): bool
    {
        return true;
    }
}

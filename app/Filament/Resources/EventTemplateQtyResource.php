<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventTemplateQtyResource\Pages;
use App\Models\EventTemplateQty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource Filament dla modelu EventTemplateQty.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z wariantami ilości uczestników.
 */
class EventTemplateQtyResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<EventTemplateQty>
     */
    protected static ?string $model = EventTemplateQty::class;

    // Ikona i etykiety nawigacji w panelu
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Szablony';
    protected static ?string $navigationLabel = 'Warianty ilości';
    protected static ?int $navigationSort = 30;
    protected static ?string $modelLabel = 'wariant ilości uczestników';
    protected static ?string $pluralModelLabel = 'warianty ilości uczestników';

    /**
     * Definicja formularza do edycji/dodawania wariantu ilości uczestników
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('qty')
                ->required()
                ->numeric(),
            Forms\Components\TextInput::make('gratis')
                ->label('Gratis (opieka)')
                ->numeric()
                ->default(fn($record) => $record?->gratis ?? null)
                ->helperText('Domyślnie: zaokrąglone w górę qty/15')
                ->required(),
            Forms\Components\TextInput::make('staff')
                ->label('Obsługa')
                ->numeric()
                ->default(1)
                ->required(),
            Forms\Components\TextInput::make('driver')
                ->label('Kierowcy')
                ->numeric()
                ->default(1)
                ->required(),
        ]);
    }

    /**
     * Definicja tabeli wariantów ilości uczestników w panelu
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('qty')
                ->label('Ilość uczestników')
                ->sortable(),
            Tables\Columns\TextColumn::make('gratis')
                ->label('Gratis (opieka)')
                ->sortable(),
            Tables\Columns\TextColumn::make('staff')
                ->label('Obsługa')
                ->sortable(),
            Tables\Columns\TextColumn::make('driver')
                ->label('Kierowcy')
                ->sortable(),
        ])
        // Brak filtrów z koszem, bo model nie ma SoftDeletes
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    /**
     * Relacje powiązane z wariantem ilości uczestników (brak w tym przypadku)
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
            'index' => Pages\ListEventTemplateQties::route('/'),
            'edit' => Pages\EditEventTemplateQty::route('/{record}/edit'),
        ];
    }

    /**
     * Możliwość nadpisania zapytania Eloquent (tu domyślne)
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canViewAny(): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view event_template_qty')) {
            return true;
        }
        return false;
    }
}

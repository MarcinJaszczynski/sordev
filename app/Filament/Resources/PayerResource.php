<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayerResource\Pages;
use App\Models\Payer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Resource Filament dla modelu Payer.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z płatnikami.
 */
class PayerResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<Payer>
     */
    protected static ?string $model = Payer::class;

    protected static ?string $navigationGroup = 'Ustawienia kalkulacji';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Płatnicy';

    protected static ?string $modelLabel = 'Płatnik';

    protected static ?string $pluralModelLabel = 'Płatnicy';

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canViewAny(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view payer')) {
            return true;
        }

        return false;
    }

    /**
     * Uprawnienia do tworzenia, edycji i usuwania
     */
    public static function canCreate(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && ($user->roles->contains('name', 'admin') || $user->roles->flatMap->permissions->contains('name', 'create payer'))) {
            return true;
        }

        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && ($user->roles->contains('name', 'admin') || $user->roles->flatMap->permissions->contains('name', 'edit payer'))) {
            return true;
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && ($user->roles->contains('name', 'admin') || $user->roles->flatMap->permissions->contains('name', 'delete payer'))) {
            return true;
        }

        return false;
    }

    /**
     * Definicja formularza do edycji/dodawania płatnika
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa płatnika')
                ->required()
                ->columnSpanFull(),
            Forms\Components\RichEditor::make('description')
                ->nullable()
                ->columnSpanFull(),
        ])->columns(1);
    }

    /**
     * Definicja tabeli płatników w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                TextColumn::make('description')->label('Opis')->limit(50)->toggleable(),
                TextColumn::make('created_at')->label('Utworzono')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayers::route('/'),
            'edit' => Pages\EditPayer::route('/{record}/edit'),
        ];
    }
}

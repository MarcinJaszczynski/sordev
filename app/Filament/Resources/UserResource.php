<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament dla modelu User.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z użytkownikami.
 */
class UserResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<User>
     */
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Użytkownicy';
    protected static ?string $navigationGroup = 'Admin';
    protected static ?string $modelLabel = 'użytkownik';
    protected static ?string $pluralModelLabel = 'użytkownicy';

    /**
     * Zwraca etykietę pojedynczą modelu
     */
    public static function getModelLabel(): string
    {
        return 'użytkownik';
    }

    /**
     * Zwraca etykietę mnogą modelu
     */
    public static function getPluralModelLabel(): string
    {
        return 'użytkownicy';
    }

    /**
     * Definicja formularza do edycji/dodawania użytkownika
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Imię i nazwisko')
                ->required(),
            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->required(),
            Forms\Components\TextInput::make('password')
                ->label('Hasło')
                ->password()
                ->dehydrateStateUsing(fn($state) => !empty($state) ? bcrypt($state) : null)
                ->required(fn($context) => $context === 'create')
                ->maxLength(255)
                ->nullable(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Aktywny',
                    'inactive' => 'Nieaktywny',
                ])
                ->required(),
            Forms\Components\Select::make('roles')
                ->label('Role')
                ->multiple()
                ->relationship('roles', 'name')
                ->preload()
                ->helperText('Wybierz role dla użytkownika'),
            Forms\Components\Select::make('permissions')
                ->label('Uprawnienia')
                ->multiple()
                ->relationship('permissions', 'name')
                ->preload()
                ->helperText('Możesz nadać indywidualne uprawnienia użytkownikowi'),
        ]);
    }

    /**
     * Definicja tabeli użytkowników w panelu
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Imię i nazwisko')->searchable(),
            Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->formatStateUsing(fn($state) => $state === 'active' ? 'Aktywny' : 'Nieaktywny'),
        ])
        ->actions([
            Tables\Actions\EditAction::make()->label('Edytuj'),
            Tables\Actions\DeleteAction::make()->label('Usuń'),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make()->label('Usuń zaznaczone'),
        ]);
    }

    /**
     * Relacje powiązane z użytkownikiem (brak w tym przypadku)
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
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

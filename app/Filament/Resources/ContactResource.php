<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource Filament dla modelu Contact.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z kontaktami.
 */
class ContactResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<Contact>
     */
    protected static ?string $model = Contact::class;

    // Ikona i etykiety nawigacji w panelu
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Kontakty';
    protected static ?string $navigationGroup = 'Kontakty';
    protected static ?string $modelLabel = 'kontakt';
    protected static ?string $pluralModelLabel = 'kontakty';

    /**
     * Zwraca etykietę pojedynczą modelu
     */
    public static function getModelLabel(): string
    {
        return 'kontakt';
    }

    /**
     * Zwraca etykietę mnogą modelu
     */
    public static function getPluralModelLabel(): string
    {
        return 'kontakty';
    }

    /**
     * Definicja formularza do edycji/dodawania kontaktu
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('first_name')
                ->label('Imię')
                ->required(),
            Forms\Components\TextInput::make('last_name')
                ->label('Nazwisko')
                ->required(),
            Forms\Components\TextInput::make('phone')
                ->label('Telefon'),
            Forms\Components\TextInput::make('email')
                ->label('Email'),
            Forms\Components\Textarea::make('notes')
                ->label('Uwagi'),
        ]);
    }

    /**
     * Definicja tabeli kontaktów w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Imię')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nazwisko')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Relacje powiązane z kontaktem (brak w tym przypadku)
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
            'index' => Pages\ListContacts::route('/'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view contact')) {
            return true;
        }
        return false;
    }
}

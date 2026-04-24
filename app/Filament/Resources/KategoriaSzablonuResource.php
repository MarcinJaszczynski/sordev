<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KategoriaSzablonuResource\Pages;
use App\Models\KategoriaSzablonu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament dla modelu KategoriaSzablonu.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z kategoriami szablonów.
 */
class KategoriaSzablonuResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<KategoriaSzablonu>
     */
    protected static ?string $model = KategoriaSzablonu::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Kategorie szablonów';

    protected static ?string $navigationGroup = 'Szablony imprez';

    protected static ?int $navigationSort = 45;

    protected static ?string $modelLabel = 'kategoria szablonu';

    protected static ?string $pluralModelLabel = 'kategorie szablonów';

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canViewAny(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view kategoria_szablonu')) {
            return true;
        }

        return false;
    }

    /**
     * Uprawnienia do tworzenia nowych rekordów
     */
    public static function canCreate(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'create kategoria_szablonu')) {
            return true;
        }

        return false;
    }

    /**
     * Definicja formularza do edycji/dodawania kategorii szablonu
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane kategorii')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nazwa')
                        ->label('Nazwa kategorii')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('parent_id')
                        ->label('Kategoria nadrzędna')
                        ->relationship('parent', 'nazwa')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('opis')
                        ->nullable()
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('uwagi')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Definicja tabeli kategorii szablonów w panelu
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nazwa')->label('Nazwa')->sortable(),
            Tables\Columns\TextColumn::make('parent.nazwa')->label('Kategoria nadrzędna')->sortable(),
        ])
            ->defaultSort('nazwa', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Kategoria nadrzędna')
                    ->relationship('parent', 'nazwa')
                    ->searchable()
                    ->preload(),
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
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKategoriaSzablonu::route('/'),
            'create' => Pages\CreateKategoriaSzablonu::route('/create'),
            'edit' => Pages\EditKategoriaSzablonu::route('/{record}/edit'),
        ];
    }
}

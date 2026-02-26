<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TodoStatusResource\Pages;
use App\Models\TodoStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Resource Filament dla modelu TodoStatus.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane ze statusami zadań.
 */
class TodoStatusResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<TodoStatus>
     */
    protected static ?string $model = TodoStatus::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationLabel = 'Statusy zadań';
    protected static ?string $navigationGroup = 'Ustawienia ogólne';
    protected static ?string $modelLabel = 'status zadania';
    protected static ?string $pluralModelLabel = 'statusy zadań';

    /**
     * Zwraca etykietę pojedynczą modelu
     */
    public static function getModelLabel(): string
    {
        return 'status zadania';
    }

    /**
     * Zwraca etykietę mnogą modelu
     */
    public static function getPluralModelLabel(): string
    {
        return 'statusy zadań';
    }

    /**
     * Definicja formularza do edycji/dodawania statusu zadania
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\ColorPicker::make('color'),
                Forms\Components\ColorPicker::make('bgcolor'),
            ]);
    }

    /**
     * Definicja tabeli statusów zadań w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable(),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Kolor'),
                Tables\Columns\ColorColumn::make('bgcolor')
                    ->label('Kolor tła'),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Usunięto')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
            ]);
    }

    /**
     * Relacje powiązane ze statusem zadania (brak w tym przypadku)
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
            'index' => Pages\ListTodoStatuses::route('/'),
            'edit' => Pages\EditTodoStatus::route('/{record}/edit'),
        ];
    }

    /**
     * Nadpisanie domyślnego zapytania Eloquent (wyłączenie globalnego scope SoftDeletes)
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

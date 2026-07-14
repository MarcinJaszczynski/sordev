<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Resource Filament dla modelu Role.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z rolami i uprawnieniami.
 */
class RoleResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<Role>
     */
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Role i uprawnienia';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?string $modelLabel = 'rola';

    protected static ?string $pluralModelLabel = 'role';

    /**
     * Definicja formularza do edycji/dodawania roli
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa roli')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('permissions')
                ->label('Uprawnienia')
                ->multiple()
                ->relationship('permissions', 'name')
                ->preload()
                ->helperText('Wybierz uprawnienia dla tej roli'),
        ]);
    }

    /**
     * Definicja tabeli ról w panelu
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nazwa roli')->searchable(),
            Tables\Columns\TextColumn::make('permissions_count')
                ->counts('permissions')
                ->label('Liczba uprawnień'),
        ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Dodaj rolę'),
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
            'index' => Pages\ListRoles::route('/'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view role')) {
            return true;
        }

        return false;
    }
}

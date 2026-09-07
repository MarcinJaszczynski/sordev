<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PhoneInput;
use App\Filament\Resources\StaffUserResource\Pages;
use App\Models\User;
use App\Support\FilamentNavigation;
use App\Support\UserRoleManagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Konta biura / adminów (panel /admin) — bez PESEL imprezowego.
 */
class StaffUserResource extends Resource
{
    /**
     * @var class-string<User>
     */
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Użytkownicy';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_PEOPLE;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'staff-users';

    protected static ?string $modelLabel = 'użytkownik';

    protected static ?string $pluralModelLabel = 'użytkownicy';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return UserRoleManagement::canManageRolesAndPermissions(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return UserRoleManagement::scopeStaffUsers(parent::getEloquentQuery());
    }

    public static function getModelLabel(): string
    {
        return 'użytkownik';
    }

    public static function getPluralModelLabel(): string
    {
        return 'użytkownicy';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane konta')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Imię i nazwisko')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->required(),
                    PhoneInput::make('phone')
                        ->label('Telefon'),
                    Forms\Components\TextInput::make('password')
                        ->label('Hasło')
                        ->password()
                        ->required(fn ($context) => $context === 'create')
                        ->maxLength(255)
                        ->nullable()
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    Forms\Components\Select::make('status')
                        ->label('Status konta')
                        ->options([
                            'active' => 'Aktywny',
                            'inactive' => 'Nieaktywny',
                        ])
                        ->default(UserRoleManagement::DEFAULT_STATUS)
                        ->required()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Uprawnienia i dostęp')
                ->description('Role biura i administratorów. Bez ról pilot / client_* — te konta są w osobnych listach.')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Role')
                        ->multiple()
                        ->relationship(
                            'roles',
                            'name',
                            fn (Builder $query) => $query->whereIn('name', UserRoleManagement::staffRoleNames()),
                        )
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('permissions')
                        ->label('Indywidualne uprawnienia')
                        ->multiple()
                        ->relationship('permissions', 'name')
                        ->preload()
                        ->helperText('Opcjonalne uprawnienia poza rolami.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Imię i nazwisko')->searchable(),
            Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
            Tables\Columns\TextColumn::make('phone')->label('Telefon')->placeholder('—'),
            Tables\Columns\TextColumn::make('roles.name')->label('Role')->badge(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktywny' : 'Nieaktywny'),
        ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edytuj'),
                Tables\Actions\DeleteAction::make()->label('Usuń'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Usuń zaznaczone'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffUsers::route('/'),
            'edit' => Pages\EditStaffUser::route('/{record}/edit'),
        ];
    }
}

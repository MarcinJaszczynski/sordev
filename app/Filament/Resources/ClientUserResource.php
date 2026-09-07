<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PhoneInput;
use App\Filament\Resources\ClientUserResource\Pages;
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
use Illuminate\Support\Facades\Schema;

/**
 * Konta portalu klienta (/portal) — role client_participant / client_guardian.
 */
class ClientUserResource extends Resource
{
    /**
     * @var class-string<User>
     */
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Uczestnicy';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_PEOPLE;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'portal-users';

    protected static ?string $modelLabel = 'uczestnik';

    protected static ?string $pluralModelLabel = 'uczestnicy';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return UserRoleManagement::canManageOperationalPeople(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny()
            && $record instanceof User
            && UserRoleManagement::isClientPortalUser($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = UserRoleManagement::scopeClientPortalMembers(parent::getEloquentQuery());

        if (Schema::hasTable('event_portal_accesses')) {
            $query->withCount([
                'portalAccesses as portal_accesses_count' => function (Builder $accessQuery): void {
                    if (Schema::hasColumn('event_portal_accesses', 'revoked_at')) {
                        $accessQuery->whereNull('revoked_at');
                    }
                },
            ]);
        }

        return $query;
    }

    public static function getModelLabel(): string
    {
        return 'uczestnik';
    }

    public static function getPluralModelLabel(): string
    {
        return 'uczestnicy';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane konta portalu')
                ->description('Dostępy do konkretnych imprez nadajesz w imprezie (portal klienta). Tu zarządzasz samym kontem.')
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
                        ->required(),
                    Forms\Components\Select::make('roles')
                        ->label('Rola portalu')
                        ->multiple()
                        ->relationship(
                            'roles',
                            'name',
                            fn (Builder $query) => $query->whereIn('name', UserRoleManagement::clientRoleNames()),
                        )
                        ->preload()
                        ->required()
                        ->default(UserRoleManagement::defaultClientRoleIds())
                        ->helperText('client_participant = uczestnik, client_guardian = opiekun.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Imię i nazwisko')->searchable(),
            Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
            Tables\Columns\TextColumn::make('phone')->label('Telefon')->placeholder('—'),
            Tables\Columns\TextColumn::make('roles.name')
                ->label('Rola')
                ->badge(),
            Tables\Columns\TextColumn::make('portal_accesses_count')
                ->label('Imprezy (dostęp)')
                ->placeholder('0')
                ->sortable(),
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
            'index' => Pages\ListClientUsers::route('/'),
            'edit' => Pages\EditClientUser::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PhoneInput;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\FilamentNavigation;
use App\Support\PilotIdentityValidation;
use App\Support\UserRoleManagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource Filament dla modelu User.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z użytkownikami.
 */
class UserResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<User>
     */
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Zespół (piloci)';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'użytkownik';

    protected static ?string $pluralModelLabel = 'użytkownicy';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        $details = [];

        if ($record->email) {
            $details['Email'] = (string) $record->email;
        }

        if ($record->phone) {
            $details['Telefon'] = (string) $record->phone;
        }

        return $details;
    }

    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        if (UserRoleManagement::canManageRolesAndPermissions(auth()->user())) {
            return true;
        }

        return $record instanceof User && UserRoleManagement::isPilotOnlyUser($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && parent::canDelete($record);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! UserRoleManagement::canManageRolesAndPermissions(auth()->user())) {
            return UserRoleManagement::scopePilotTeamMembers($query);
        }

        return $query;
    }

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
                        ->helperText('Hasło zapisze się na koncie. E-mail z danymi logowania wyślesz ręcznie przyciskiem «Wyślij dane logowania».'),
                    Forms\Components\Select::make('status')
                        ->label('Status konta')
                        ->options([
                            'active' => 'Aktywny',
                            'inactive' => 'Nieaktywny',
                        ])
                        ->default(UserRoleManagement::DEFAULT_STATUS)
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('pilot_panel_access_sent_info')
                        ->label('Ostatnie powiadomienie o dostępie do panelu')
                        ->content(function (?User $record): string {
                            if (! $record?->pilot_panel_access_sent_at) {
                                return 'Nie wysłano — użyj przycisku «Wyślij dane logowania» u góry formularza.';
                            }

                            $by = $record->pilotPanelAccessSentByUser?->name ?? '—';

                            return $record->pilot_panel_access_sent_at->format('d.m.Y H:i').' · '.$by;
                        })
                        ->visible(fn (?User $record): bool => $record?->hasRole('pilot') ?? false)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Dane pilota')
                ->description('Domyślnie każdy nowy użytkownik w tej sekcji jest pilotem.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Data urodzenia')
                        ->displayFormat('d.m.Y')
                        ->native(false)
                        ->nullable(),
                    Forms\Components\TextInput::make('pesel')
                        ->label('PESEL')
                        ->maxLength(11)
                        ->nullable()
                        ->rules(PilotIdentityValidation::optionalPeselRules())
                        ->helperText('Opcjonalnie — 11 cyfr.'),
                ]),

            Forms\Components\Section::make('Uprawnienia i dostęp')
                ->columns(1)
                ->visible(fn (): bool => UserRoleManagement::canManageRolesAndPermissions(auth()->user()))
                ->description('Role inne niż pilot oraz indywidualne uprawnienia mogą nadawać wyłącznie administratorzy.')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Role użytkownika')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->preload()
                        ->default(UserRoleManagement::defaultPilotRoleIds())
                        ->helperText('Domyślnie: pilot. Inne role tylko dla administratorów.'),
                    Forms\Components\Select::make('permissions')
                        ->label('Indywidualne uprawnienia')
                        ->multiple()
                        ->relationship('permissions', 'name')
                        ->preload()
                        ->helperText('Opcjonalne uprawnienia poza rolami — tylko dla administratorów.'),
                ]),
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
            Tables\Columns\TextColumn::make('phone')->label('Telefon')->placeholder('—')->copyable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktywny' : 'Nieaktywny'),
            Tables\Columns\TextColumn::make('pilot_panel_access_sent_at')
                ->label('Dostęp — e-mail')
                ->dateTime('d.m.Y H:i')
                ->placeholder('Nie wysłano')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('roles.name')
                ->label('Role')
                ->badge()
                ->visible(fn (): bool => UserRoleManagement::canManageRolesAndPermissions(auth()->user())),
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

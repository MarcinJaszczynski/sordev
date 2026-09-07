<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PhoneInput;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\PilotContractorAssignmentService;
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
 * Konta pilotów (panel /pilot) — PESEL kanonicznie na Contractor, kopia na User.
 */
class UserResource extends Resource
{
    /**
     * @var class-string<User>
     */
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Piloci';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_PEOPLE;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pilots';

    protected static ?string $modelLabel = 'pilot';

    protected static ?string $pluralModelLabel = 'piloci';

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
        return UserRoleManagement::scopePilotTeamMembers(parent::getEloquentQuery());
    }

    public static function getModelLabel(): string
    {
        return 'pilot';
    }

    public static function getPluralModelLabel(): string
    {
        return 'piloci';
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
                        ->required()
                        ->helperText('Ten sam e-mail co na karcie kontrahenta-pilota wiąże konto z PESEL.'),
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
                        ->visible(fn (?User $record): bool => $record !== null)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Dane osobowe pilota')
                ->description('Źródło kanoniczne: karta kontrahenta typu „pilot” z tym samym e-mailem. Bez karty zapis idzie tylko na konto.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Placeholder::make('linked_contractor_info')
                        ->label('Powiązany kontrahent')
                        ->content(function (?User $record): string {
                            if (! $record) {
                                return 'Po zapisaniu — jeśli istnieje kontrahent-pilot z tym e-mailem — PESEL trafi na jego kartę.';
                            }

                            $label = app(PilotContractorAssignmentService::class)
                                ->demographicsFormStateForPortalUser($record)['contractor_label'] ?? null;

                            return filled($label)
                                ? (string) $label.' — PESEL zapisze się na karcie kontrahenta i skopiuje na konto.'
                                : 'Brak kontrahenta-pilota z tym e-mailem — PESEL tylko na koncie. Utwórz kartę w Kontrahentach, aby ujednolicić dane.';
                        })
                        ->columnSpanFull(),
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Imię i nazwisko')->searchable(),
            Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
            Tables\Columns\TextColumn::make('phone')->label('Telefon')->placeholder('—')->copyable(),
            Tables\Columns\TextColumn::make('pesel')->label('PESEL')->placeholder('—')->toggleable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktywny' : 'Nieaktywny'),
            Tables\Columns\TextColumn::make('pilot_panel_access_sent_at')
                ->label('Dostęp — e-mail')
                ->dateTime('d.m.Y H:i')
                ->placeholder('Nie wysłano')
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Edytuj'),
                Tables\Actions\DeleteAction::make()->label('Usuń'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Usuń zaznaczone'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

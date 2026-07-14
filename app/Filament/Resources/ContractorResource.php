<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PhoneInput;
use App\Filament\Forms\ContractorLocationFields;
use App\Filament\Resources\ContractorResource\Pages;
use App\Filament\Resources\ContractorResource\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\ContractorResource\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\ContractorResource\RelationManagers\VendorInvoicesRelationManager;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Support\ContractorContactDetails;
use App\Support\FilamentNavigation;
use App\Support\PilotIdentityValidation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource Filament dla modelu Contractor.
 * Definiuje formularz, tabelę, relacje, uprawnienia i strony powiązane z kontrahentami.
 */
class ContractorResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<Contractor>
     */
    protected static ?string $model = Contractor::class;

    // Ikona i etykiety nawigacji w panelu
    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Kontrahenci';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONTACTS;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kontrahent';

    protected static ?string $pluralModelLabel = 'kontrahenci';

    /**
     * Zwraca etykietę pojedynczą modelu
     */
    public static function getModelLabel(): string
    {
        return 'kontrahent';
    }

    /**
     * Zwraca etykietę mnogą modelu
     */
    public static function getPluralModelLabel(): string
    {
        return 'kontrahenci';
    }

    /**
     * Definicja formularza do edycji/dodawania kontrahenta
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane podstawowe')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa kontrahenta')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Aktywny',
                            'inactive' => 'Nieaktywny',
                        ])
                        ->default('active')
                        ->required(),
                    Forms\Components\Select::make('types')
                        ->label('Typy kontrahenta')
                        ->multiple()
                        ->relationship('types', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nazwa typu')
                                ->required(),
                        ]),
                ]),

            Forms\Components\Section::make('Dane kontaktowe')
                ->columns(2)
                ->schema([
                    PhoneInput::make('phone')
                        ->label('Telefon'),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('nip')
                        ->label('NIP')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('www')
                        ->label('Strona WWW')
                        ->url()
                        ->maxLength(255),
                ]),

            Forms\Components\Section::make('Osoba kontaktowa')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('firstname')
                        ->label('Imię')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('surname')
                        ->label('Nazwisko')
                        ->maxLength(100),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Data urodzenia (pilot)')
                        ->displayFormat('d.m.Y')
                        ->native(false)
                        ->nullable()
                        ->visible(fn (Get $get): bool => static::formTypesIncludePilot($get('types'))),
                    Forms\Components\TextInput::make('pesel')
                        ->label('PESEL (pilot)')
                        ->maxLength(11)
                        ->nullable()
                        ->rules(PilotIdentityValidation::optionalPeselRules())
                        ->visible(fn (Get $get): bool => static::formTypesIncludePilot($get('types')))
                        ->helperText('Widoczne tylko, gdy w typach wybrano „pilot”.'),
                ]),

            Forms\Components\Section::make('Adres rozliczeniowy / siedziba')
                ->description(fn (Get $get): string => (bool) $get('uses_business_locations')
                    ? 'Adres do faktur. Poniżej dodaj oddziały operacyjne z adresami podjazdu.'
                    : 'Adres do faktur i rozliczeń. Włącz „Wiele miejsc prowadzenia”, aby dodać oddziały operacyjne.')
                ->columns(4)
                ->schema([
                    Forms\Components\Toggle::make('uses_business_locations')
                        ->label('Wiele miejsc prowadzenia działalności')
                        ->helperText('Włącz dla sieci hoteli, restauracji itp. Po zapisaniu kontrahenta dodasz oddziały w sekcji poniżej.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull()
                        ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('contractors', 'uses_business_locations')),
                    Forms\Components\TextInput::make('street')
                        ->label('Ulica')
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('house_number')
                        ->label('Nr domu')
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('postal_code')
                        ->label('Kod pocztowy')
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('city')
                        ->label('Miejscowość')
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('region')
                        ->label('Region / województwo')
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('country')
                        ->label('Kraj')
                        ->default('Polska')
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('locations_create_hint')
                        ->label('Miejsca prowadzenia')
                        ->content('Zapisz kontrahenta (przycisk u góry), a następnie wróć do edycji — pod adresem rozliczeniowym pojawi się lista oddziałów do dodania.')
                        ->columnSpanFull()
                        ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                            && (bool) $get('uses_business_locations')
                            && \Illuminate\Support\Facades\Schema::hasTable('contractor_locations')),

                    Forms\Components\Repeater::make('locations')
                        ->label('Miejsca prowadzenia działalności')
                        ->relationship()
                        ->schema(ContractorLocationFields::schema())
                        ->columns(4)
                        ->columnSpanFull()
                        ->visible(fn (Get $get, string $operation): bool => $operation === 'edit'
                            && (bool) $get('uses_business_locations')
                            && \Illuminate\Support\Facades\Schema::hasTable('contractor_locations'))
                        ->addActionLabel('Dodaj oddział')
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                            ? (string) $state['name']
                            : 'Nowy oddział'),
                ]),

            Forms\Components\Section::make('Opis i uwagi')
                ->columns(1)
                ->collapsed()
                ->schema([
                    \FilamentTiptapEditor\TiptapEditor::make('description')
                        ->label('Opis')
                        ->columnSpanFull(),
                    \FilamentTiptapEditor\TiptapEditor::make('office_notes')
                        ->label('Uwagi dla biura')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Karteczki')
                ->description('Operacyjne notatki zespołu — stos z historią, edycja tylko własnej najnowszej.')
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->schema([
                    Forms\Components\View::make('filament.components.sticky-notes-contractor-section')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['contacts', 'types', 'locations']);
    }

    /**
     * Definicja tabeli kontrahentów w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa kontrahenta')
                    ->searchable()
                    ->description(fn ($record) => trim(($record->firstname ?? '').' '.($record->surname ?? '')) ?: null),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),
                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->label('Adres')
                    ->searchable(['city', 'street', 'postal_code'])
                    ->formatStateUsing(fn (Contractor $record): string => ContractorContactDetails::formatAddress($record) ?? '—')
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktywny' : 'Nieaktywny')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('office_notes')
                    ->label('Uwagi dla biura')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contacts')
                    ->label('Kontakty')
                    ->formatStateUsing(function ($state, Contractor $record): string {
                        return $record->contacts
                            ->map(function ($contact): string {
                                $details = collect([$contact->phone, $contact->email])
                                    ->filter()
                                    ->implode(' · ');

                                return $details !== ''
                                    ? $contact->displayName().' · '.$details
                                    : $contact->displayName();
                            })
                            ->join('; ');
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktywny',
                        'inactive' => 'Nieaktywny',
                    ]),
                Tables\Filters\Filter::make('city')
                    ->label('Miejscowość')
                    ->form([
                        Forms\Components\TextInput::make('value')->label('Miejscowość'),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->where('city', 'like', "%{$v}%"))),
                Tables\Filters\TrashedFilter::make()
                    ->label('Kosz'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
            ]);
    }

    /**
     * Relacje powiązane z kontrahentem (np. kontakty)
     */
    public static function getRelations(): array
    {
        $relations = [
            ContactsRelationManager::class,
            LocationsRelationManager::class,
            TasksRelationManager::class,
        ];

        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_invoices')) {
            $relations[] = VendorInvoicesRelationManager::class;
        }

        return $relations;
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractors::route('/'),
            'create' => Pages\CreateContractor::route('/create'),
            'edit' => Pages\EditContractor::route('/{record}/edit'),
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view contractor')) {
            return true;
        }

        return false;
    }

    /**
     * Czy w stanie formularza (pole types) wybrano typ pilota.
     *
     * @param  array<int|string>|null  $types
     */
    public static function formTypesIncludePilot(mixed $types): bool
    {
        $pilotId = ContractorType::pilotTypeId();
        if ($pilotId === null) {
            return false;
        }

        $ids = is_array($types) ? $types : [];

        return in_array((string) $pilotId, array_map('strval', $ids), true);
    }
}

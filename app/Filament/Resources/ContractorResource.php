<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractorResource\Pages;
use App\Filament\Resources\ContractorResource\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Contractor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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

    protected static ?string $navigationGroup = 'Kontakty';

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
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nazwa typu')
                                ->required(),
                        ]),
                ]),

            Forms\Components\Section::make('Dane kontaktowe')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(50),
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
                ]),

            Forms\Components\Section::make('Adres')
                ->columns(4)
                ->schema([
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
                ]),

            Forms\Components\Section::make('Opis i uwagi')
                ->columns(1)
                ->collapsed()
                ->schema([
                    Forms\Components\RichEditor::make('description')
                        ->label('Opis')
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('office_notes')
                        ->label('Uwagi dla biura')
                        ->columnSpanFull(),
                ]),
        ]);
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
                    ->label('Miejscowość')
                    ->searchable()
                    ->description(fn ($record) => implode(' ', array_filter([$record->postal_code, $record->street, $record->house_number]))),
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
                    ->formatStateUsing(fn ($state, $record) => $record->contacts->map(fn ($contact) => $contact->first_name.' '.$contact->last_name)->join(', ')
                    )
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
        return [
            ContactsRelationManager::class,
            TasksRelationManager::class,
        ];
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractors::route('/'),
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
}

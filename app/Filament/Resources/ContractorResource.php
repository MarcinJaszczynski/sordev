<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractorResource\Pages;
use App\Filament\Resources\ContractorResource\RelationManagers\ContactsRelationManager;
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
            Forms\Components\TextInput::make('name')
                ->label('Nazwa kontrahenta')
                ->required(),
            Forms\Components\TextInput::make('street')
                ->label('Ulica'),
            Forms\Components\TextInput::make('house_number')
                ->label('Numer domu'),
            Forms\Components\TextInput::make('city')
                ->label('Miejscowość'),
            Forms\Components\TextInput::make('postal_code')
                ->label('Kod pocztowy'),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Aktywny',
                    'inactive' => 'Nieaktywny',
                ])
                ->default('active')
                ->required(),
            Forms\Components\Textarea::make('office_notes')
                ->label('Uwagi dla biura')
                ->rows(3),
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('street')
                    ->label('Ulica'),
                Tables\Columns\TextColumn::make('house_number')
                    ->label('Numer domu'),
                Tables\Columns\TextColumn::make('city')
                    ->label('Miejscowość'),
                Tables\Columns\TextColumn::make('postal_code')
                    ->label('Kod pocztowy'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktywny' : 'Nieaktywny')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ]),
                Tables\Columns\TextColumn::make('office_notes')
                    ->label('Uwagi dla biura')
                    ->limit(50),
                Tables\Columns\TextColumn::make('contacts')
                    ->label('Kontakty')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->contacts->map(fn ($contact) => $contact->first_name . ' ' . $contact->last_name)->join(', ')
                    )
                    ->toggleable(),
                Tables\Columns\TextColumn::make('types')
                    ->label('Typy')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->types->pluck('name')->join(', ')
                    )
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktywny',
                        'inactive' => 'Nieaktywny',
                    ]),
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

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\ContractTemplate;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource Filament dla modelu ContractTemplate.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z szablonami umów.
 */
class ContractTemplateResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<ContractTemplate>
     */    protected static ?string $model = ContractTemplate::class;

    // Ikona i etykieta nawigacji w panelu
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Szablony umów';
    protected static ?string $navigationGroup = 'Ustawienia ogólne';

    /**
     * Definicja formularza do edycji/dodawania szablonu umowy
     */
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa szablonu')
                ->required(),
            Forms\Components\Textarea::make('content')
                ->label('Treść szablonu')
                ->rows(16)
                ->required()
                ->helperText('Dostępne znaczniki: [NUMER_UMOWY], [DATA_UMOWY], [TYP_UMOWY], [NAZWA_IMPREZY], [DATA_START], [DATA_KONIEC], [KWOTA], [WALUTA], [ZAMAWIAJACY_IMIE_NAZWISKO], [ZAMAWIAJACY_INSTYTUCJA], [ZAMAWIAJACY_ADRES], [ZAMAWIAJACY_EMAIL], [ZAMAWIAJACY_TELEFON], [OPIEKUN], [UCZESTNIK], [PODOPIECZNY], [DATA_URODZENIA], [DODATKOWE_UBEZPIECZENIE], [LINK_UMOWY].'),
        ]);
    }

    /**
     * Definicja tabeli szablonów umów w panelu
     */
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa'),
                Tables\Columns\TextColumn::make('updated_at')->label('Ostatnia edycja')->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractTemplates::route('/'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canEdit(Model $record): bool
    {
        if (blank($record->getRouteKey())) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function canViewAny(): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view contracttemplate')) {
            return true;
        }
        return false;
    }
}

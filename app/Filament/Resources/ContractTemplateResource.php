<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\ContractTemplate;
use App\Services\ContractAttachmentCatalogService;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource Filament dla modelu ContractTemplate.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z szablonami umów.
 */
class ContractTemplateResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<ContractTemplate>
     */
    protected static ?string $model = ContractTemplate::class;

    // Ikona i etykieta nawigacji w panelu
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Szablony umów';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

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
            Forms\Components\CheckboxList::make('default_attachments')
                ->label('Domyślne załączniki dla tego szablonu')
                ->options(fn (): array => app(ContractAttachmentCatalogService::class)->getOptions())
                ->columns(1)
                ->helperText('Po wybraniu tego szablonu przy tworzeniu umowy te pliki będą domyślnie zaznaczone. Puste pole oznacza użycie ustawień globalnych.'),
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
                Tables\Columns\TextColumn::make('default_attachments')
                    ->label('Domyślne załączniki')
                    ->formatStateUsing(fn (?array $state): string => filled($state) ? (string) count($state) : 'Globalne')
                    ->badge()
                    ->color(fn (?array $state): string => filled($state) ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('updated_at')->label('Ostatnia edycja')->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('global_attachment_defaults')
                    ->label('Domyślne załączniki globalne')
                    ->icon('heroicon-o-paper-clip')
                    ->form([
                        Forms\Components\CheckboxList::make('default_attachments')
                            ->label('Załączniki zaznaczane przy nowej umowie')
                            ->options(fn (): array => app(ContractAttachmentCatalogService::class)->getOptions())
                            ->columns(1)
                            ->helperText('Te pliki będą domyślnie zaznaczone, gdy szablon nie ma własnej listy załączników.'),
                    ])
                    ->fillForm(fn (): array => [
                        'default_attachments' => app(ContractAttachmentCatalogService::class)->resolveDefaultSelectedPaths(),
                    ])
                    ->action(function (array $data): void {
                        app(ContractAttachmentCatalogService::class)->saveGlobalDefaults(
                            array_values((array) ($data['default_attachments'] ?? [])),
                        );

                        Notification::make()
                            ->title('Zapisano domyślne załączniki globalne')
                            ->success()
                            ->send();
                    }),
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

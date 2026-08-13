<?php

namespace App\Filament\Resources;

use App\Filament\Forms\ContractTemplateFormFields;
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
     * @var class-string<ContractTemplate>
     */
    protected static ?string $model = ContractTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Szablony umów';

    protected static ?string $modelLabel = 'szablon umowy';

    protected static ?string $pluralModelLabel = 'szablony umów';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema(ContractTemplateFormFields::schema());
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => 'v'.(int) $state),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean(),
                Tables\Columns\TextColumn::make('applies_to')
                    ->label('Typy')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state) || $state === []) {
                            return 'Wszystkie';
                        }

                        return collect($state)
                            ->map(fn ($key) => ContractTemplate::$appliesToOptions[$key] ?? $key)
                            ->implode(', ');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('custom_placeholders')
                    ->label('Pola własne')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }

                        return (string) count($state);
                    })
                    ->badge()
                    ->color(fn ($state): string => (is_array($state) && $state !== []) ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('default_attachments')
                    ->label('Domyślne załączniki')
                    ->formatStateUsing(fn (?array $state): string => filled($state) ? (string) count($state) : 'Globalne')
                    ->badge()
                    ->color(fn (?array $state): string => filled($state) ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('updated_at')->label('Ostatnia edycja')->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->trueLabel('Tylko aktywne')
                    ->falseLabel('Tylko nieaktywne')
                    ->placeholder('Wszystkie'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('new_version')
                    ->label('Nowa wersja')
                    ->icon('heroicon-o-document-duplicate')
                    ->form([
                        Forms\Components\Textarea::make('version_notes')
                            ->label('Notatka do nowej wersji')
                            ->rows(3),
                    ])
                    ->action(function (ContractTemplate $record, array $data) {
                        $clone = $record->createNewVersion($data['version_notes'] ?? null);

                        Notification::make()
                            ->title('Utworzono wersję v'.$clone->version)
                            ->success()
                            ->send();

                        return redirect(static::getUrl('edit', ['record' => $clone]));
                    }),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractTemplates::route('/'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }

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

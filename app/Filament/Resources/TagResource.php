<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Enums\Status;
use App\Enums\Visibility;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament dla modelu Tag.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z tagami.
 */
class TagResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<Tag>
     */
    protected static ?string $model = Tag::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Tagi';
    protected static ?string $navigationGroup = 'Ustawienia ogólne';
    protected static ?string $modelLabel = 'tag';
    protected static ?string $pluralModelLabel = 'tagi';

    /**
     * Zwraca etykietę pojedynczą modelu
     */
    public static function getModelLabel(): string
    {
        return 'tag';
    }

    /**
     * Zwraca etykietę mnogą modelu
     */
    public static function getPluralModelLabel(): string
    {
        return 'tagi';
    }

    /**
     * Definicja formularza do edycji/dodawania tagu
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Opis')
                    ->rows(3),
                Forms\Components\Select::make('visibility')
                    ->label('Widoczność')
                    ->options(array_combine(
                        array_map(fn ($item) => $item->value, Visibility::cases()),
                        array_map(fn ($item) => $item->label(), Visibility::cases())
                    ))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(array_combine(
                        array_map(fn ($item) => $item->value, Status::cases()),
                        array_map(fn ($item) => $item->label(), Status::cases())
                    ))
                    ->required(),
            ]);
    }

    /**
     * Definicja tabeli tagów w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tag $record): string => $record->description ?? ''),
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('visibility')
                    ->label('Widoczność')
                    ->searchable()
                    ->sortable()
                    ->colors([
                        'success' => 'public',
                        'warning' => 'internal',
                        'danger' => 'private',
                    ])
                    ->formatStateUsing(fn (Visibility $state): string => $state->label()),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->sortable()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'draft',
                    ])
                    ->formatStateUsing(fn (Status $state): string => $state->label()),
                Tables\Columns\TextColumn::make('event_template_program_points_count')
                    ->label('Użycia w punktach')
                    ->counts('eventTemplateProgramPoints')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Widoczność')
                    ->options(array_combine(
                        array_map(fn ($item) => $item->value, Visibility::cases()),
                        array_map(fn ($item) => $item->label(), Visibility::cases())
                    )),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(array_combine(
                        array_map(fn ($item) => $item->value, Status::cases()),
                        array_map(fn ($item) => $item->label(), Status::cases())
                    )),
                Tables\Filters\Filter::make('has_description')
                    ->label('Z opisem')
                    ->query(fn ($query) => $query->whereNotNull('description')->where('description', '!=', '')),
                Tables\Filters\Filter::make('without_description')
                    ->label('Bez opisu')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereNull('description')->orWhere('description', '');
                    })),
                Tables\Filters\Filter::make('unused')
                    ->label('Nieużywane')
                    ->query(fn ($query) => $query->doesntHave('eventTemplateProgramPoints')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplikuj')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Tag $record) {
                        $newTag = $record->replicate();
                        $newTag->name = $record->name . ' (kopia)';
                        $newTag->save();
                        
                        return redirect()->to(TagResource::getUrl('edit', ['record' => $newTag]));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Duplikuj tag')
                    ->modalDescription('Czy na pewno chcesz zduplikować ten tag?'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('change_status')
                        ->label('Zmień status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Nowy status')
                                ->options(array_combine(
                                    array_map(fn ($item) => $item->value, Status::cases()),
                                    array_map(fn ($item) => $item->label(), Status::cases())
                                ))
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('change_visibility')
                        ->label('Zmień widoczność')
                        ->icon('heroicon-o-eye')
                        ->form([
                            Forms\Components\Select::make('visibility')
                                ->label('Nowa widoczność')
                                ->options(array_combine(
                                    array_map(fn ($item) => $item->value, Visibility::cases()),
                                    array_map(fn ($item) => $item->label(), Visibility::cases())
                                ))
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['visibility' => $data['visibility']]);
                            });
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj pierwszy tag'),
            ])
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->striped();
    }

    /**
     * Relacje powiązane z tagiem (brak w tym przypadku)
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}

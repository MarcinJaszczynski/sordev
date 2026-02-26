<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventTemplateProgramPointResource\Pages;
use App\Models\EventTemplateProgramPoint;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Media;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\View as ViewComponent;

/**
 * Resource Filament dla modelu EventTemplateProgramPoint.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z punktami programu szablonu wydarzenia.
 */
class EventTemplateProgramPointResource extends Resource
{
    /**
     * Powiązany model Eloquent
     * @var class-string<EventTemplateProgramPoint>
     */
    protected static ?string $model = EventTemplateProgramPoint::class;

    // Ikona i etykiety nawigacji w panelu
    protected static ?string $navigationGroup = 'Szablony';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Punkty programu';
    protected static ?int $navigationSort = 20;

    /**
     * Definicja formularza do edycji/dodawania punktu programu
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                // Sekcja podstawowych informacji
                Forms\Components\Section::make('Podstawowe informacje')
                    ->description('Główne dane punktu programu')
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa punktu programu')
                            ->placeholder('Wpisz nazwę punktu programu')
                            ->hint('Podaj unikalną i zrozumiałą nazwę punktu programu widoczną dla użytkowników.')
                            ->required()
                            ->columnSpanFull(),
                        
                        Forms\Components\RichEditor::make('description')
                            ->label('Opis punktu programu')
                            ->placeholder('Opisz szczegóły punktu programu, np. przebieg, atrakcje, ważne informacje...')
                            ->hint('Opis widoczny dla uczestników i organizatorów. Możesz używać pogrubień, list, linków.')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                            ->nullable()
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('duration_hours')
                            ->label('Czas trwania (godziny)')
                            ->placeholder('np. 2')
                            ->hint('Podaj liczbę pełnych godzin trwania punktu programu.')
                            ->numeric()
                            ->required(),
                        
                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('Czas trwania (minuty)')
                            ->placeholder('np. 30')
                            ->hint('Podaj dodatkowe minuty (0-59).')
                            ->numeric()
                            ->required(),
                    ]),

                // Sekcja uwag organizacyjnych
                Forms\Components\Section::make('Uwagi organizacyjne')
                    ->description('Informacje dla biura i pilotów')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Forms\Components\RichEditor::make('office_notes')
                            ->label('Uwagi dla biura')
                            ->placeholder('Wpisz uwagi organizacyjne, np. wymagania, kontakty, szczegóły logistyczne...')
                            ->hint('Tylko dla pracowników biura. Nie widoczne dla uczestników.')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                        
                        Forms\Components\RichEditor::make('pilot_notes')
                            ->label('Uwagi dla pilota')
                            ->placeholder('Wskazówki dla pilota/opiekuna grupy, np. na co zwrócić uwagę, co przekazać uczestnikom...')
                            ->hint('Tylko dla pilota/opiekuna. Nie widoczne dla uczestników.')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                    ]),

                // Sekcja zdjęć
                Forms\Components\Section::make('Zdjęcia i galeria')
                    ->description('Materiały wizualne punktu programu')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Zdjęcie wyróżniające')
                            ->image()
                            ->disk('public')
                            ->directory('program-points')
                            ->visibility('public')
                            ->previewable()
                            ->downloadable()
                            ->live()
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->preserveFilenames()
                            ->nullable()
                            ->default(fn($record) => is_string($record?->featured_image) ? $record->featured_image : null),
                        FormActions::make([
                            FormAction::make('choose_featured_from_media')
                                ->label('Wybierz z biblioteki')
                                ->icon('heroicon-o-photo')
                                ->modalHeading('Wybierz zdjęcie wyróżniające')
                                ->form([
                                    Select::make('folders')
                                        ->label('Foldery')
                                        ->multiple()
                                        ->native(false)
                                        ->options(function () {
                                            $dirs = Media::images()->pluck('path')
                                                ->map(fn($p) => \Illuminate\Support\Str::before($p, '/'))
                                                ->filter(fn($d) => !empty($d))
                                                ->unique()
                                                ->sort()
                                                ->values();
                                            return $dirs->combine($dirs)->all();
                                        })
                                        ->placeholder('Wszystkie')
                                        ->live(),
                                    Forms\Components\TextInput::make('grid_page')
                                        ->default(1)
                                        ->live()
                                        ->extraAttributes(['id' => 'pp-featured-grid-page'])
                                        ->extraInputAttributes(['id' => 'pp-featured-grid-page'])
                                        ->dehydrated(false),
                                    Forms\Components\Hidden::make('media_id')
                                        ->required()
                                        ->extraAttributes(['id' => 'pp-featured-media-input']),
                                    ViewComponent::make('filament.fields.media-grid-picker')
                                        ->viewData(function (Get $get) {
                                            $perPage = 20;
                                            $page = (int) ($get('grid_page') ?: 1);
                                            $folders = $get('folders');
                                            $query = Media::images();
                                            if (is_array($folders) && count($folders)) {
                                                $query->where(function ($q) use ($folders) {
                                                    foreach ($folders as $f) {
                                                        $q->orWhere('path', 'like', $f . '/%');
                                                    }
                                                });
                                            }
                                            $total = (clone $query)->count();
                                            $items = (clone $query)
                                                ->orderByDesc('created_at')
                                                ->skip(max(0, ($page - 1) * $perPage))
                                                ->take($perPage)
                                                ->get()
                                                ->map(fn($m) => [
                                                    'id' => $m->id,
                                                    'filename' => $m->filename,
                                                    'url' => $m->url(),
                                                ])->all();
                                            $selected = $get('media_id') ? [(int) $get('media_id')] : [];
                                            return [
                                                'mode' => 'single',
                                                'selectId' => 'pp-featured-media-input',
                                                'pageInputId' => 'pp-featured-grid-page',
                                                'items' => $items,
                                                'selected' => $selected,
                                                'page' => $page,
                                                'perPage' => $perPage,
                                                'total' => $total,
                                            ];
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, Set $set) {
                                    $media = Media::find($data['media_id'] ?? null);
                                    if ($media) {
                                        $set('featured_image', $media->path);
                                    }
                                })
                                ->successNotificationTitle('Zdjęcie wyróżniające ustawione'),
                        ]),

                        Forms\Components\FileUpload::make('gallery_images')
                            ->label('Zdjęcia do galerii')
                            ->hint('Możesz dodać do 10 zdjęć uzupełniających. Ułatwiają prezentację punktu programu.')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('program-points/gallery')
                            ->visibility('public')
                            ->downloadable()
                            ->previewable()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->maxSize(5120)
                            ->reorderable()
                            ->maxFiles(10)
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9','4:3','1:1'])
                            ->panelLayout('grid')
                            ->uploadingMessage('Przesyłanie zdjęć...')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadButtonPosition('left')
                            ->uploadProgressIndicatorPosition('left')
                            ->preserveFilenames()
                            ->live()
                            ->default(fn($record) => $record?->gallery_images ?? []),
                        FormActions::make([
                            FormAction::make('choose_gallery_from_media')
                                ->label('Dodaj z biblioteki')
                                ->icon('heroicon-o-rectangle-stack')
                                ->modalHeading('Wybierz zdjęcia do galerii')
                                ->form([
                                    Select::make('folders')
                                        ->label('Foldery')
                                        ->multiple()
                                        ->native(false)
                                        ->options(function () {
                                            $dirs = Media::images()->pluck('path')
                                                ->map(fn($p) => \Illuminate\Support\Str::before($p, '/'))
                                                ->filter(fn($d) => !empty($d))
                                                ->unique()
                                                ->sort()
                                                ->values();
                                            return $dirs->combine($dirs)->all();
                                        })
                                        ->placeholder('Wszystkie')
                                        ->live(),
                                    Forms\Components\TextInput::make('grid_page')
                                        ->default(1)
                                        ->live()
                                        ->extraAttributes(['id' => 'pp-gallery-grid-page'])
                                        ->extraInputAttributes(['id' => 'pp-gallery-grid-page'])
                                        ->dehydrated(false),
                                    Forms\Components\Hidden::make('media_ids')
                                        ->required()
                                        ->extraAttributes(['id' => 'pp-gallery-media-input']),
                                    ViewComponent::make('filament.fields.media-grid-picker')
                                        ->viewData(function (Get $get) {
                                            $perPage = 20;
                                            $page = (int) ($get('grid_page') ?: 1);
                                            $folders = $get('folders');
                                            $query = Media::images();
                                            if (is_array($folders) && count($folders)) {
                                                $query->where(function ($q) use ($folders) {
                                                    foreach ($folders as $f) {
                                                        $q->orWhere('path', 'like', $f . '/%');
                                                    }
                                                });
                                            }
                                            $total = (clone $query)->count();
                                            $items = (clone $query)
                                                ->orderByDesc('created_at')
                                                ->skip(max(0, ($page - 1) * $perPage))
                                                ->take($perPage)
                                                ->get()
                                                ->map(fn($m) => [
                                                    'id' => $m->id,
                                                    'filename' => $m->filename,
                                                    'url' => $m->url(),
                                                ])->all();
                                            $raw = $get('media_ids');
                                            $selected = is_array($raw) ? array_map('intval', $raw) : (json_decode((string) $raw, true) ?: []);
                                            return [
                                                'mode' => 'multi',
                                                'selectId' => 'pp-gallery-media-input',
                                                'pageInputId' => 'pp-gallery-grid-page',
                                                'items' => $items,
                                                'selected' => $selected,
                                                'page' => $page,
                                                'perPage' => $perPage,
                                                'total' => $total,
                                            ];
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, Get $get, Set $set) {
                                    $raw = $data['media_ids'] ?? [];
                                    $ids = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
                                    $paths = Media::whereIn('id', $ids)->pluck('path')->all();
                                    $current = $get('gallery_images') ?? [];
                                    if (!is_array($current)) { $current = []; }
                                    $new = array_values(array_unique(array_merge($current, $paths)));
                                    $set('gallery_images', $new);
                                })
                                ->successNotificationTitle('Zdjęcia dodane do galerii'),
                        ]),
                    ]),

                // Sekcja cenowa
                Forms\Components\Section::make('Wycena i koszty')
                    ->description('Ustawienia finansowe punktu programu')
                    ->icon('heroicon-o-currency-dollar')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Cena jednostkowa')
                            ->placeholder('np. 120.00')
                            ->hint('Podaj cenę za osobę lub grupę, zgodnie z charakterem punktu.')
                            ->numeric()
                            ->required(),
                        
                        Forms\Components\TextInput::make('group_size')
                            ->label('Wielkość grupy')
                            ->placeholder('np. 20')
                            ->hint('Podaj liczbę osób w grupie. Jeśli nie dotyczy, wpisz 1.')
                            ->numeric()
                            ->default(1),
                        
                        Forms\Components\Select::make('currency_id')
                            ->label('Waluta')
                            ->options(Currency::all()->pluck('name', 'id'))
                            ->searchable()
                            ->hint('Wybierz walutę, w której podana jest cena.')
                            ->required(),
                        
                        Forms\Components\Toggle::make('convert_to_pln')
                            ->label('Przeliczaj na złotówki')
                            ->hint('Jeśli zaznaczone, cena zostanie automatycznie przeliczona na PLN według kursu z dnia.')
                            ->default(true),
                    ]),

                // Sekcja tagów i kategoryzacji
                Forms\Components\Section::make('Tagi i kategoryzacja')
                    ->description('Klasyfikacja i wyszukiwanie')
                    ->icon('heroicon-o-tag')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('tags')
                            ->label('Tagi')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->hint('Wybierz lub utwórz tagi, które ułatwią wyszukiwanie i kategoryzację punktu.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nazwa tagu')
                                    ->placeholder('Wpisz nazwę tagu')
                                    ->hint('Nazwa powinna być krótka i jednoznaczna.')
                                    ->required(),
                                Forms\Components\RichEditor::make('description')
                                    ->label('Opis tagu')
                                    ->placeholder('Opcjonalny opis tagu, np. do czego służy, kiedy stosować...')
                                    ->hint('Opis widoczny tylko dla administratorów.')
                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                                Forms\Components\Select::make('visibility')
                                    ->label('Widoczność tagu')
                                    ->options([
                                        'public' => 'Publiczny (widoczny dla wszystkich)',
                                        'internal' => 'Wewnętrzny (tylko dla biura)',
                                    ])
                                    ->default('public')
                                    ->hint('Wewnętrzne tagi nie są widoczne dla uczestników.'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Definicja tabeli punktów programu w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('name', 'asc')
            ->searchable()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->extremePaginationLinks()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TagsColumn::make('tags.name')
                    ->label('Tagi')
                    ->separator(',')
                    ->searchable(),
                Tables\Columns\TextColumn::make('duration_hours')
                    ->label('Godz.')
                    ->sortable()
                    ->toggleable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Min.')
                    ->sortable()
                    ->toggleable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total_duration')
                    ->label('Czas łączny')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("(duration_hours * 60 + duration_minutes) {$direction}");
                    })
                    ->formatStateUsing(fn ($record) => 
                        $record->duration_hours . 'h ' . $record->duration_minutes . 'm'
                    )
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Cena')
                    ->sortable()
                    ->toggleable()
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('group_size')
                    ->label('Grupa')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('currency.symbol')
                    ->label('Waluta')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BooleanColumn::make('convert_to_pln')
                    ->label('Przelicz na PLN')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('name', 'asc')
            ->searchable()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->striped()
            ->filters([
                Tables\Filters\SelectFilter::make('tags')
                    ->label('Tagi')
                    ->relationship('tags', 'name')
                    ->multiple(),
                Tables\Filters\SelectFilter::make('currency')
                    ->label('Waluta')
                    ->relationship('currency', 'symbol'),
                Tables\Filters\Filter::make('convert_to_pln')
                    ->label('Przeliczane na PLN')
                    ->toggle(),
                Tables\Filters\Filter::make('duration')
                    ->form([
                        Forms\Components\TextInput::make('min_hours')
                            ->label('Min. godzin')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_hours')
                            ->label('Max. godzin')
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min_hours'], fn($query, $value) => $query->where('duration_hours', '>=', $value))
                            ->when($data['max_hours'], fn($query, $value) => $query->where('duration_hours', '<=', $value));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Podgląd'),
                Tables\Actions\EditAction::make()
                    ->label('Edytuj'),
                Tables\Actions\ReplicateAction::make()
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa kopii')
                            ->required()
                            ->default(fn($record) => $record->name . ' (kopia)'),
                    ])
                    ->label('Klonuj')
                    ->beforeReplicaSaved(function ($replica, array $data) {
                        $replica->name = $data['name'];
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Usuń'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj punkt programu'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('update_currency')
                        ->label('Zmień walutę')
                        ->icon('heroicon-o-currency-dollar')
                        ->form([
                            Forms\Components\Select::make('currency_id')
                                ->label('Nowa waluta')
                                ->options(Currency::all()->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['currency_id' => $data['currency_id']]);
                            });
                        }),
                    Tables\Actions\BulkAction::make('toggle_convert_to_pln')
                        ->label('Przełącz przeliczanie na PLN')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['convert_to_pln' => !$record->convert_to_pln]);
                            });
                        }),
                ]),
            ]);
    }

    /**
     * Relacje powiązane z punktem programu (brak w tym przypadku)
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
            'index' => Pages\ListEventTemplateProgramPoints::route('/'),
            'create' => Pages\CreateEventTemplateProgramPoint::route('/create'),
            'tree' => Pages\ManageTreeEventTemplateProgramPoints::route('/tree'),
            'edit' => Pages\EditEventTemplateProgramPoint::route('/{record}/edit'),
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view eventtemplateprogrampoint')) {
            return true;
        }
        return false;
    }
}

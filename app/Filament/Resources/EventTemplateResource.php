<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Filament\Resources\EventTemplateResource\Pages;
use App\Models\EventTemplate;
use App\Models\HotelRoom;
use App\Models\EventPriceDescription;
use App\Models\EventTemplatePricePerPerson;
use App\Models\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Forms\Components\View as ViewComponent;
use Filament\Forms\Components\Select;

/**
 * Resource Filament dla modelu EventTemplate.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane z szablonami wydarzeń.
 */
class EventTemplateResource extends Resource
{
    protected static ?string $model = EventTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Szablony imprez';
    protected static ?string $navigationLabel = 'Szablony imprez';
    protected static ?int $navigationSort = 10;

    /**
     * Definicja formularza do edycji/dodawania szablonu wydarzenia
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe informacje')
                ->icon('heroicon-o-information-circle')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nazwa')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(
                                    fn($state, callable $set) =>
                                    $set('slug', Str::slug($state))
                                ),
                            Forms\Components\TextInput::make('subtitle')
                                ->label('Podtytuł')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->required(),
                            Forms\Components\Toggle::make('is_active')
                                ->label('Aktywny')
                                ->default(true)
                                ->helperText('Tylko aktywne szablony są widoczne w systemie'),
                            Forms\Components\TextInput::make('duration_days')
                                ->label('Długość imprezy (dni)')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->extraInputAttributes(['step' => 1, 'min' => 1]),
                        ]),
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('eventTypes')
                                ->label('Typy wydarzenia')
                                ->multiple()
                                ->relationship('eventTypes', 'name')
                                ->preload()
                                ->searchable()
                                ->columnSpanFull(),
                            Forms\Components\Select::make('transportTypes')
                                ->label('Rodzaje transportu')
                                ->multiple()
                                ->relationship('transportTypes', 'name')
                                ->preload()
                                ->searchable()
                                ->columnSpanFull(),
                        ]),
                ]),

            Forms\Components\Section::make('Ceny i narzuty')
                ->icon('heroicon-o-currency-dollar')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('markup_id')
                                ->label('Narzut')
                                ->options(fn() => \App\Models\Markup::pluck('name', 'id'))
                                ->searchable()
                                ->nullable()
                                ->default(function () {
                                    $defaultMarkup = \App\Models\Markup::where('is_default', true)->first();
                                    return $defaultMarkup?->id;
                                })
                                ->helperText('Jeśli nie wybierzesz, zostanie użyty domyślny narzut.'),
                            Forms\Components\Select::make('event_price_description_id')
                                ->label('Opis ceny imprezy')
                                ->options(fn() => \App\Models\EventPriceDescription::pluck('name', 'id'))
                                ->searchable()
                                ->nullable()
                                ->helperText('Wybierz opis ceny imprezy. Możesz zostawić puste.')
                                ->live(),
                        ]),
                    Forms\Components\CheckboxList::make('taxes')
                        ->label('Podatki')
                        ->helperText('Wybierz podatki, które mają być naliczane dla tej imprezy')
                        ->relationship('taxes', 'name', function ($query) {
                            return $query->where('is_active', true);
                        })
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            $baseText = $record->apply_to_base ? 'od sumy bez narzutu' : '';
                            $markupText = $record->apply_to_markup ? 'od narzutu' : '';
                            $description = array_filter([$baseText, $markupText]);
                            $descriptionText = !empty($description) ? ' (' . implode(', ', $description) . ')' : '';
                            return $record->name . $descriptionText;
                        })
                        ->columns(2),
                ]),

            Forms\Components\Section::make('Opis i materiały')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\RichEditor::make('event_description')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                                ]),
                            Forms\Components\RichEditor::make('office_description')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                                ]),
                        ]),
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\FileUpload::make('featured_image')
                                ->label('Zdjęcie wyróżniające')
                                ->image()
                                ->disk('public')
                                ->directory('event-templates')
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
                                                    ->map(fn($p) => Str::before($p, '/'))
                                                    ->filter(fn($d) => !empty($d))
                                                    ->unique()
                                                    ->sort()
                                                    ->values();
                                                return $dirs->combine($dirs)->all();
                                            })
                                            ->placeholder('Wszystkie')
                                            ->live(),
                                        // Ukryty input do paginacji siatki
                                        Forms\Components\TextInput::make('grid_page')
                                            ->default(1)
                                            ->live()
                                            ->extraAttributes(['id' => 'featured-grid-page'])
                                            ->extraInputAttributes(['id' => 'featured-grid-page'])
                                            ->dehydrated(false)
                                            ->suffixIcon('heroicon-m-arrow-path'),
                                        // Ukryty input – nośnik wartości, sterowany przez grid
                                        Forms\Components\Hidden::make('media_id')
                                            ->required()
                                            ->extraAttributes(['id' => 'featured-media-input']),
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
                                                    'selectId' => 'featured-media-input',
                                                    'pageInputId' => 'featured-grid-page',
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
                            ])->columnSpan(1),
                            Forms\Components\FileUpload::make('gallery')
                                ->label('Zdjęcia do galerii')
                                ->hint('Możesz dodać do 10 zdjęć uzupełniających. Ułatwiają prezentację imprezy.')
                                ->image()
                                ->multiple()
                                ->disk('public')
                                ->directory('event-templates/gallery')
                                ->visibility('public')
                                ->downloadable()
                                ->previewable()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                                ->maxSize(5120)
                                ->reorderable()
                                ->maxFiles(10)
                                ->imageEditor()
                                ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                                ->panelLayout('grid')
                                ->uploadingMessage('Przesyłanie zdjęć...')
                                ->removeUploadedFileButtonPosition('right')
                                ->uploadButtonPosition('left')
                                ->uploadProgressIndicatorPosition('left')
                                ->preserveFilenames()
                                ->live()
                                ->default(fn($record) => $record?->gallery ?? []),
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
                                                    ->map(fn($p) => Str::before($p, '/'))
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
                                            ->extraAttributes(['id' => 'gallery-grid-page'])
                                            ->extraInputAttributes(['id' => 'gallery-grid-page'])
                                            ->dehydrated(false),
                                        Forms\Components\Hidden::make('media_ids')
                                            ->required()
                                            ->extraAttributes(['id' => 'gallery-media-input']),
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
                                                    'selectId' => 'gallery-media-input',
                                                    'pageInputId' => 'gallery-grid-page',
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
                                        $current = $get('gallery') ?? [];
                                        if (!is_array($current)) {
                                            $current = [];
                                        }
                                        $new = array_values(array_unique(array_merge($current, $paths)));
                                        $set('gallery', $new);
                                    })
                                    ->successNotificationTitle('Zdjęcia dodane do galerii'),
                            ])->columnSpan(1),
                        ]),
                ]),

            Forms\Components\Section::make('Tagi i kategoryzacja')
                ->description('Klasyfikacja i wyszukiwanie')
                ->icon('heroicon-o-tag')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('tags')
                                ->label('Tagi')
                                ->multiple()
                                ->relationship('tags', 'name')
                                ->preload()
                                ->searchable()
                                ->columnSpanFull(),
                        ]),
                ]),

            Forms\Components\Section::make('SEO')
                ->icon('heroicon-o-magnifying-glass')
                ->collapsible()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')
                                ->label('Tytuł SEO')
                                ->maxLength(70)
                                ->helperText('Tytuł strony widoczny w Google (max 70 znaków)'),
                            Forms\Components\RichEditor::make('seo_description')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                                ])
                                ->helperText('Opis strony widoczny w Google (max 350 znaków)'),
                            Forms\Components\TextInput::make('seo_keywords')
                                ->label('Słowa kluczowe')
                                ->helperText('Oddziel przecinkami'),
                        ]),
                ]),
        ]);
    }

    /**
     * Definicja tabeli szablonów wydarzeń w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                // Keep eager loading light to avoid exhausting memory when rendering the table
                $query->with([
                    'startingPlaceAvailabilities' => fn($q) => $q
                        ->where('available', true)
                        ->with(['startPlace:id,name'])
                        ->select(['id', 'event_template_id', 'start_place_id', 'available']),
                    'tags:id,name',
                    'eventTypes:id,name',
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('duration_days')
                    ->label('Długość (dni)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                // Opcjonalne kolumny
                Tables\Columns\TextColumn::make('notes')
                    ->label('Uwagi')
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('office_description')
                    ->label('Opis dla biura')
                    ->html()
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                    })
                    ->tooltip(fn($record) => $record->is_active ? 'Kliknij, aby dezaktywować' : 'Kliknij, aby aktywować')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('available_start_places')
                    ->label('Dostępne miejsca wyjazdu')
                    ->getStateUsing(function ($record) {
                        return $record->startingPlaceAvailabilities
                            ->pluck('startPlace.name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->join(', ');
                    })
                    ->placeholder('Brak dostępnych')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('min_price_for_selected_start')
                    ->label('Min. cena (PLN)')
                    ->getStateUsing(function ($record) {
                        $filters = request()->get('tableFilters') ?? [];
                        $startId = null;
                        if (isset($filters['start_place']['value'])) {
                            $startId = (int) ($filters['start_place']['value']);
                        } elseif (isset($filters['start_place']['values']) && is_array($filters['start_place']['values'])) {
                            $startId = (int) ($filters['start_place']['values'][0] ?? 0);
                        }
                        if (!$startId) {
                            return '—';
                        }
                        $availableIds = $record->startingPlaceAvailabilities
                            ->pluck('start_place_id')
                            ->map(fn($v) => (int) $v);
                        if ($availableIds->isEmpty() || !$availableIds->contains($startId)) {
                            return '—';
                        }
                        $min = EventTemplatePricePerPerson::query()
                            ->where('event_template_id', $record->id)
                            ->where('start_place_id', $startId)
                            ->where('price_per_person', '>', 0)
                            ->whereHas('currency', function ($q) {
                                $q->where(fn($qq) => $qq
                                    ->where('code', 'PLN')
                                    ->orWhere('name', 'like', '%złoty%')
                                    ->orWhere('name', 'like', '%polish%'));
                            })
                            ->selectRaw('MIN(price_per_person) as min_price')
                            ->value('min_price');

                        if (!$min) {
                            return '—';
                        }

                        $rounded = ceil(((float) $min) / 5) * 5;
                        return number_format($rounded, 0, ',', ' ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Tylko aktywne')
                    ->query(fn($query) => $query->where('is_active', true))
                    ->default(),
                Tables\Filters\SelectFilter::make('start_place')
                    ->label('Możliwe miejsce wyjazdu')
                    ->options(fn() => \App\Models\Place::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->native(false)
                    ->query(function ($query, $data) {
                        $id = $data['value'] ?? null;
                        if ($id) {
                            $query->whereHas('startingPlaceAvailabilities', function ($q) use ($id) {
                                $q->where('available', true)->where('start_place_id', (int) $id);
                            });
                        }
                    }),
                Tables\Filters\SelectFilter::make('event_types')
                    ->label('Rodzaj wycieczki')
                    ->relationship('eventTypes', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('tags')
                    ->label('Tagi')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('duration_days')
                    ->label('Długość wycieczki (dni)')
                    ->options(function () {
                        $values = EventTemplate::query()->select('duration_days')->distinct()->orderBy('duration_days')->pluck('duration_days')->all();
                        $opts = [];
                        foreach ($values as $v) {
                            $opts[(string)$v] = (string)$v;
                        }
                        return $opts;
                    })
                    ->native(false)
                    ->searchable()
                    ->query(function ($query, $data) {
                        $val = $data['value'] ?? null;
                        if ($val !== null && $val !== '') {
                            $query->where('duration_days', (int) $val);
                        }
                    }),
                Tables\Filters\TrashedFilter::make()
                    ->label('Kosz'),
            ])
            ->actions([
                Tables\Actions\Action::make('create_event')
                    ->label('Utwórz imprezę')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(fn($record) => route('filament.admin.resources.events.create', ['template' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\ViewAction::make()
                    ->label('Podgląd'),
                Tables\Actions\EditAction::make()
                    ->label('Edytuj'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Usuń zaznaczone'),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Usuń na stałe'),
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Przywróć'),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    /**
     * Relacje powiązane z szablonem wydarzenia (jeśli są)
     */
    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventTemplates::route('/'),
            'create' => Pages\CreateEventTemplate::route('/create'),
            'edit' => Pages\EditEventTemplate::route('/{record}/edit'),
            'edit-program' => Pages\EditEventTemplateProgram::route('/{record}/program'),
            'calculation' => Pages\EventTemplateCalculation::route('/{record}/calculation'),
            'transport' => Pages\EventTemplateTransport::route('/{record}/transport'),
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view eventtemplate')) {
            return true;
        }
        return false;
    }
}

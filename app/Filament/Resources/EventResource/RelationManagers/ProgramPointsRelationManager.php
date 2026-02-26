<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramPointsRelationManager extends RelationManager
{
    protected static string $relationship = 'programPoints';
    protected static ?string $title = 'Program imprezy';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_template_program_point_id')
                    ->label('Punkt programu')
                    ->relationship('templatePoint', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('day')
                    ->label('Dzień')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Forms\Components\TextInput::make('order')
                    ->label('Kolejność')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Cena jednostkowa (PLN)')
                    ->numeric()
                    ->prefix('PLN')
                    ->step(0.01),

                Forms\Components\TextInput::make('quantity')
                    ->label('Ilość')
                    ->numeric()
                    ->minValue(1)
                    ->default(1),

                Forms\Components\TextInput::make('total_price')
                    ->label('Cena całkowita (PLN)')
                    ->numeric()
                    ->prefix('PLN')
                    ->step(0.01)
                    ->readOnly()
                    ->helperText('Obliczane automatycznie'),

                Forms\Components\RichEditor::make('notes')
                    ->label('Uwagi')
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        'orderedList',
                    ]),

                Forms\Components\Toggle::make('include_in_program')
                    ->label('Uwzględnij w programie')
                    ->default(true),

                Forms\Components\Toggle::make('include_in_calculation')
                    ->label('Uwzględnij w kalkulacji')
                    ->default(true),

                Forms\Components\Toggle::make('active')
                    ->label('Aktywny')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->deferLoading()
            ->defaultSort('day')
            ->groups([
                Tables\Grouping\Group::make('day')
                    ->label('Dzień')
                    ->getTitleFromRecordUsing(fn($record) => 'Dzień ' . $record->day)
                    ->collapsible(false) // Nie pozwalamy na zwijanie
                    ->orderQueryUsing(fn($query, string $direction) => $query->orderBy('day', $direction))
            ])
            ->defaultGroup('day')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Kolejność')
                    ->sortable(false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('templatePoint.name')
                    ->label('Punkt programu')
                    ->searchable()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Cena jedn.')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Ilość')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Cena całk.')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Uwagi')
                    ->limit(30)
                    ->html(),

                Tables\Columns\IconColumn::make('include_in_program')
                    ->label('W programie')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('include_in_calculation')
                    ->label('W kalkulacji')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Aktywny')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('day')
                    ->label('Dzień')
                    ->options(function () {
                        $maxDay = $this->getOwnerRecord()->eventTemplate->duration_days ?? 3;
                        $options = [];
                        for ($i = 1; $i <= $maxDay; $i++) {
                            $options[$i] = "Dzień {$i}";
                        }
                        return $options;
                    }),

                Tables\Filters\TernaryFilter::make('include_in_program')
                    ->label('Uwzględniony w programie'),

                Tables\Filters\TernaryFilter::make('include_in_calculation')
                    ->label('Uwzględniony w kalkulacji'),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Aktywny'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_program_point')
                    ->label('Dodaj punkt programu')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Dodaj punkt programu do imprezy')
                    ->modalDescription('Wybierz punkt programu z biblioteki szablonów')
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Dodaj punkt')
                    ->modalCancelActionLabel('Anuluj')
                    ->form([
                        Forms\Components\Select::make('event_template_program_point_id')
                            ->label('Wybierz punkt programu')
                            ->searchable(['name', 'description'])
                            ->preload()
                            ->required()
                            ->options(function () {
                                return EventTemplateProgramPoint::all()
                                    ->mapWithKeys(function ($point) {
                                        // Przygotowujemy bogate dane do wyświetlenia
                                        $duration = $point->duration_hours . 'h';
                                        if ($point->duration_minutes > 0) {
                                            $duration .= ' ' . $point->duration_minutes . 'min';
                                        }

                                        $tags = $point->tags->pluck('name')->join(', ');
                                        $price = number_format($point->unit_price, 2) . ' ' . ($point->currency->code ?? 'PLN');

                                        $description = strip_tags($point->description ?? '');
                                        $shortDescription = strlen($description) > 100
                                            ? substr($description, 0, 100) . '...'
                                            : $description;

                                        $officeNotes = strip_tags($point->office_notes ?? '');
                                        $shortOfficeNotes = strlen($officeNotes) > 50
                                            ? substr($officeNotes, 0, 50) . '...'
                                            : $officeNotes;

                                        $html = '<div class="space-y-2">';
                                        $html .= '<div class="font-semibold text-gray-900">' . e($point->name) . '</div>';

                                        if ($shortDescription) {
                                            $html .= '<div class="text-sm text-gray-600">📝 ' . e($shortDescription) . '</div>';
                                        }

                                        if ($tags) {
                                            $html .= '<div class="text-xs text-blue-600">🏷️ ' . e($tags) . '</div>';
                                        }

                                        $html .= '<div class="flex gap-4 text-xs text-gray-500">';
                                        $html .= '<span>⏱️ ' . e($duration) . '</span>';
                                        $html .= '<span>💰 ' . e($price) . '</span>';
                                        $html .= '</div>';

                                        if ($shortOfficeNotes) {
                                            $html .= '<div class="text-xs text-orange-600">📋 ' . e($shortOfficeNotes) . '</div>';
                                        }

                                        $html .= '</div>';

                                        return [$point->id => $html];
                                    });
                            })
                            ->allowHtml()
                            ->placeholder('Wybierz punkt programu z biblioteki...')
                            ->helperText('Wybierz punkt programu z dostępnych szablonów')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $templatePoint = EventTemplateProgramPoint::find($state);
                                    if ($templatePoint) {
                                        $set('unit_price', $templatePoint->unit_price);
                                    }
                                }
                            }),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('Dzień')
                                    ->options(function () {
                                        $maxDay = $this->getOwnerRecord()->eventTemplate->duration_days ?? 3;
                                        $options = [];
                                        for ($i = 1; $i <= $maxDay; $i++) {
                                            $options[$i] = "Dzień {$i}";
                                        }
                                        return $options;
                                    })
                                    ->default(1)
                                    ->required(),

                                Forms\Components\TextInput::make('order')
                                    ->label('Kolejność')
                                    ->numeric()
                                    ->default(function () {
                                        return $this->getOwnerRecord()
                                            ->programPoints()
                                            ->max('order') + 1;
                                    })
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Cena jednostkowa (PLN)')
                                    ->numeric()
                                    ->prefix('PLN')
                                    ->step(0.01)
                                    ->helperText('Zostanie automatycznie wypełniona z szablonu'),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Ilość')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1),
                            ]),

                        Forms\Components\RichEditor::make('notes')
                            ->label('Uwagi specjalne dla tej imprezy')
                            ->placeholder('Dodatkowe uwagi specyficzne dla tej imprezy...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('include_in_program')
                                    ->label('Uwzględnij w programie')
                                    ->default(true),

                                Forms\Components\Toggle::make('include_in_calculation')
                                    ->label('Uwzględnij w kalkulacji')
                                    ->default(true),

                                Forms\Components\Toggle::make('active')
                                    ->label('Aktywny')
                                    ->default(true),
                            ]),
                    ])
                    ->action(function (array $data) {
                        // Pobieramy szablon punktu programu
                        $templatePoint = EventTemplateProgramPoint::find($data['event_template_program_point_id']);

                        // Tworzymy nowy punkt programu dla imprezy
                        $this->getOwnerRecord()->programPoints()->create([
                            'event_template_program_point_id' => $data['event_template_program_point_id'],
                            'day' => $data['day'],
                            'order' => $data['order'],
                            'unit_price' => $data['unit_price'] ?? $templatePoint->unit_price,
                            'quantity' => $data['quantity'],
                            'total_price' => ($data['unit_price'] ?? $templatePoint->unit_price) * $data['quantity'],
                            'notes' => $data['notes'],
                            'include_in_program' => $data['include_in_program'],
                            'include_in_calculation' => $data['include_in_calculation'],
                            'active' => $data['active'],
                        ]);
                    }),

                Tables\Actions\Action::make('copy_from_template')
                    ->label('Skopiuj z szablonu')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function () {
                        $event = $this->getOwnerRecord();
                        $event->copyProgramPointsFromTemplate();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Skopiuj program z szablonu')
                    ->modalDescription('To działanie skopiuje wszystkie punkty programu z szablonu. Istniejące punkty zostaną zastąpione.')
                    ->visible(fn() => $this->getOwnerRecord()->programPoints()->count() === 0),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplikuj')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->action(fn(EventProgramPoint $record) => $record->duplicate()),

                Tables\Actions\Action::make('move_to_day')
                    ->label('Przenieś do dnia')
                    ->icon('heroicon-o-arrow-right')
                    ->form([
                        Forms\Components\Select::make('new_day')
                            ->label('Dzień docelowy')
                            ->options(function () {
                                $maxDay = $this->getOwnerRecord()->eventTemplate->duration_days ?? 3;
                                $options = [];
                                for ($i = 1; $i <= $maxDay; $i++) {
                                    $options[$i] = "Dzień {$i}";
                                }
                                return $options;
                            })
                            ->required(),
                    ])
                    ->action(function (EventProgramPoint $record, array $data) {
                        $record->moveToDay((int)$data['new_day']);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Brak punktów programu')
            ->emptyStateDescription('Dodaj punkty programu lub skopiuj je z szablonu.')
            ->emptyStateIcon('heroicon-o-calendar-days');
        // Dodajemy prosty include z kontrolkami DnD (renderowane w widoku Filament)
        echo view('filament.resources.event-resource.relation-managers.program-points-dnd-controls')->render();

        // Renderujemy Livewire component z listą punktów i obsługą zapisu porządku
        try {
            echo \Livewire\Livewire::mount('event-program-dnd', ['eventId' => $this->getOwnerRecord()->id])->html();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Nie udało się zamontować Livewire EventProgramDnD: ' . $e->getMessage());
        }
    }

    public function reorderTable(array $order): void
    {
        try {
            \Illuminate\Support\Facades\Log::info('Początek reorderTable Event z grupowaniem', ['order' => $order]);

            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                // Obsługa dwóch formatów payloadu:
                // 1) prosty: [id1, id2, id3]
                // 2) obiektowy: [{id:1, parent_id: null, day:1, order:1}, ...]
                $ids = [];
                $objectPayload = false;
                if (!empty($order) && is_array($order) && isset($order[0]) && is_array($order[0]) && isset($order[0]['id'])) {
                    $objectPayload = true;
                    foreach ($order as $item) {
                        $ids[] = $item['id'];
                    }
                } else {
                    $ids = $order;
                }

                // Pobieramy wszystkie rekordy do przetwarzania
                $records = EventProgramPoint::whereIn('id', $ids)->get()->keyBy('id');

                // Pobieramy aktualną strukturę grup (dni) z tabeli
                $currentDays = $this->getOwnerRecord()
                    ->programPoints()
                    ->select('day')
                    ->distinct()
                    ->orderBy('day')
                    ->pluck('day')
                    ->toArray();

                // Jeśli nie ma dni, tworzymy przynajmniej dzień 1
                if (empty($currentDays)) {
                    $currentDays = [1];
                }

                // Generujemy mapowanie pozycji do dni na podstawie aktualnego sortowania tabeli
                $dayMapping = [];
                $itemsPerDay = [];

                // Najpierw liczymy ile elementów jest w każdym dniu
                foreach ($records as $record) {
                    $itemsPerDay[$record->day] = ($itemsPerDay[$record->day] ?? 0) + 1;
                }

                // Tworzymy mapowanie pozycji globalnej na dzień i pozycję lokalną
                $globalPosition = 0;
                foreach ($currentDays as $day) {
                    $itemsInDay = $itemsPerDay[$day] ?? 0;
                    for ($i = 0; $i < $itemsInDay; $i++) {
                        $dayMapping[$globalPosition] = [
                            'day' => $day,
                            'local_order' => $i + 1
                        ];
                        $globalPosition++;
                    }
                }

                // Aktualizujemy rekordy zgodnie z nową kolejnością
                if ($objectPayload) {
                    // payload zawiera pełne informacje
                    foreach ($order as $item) {
                        $recordId = $item['id'];
                        if (!isset($records[$recordId])) {
                            continue;
                        }
                        $newDay = $item['day'] ?? $records[$recordId]->day;
                        $newOrder = $item['order'] ?? $records[$recordId]->order;
                        $newParent = array_key_exists('parent_id', $item) ? ($item['parent_id'] !== '' ? (int)$item['parent_id'] : null) : ($records[$recordId]->parent_id ? (int)$records[$recordId]->parent_id : null);

                        \Illuminate\Support\Facades\Log::info('Aktualizacja rekordu Event (obiektowy)', [
                            'recordId' => $recordId,
                            'oldDay' => $records[$recordId]->day,
                            'newDay' => $newDay,
                            'newOrder' => $newOrder,
                            'newParent' => $newParent,
                        ]);

                        $result = EventProgramPoint::where('id', $recordId)
                            ->update([
                                'day' => $newDay,
                                'order' => $newOrder,
                                'parent_id' => $newParent,
                            ]);

                        \Illuminate\Support\Facades\Log::info('Wynik aktualizacji Event', ['result' => $result]);
                    }
                } else {
                    // stary prosty format - zachowujemy dotychczasową logikę mapowania dni i pozycji
                    foreach ($order as $index => $recordId) {
                        if (isset($records[$recordId]) && isset($dayMapping[$index])) {
                            $newDay = $dayMapping[$index]['day'];
                            $newOrder = $dayMapping[$index]['local_order'];

                            \Illuminate\Support\Facades\Log::info('Aktualizacja rekordu Event', [
                                'recordId' => $recordId,
                                'oldDay' => $records[$recordId]->day,
                                'newDay' => $newDay,
                                'newOrder' => $newOrder,
                                'globalIndex' => $index
                            ]);

                            $result = EventProgramPoint::where('id', $recordId)
                                ->update([
                                    'day' => $newDay,
                                    'order' => $newOrder
                                ]);

                            \Illuminate\Support\Facades\Log::info('Wynik aktualizacji Event', ['result' => $result]);
                        }
                    }
                }
            });

            \Illuminate\Support\Facades\Log::info('Koniec reorderTable Event - sukces');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Błąd podczas przestawiania Event: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function getTableQueryForPage(): Builder
    {
        $query = parent::getTableQueryForPage();

        // Dodajemy puste rekordy dla dni bez punktów programu
        $event = $this->getOwnerRecord();
        $maxDay = $event->eventTemplate->duration_days ?? 3;

        // Sprawdzamy które dni mają punkty programu
        $usedDays = $event->programPoints()->distinct('day')->pluck('day')->toArray();

        // Dla dni bez punktów, tworzymy "phantom" rekordy (nie zapisujemy do bazy)
        // To jest tylko do wyświetlenia pustych grup
        for ($day = 1; $day <= $maxDay; $day++) {
            if (!in_array($day, $usedDays)) {
                // Dla pustych dni Filament automatycznie pokaże pustą grupę
                // gdy użyjemy defaultGroup
            }
        }

        return $query;
    }
}

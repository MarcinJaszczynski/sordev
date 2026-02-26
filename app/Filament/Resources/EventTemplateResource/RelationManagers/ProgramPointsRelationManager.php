<?php

namespace App\Filament\Resources\EventTemplateResource\RelationManagers;

use App\Models\EventTemplateProgramPoint;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramPointsRelationManager extends RelationManager
{
    protected static string $relationship = 'programPoints';
    protected static ?string $title = 'Program imprezy';
    protected static ?string $recordTitleAttribute = 'name';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->reorderable('order')
            ->deferLoading()
            ->columns([
                Tables\Columns\TextColumn::make('event_template_event_template_program_point.day')
                    ->label('Dzień')
                    ->formatStateUsing(function ($state, $record, $rowLoop, $livewire) {
                        // Separator: wyświetl numer dnia tylko nad pierwszym punktem danego dnia
                        static $lastDay = null;
                        $output = '';
                        if ($lastDay !== $state) {
                            $output = '<b style="display:block;margin-top:8px;">Dzień ' . $state . '</b>';
                            $lastDay = $state;
                        }
                        return $output;
                    })
                    ->html()
                    ->sortable(false),
                Tables\Columns\TextColumn::make('name')->label('Punkt programu')->searchable()->sortable(false),
                Tables\Columns\TextColumn::make('event_template_event_template_program_point.order')
                    ->label('Kolejność')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('event_template_event_template_program_point.notes')
                    ->label('Uwagi')
                    ->limit(30)
                    ->sortable(false),
                Tables\Columns\BooleanColumn::make('event_template_event_template_program_point.include_in_program')
                    ->label('W programie')
                    ->sortable(false)
                    ->formatStateUsing(fn ($state) => (bool)$state),
                Tables\Columns\BooleanColumn::make('event_template_event_template_program_point.include_in_calculation')
                    ->label('W kalkulacji')
                    ->sortable(false)
                    ->formatStateUsing(fn ($state) => (bool)$state),
                Tables\Columns\BooleanColumn::make('event_template_event_template_program_point.active')
                    ->label('Aktywny')
                    ->sortable(false)
                    ->formatStateUsing(fn ($state) => (bool)$state),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplikuj')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Duplikuj punkt programu')
                    ->modalDescription('Czy na pewno chcesz zduplikować ten punkt programu?')
                    ->modalSubmitActionLabel('Tak, duplikuj')
                    ->action(function ($record) {
                        // Znajdź największą kolejność w tym samym dniu
                        $currentDay = $record->pivot->day;
                        $maxOrder = $this->getOwnerRecord()
                            ->programPoints()
                            ->wherePivot('day', $currentDay)
                            ->max('event_template_event_template_program_point.order');
                        
                        // Duplikuj punkt z nową kolejnością - jawnie rzuć wartości na bool
                        $attachData = [
                            'day' => $currentDay,
                            'order' => ($maxOrder ?? 0) + 1,
                            'notes' => $record->pivot->notes,
                            'include_in_program' => (bool)($record->pivot->include_in_program ?? false),
                            'include_in_calculation' => (bool)($record->pivot->include_in_calculation ?? false),
                            'active' => (bool)($record->pivot->active ?? false),
                        ];
                        
                        Log::debug('DuplicateAction', [
                            'record_id' => $record->id,
                            'raw_pivot' => $record->pivot->getAttributes(),
                            'attach_data' => $attachData,
                        ]);
                        
                        $this->getOwnerRecord()->programPoints()->attach($record->id, $attachData);
                    }),
                Tables\Actions\Action::make('edit')
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Edytuj punkt programu')
                    ->modalSubmitActionLabel('Zapisz')
                    ->form([
                        Forms\Components\Select::make('event_template_program_point_id')
                            ->label('Punkt programu')
                            ->options(EventTemplateProgramPoint::all()->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('day')->label('Dzień')->numeric()->required(),
                        Forms\Components\TextInput::make('order')->label('Kolejność')->numeric()->required(),
                        Forms\Components\Textarea::make('notes')->label('Uwagi'),
                        Forms\Components\Toggle::make('include_in_program')
                            ->label('Uwzględniaj w programie')
                            ->default(false)
                            ->inline(false),
                        Forms\Components\Toggle::make('include_in_calculation')
                            ->label('Uwzględniaj w kalkulacji')
                            ->default(false)
                            ->inline(false),
                        Forms\Components\Toggle::make('active')
                            ->label('Aktywny')
                            ->default(false)
                            ->inline(false),
                    ])
                    ->mountUsing(function ($form, $record) {
                        // Pobieramy wartości bezpośrednio z tabeli pivot i jawnie je konwertujemy na bool
                        $pivotData = [
                            'event_template_program_point_id' => $record->id,
                            'day' => $record->pivot->day,
                            'order' => $record->pivot->order,
                            'notes' => $record->pivot->notes,
                            'include_in_program' => (bool)($record->pivot->include_in_program ?? false),
                            'include_in_calculation' => (bool)($record->pivot->include_in_calculation ?? false),
                            'active' => (bool)($record->pivot->active ?? false),
                        ];
                        Log::debug('EditAction mountUsing - loadowanie wartości z pivot', [
                            'pivot_data' => $pivotData,
                            'raw_pivot' => $record->pivot->getAttributes(),
                        ]);
                        $form->fill($pivotData);
                    })
                    ->action(function ($data, $record) {
                        // Konwertuj wartości z formularza na bool przed zapisem
                        $updateData = [
                            'day' => $data['day'],
                            'order' => $data['order'],
                            'notes' => $data['notes'] ?? null,
                            'include_in_program' => (bool)($data['include_in_program'] ?? false),
                            'include_in_calculation' => (bool)($data['include_in_calculation'] ?? false),
                            'active' => (bool)($data['active'] ?? false),
                        ];
                        Log::debug('EditAction saving', [
                            'form_data' => $data,
                            'update_data' => $updateData,
                        ]);
                        $record->pivot->update($updateData);
                    }),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('move_to_day')
                    ->label('Przenieś do dnia')
                    ->icon('heroicon-o-arrow-right')
                    ->form([
                        Forms\Components\Select::make('new_day')
                            ->label('Dzień docelowy')
                            ->options(function ($record) {
                                $template = $this->getOwnerRecord();
                                $days = $template->duration_days ?? 1;
                                $options = [];
                                for ($i = 1; $i <= $days; $i++) {
                                    $options[$i] = 'Dzień ' . $i;
                                }
                                return $options;
                            })
                            ->required(),
                    ])
                    ->action(function ($data, $record) {
                        $this->moveToDay($record, (int)$data['new_day']);
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('attach')
                    ->label('Dodaj punkt do programu')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Dodaj punkt programu do imprezy')
                    ->modalSubmitActionLabel('Dodaj')
                    ->form([
                        Forms\Components\Select::make('event_template_program_point_id')
                            ->label('Punkt programu')
                            ->options(EventTemplateProgramPoint::all()->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('day')->label('Dzień')->numeric()->required(),
                        Forms\Components\TextInput::make('order')->label('Kolejność')->numeric()->required(),
                        Forms\Components\Textarea::make('notes')->label('Uwagi'),
                        Forms\Components\Toggle::make('include_in_program')
                            ->label('Uwzględniaj w programie')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('include_in_calculation')
                            ->label('Uwzględniaj w kalkulacji')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('active')
                            ->label('Aktywny')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->action(function ($data) {
                        try {
                            // Jawnie konwertuj wartości checboxów na bool przed attachem
                            $attachData = [
                                'day' => $data['day'],
                                'order' => $data['order'],
                                'notes' => $data['notes'] ?? null,
                                'include_in_program' => (bool)($data['include_in_program'] ?? false),
                                'include_in_calculation' => (bool)($data['include_in_calculation'] ?? false),
                                'active' => (bool)($data['active'] ?? false),
                            ];
                            
                            Log::debug('AttachAction', [
                                'form_data' => $data,
                                'attach_data' => $attachData,
                            ]);
                            
                            $this->getOwnerRecord()->programPoints()->attach($data['event_template_program_point_id'], $attachData);
                            
                            // Logowanie sukcesu
                            Log::info('Punkt programu dodany pomyślnie', ['data' => $attachData]);
                        } catch (\Exception $e) {
                            // Logowanie błędu
                            Log::error('Błąd dodawania punktu programu: ' . $e->getMessage(), [
                                'data' => $data,
                                'trace' => $e->getTraceAsString()
                            ]);
                            
                            // Rzuć wyjątek dalej, aby Filament mógł go obsłużyć
                            throw $e;
                        }
                    }),
            ]);
    }

    public function getTableQuery(): Builder
    {
        return $this->getOwnerRecord()
            ->programPoints()
            ->withPivot([
                'id', // Koniecznie dodajemy id do pivot
                'day',
                'order',
                'notes',
                'include_in_program',
                'include_in_calculation',
                'active',
            ])
            ->orderBy('event_template_event_template_program_point.day')
            ->orderBy('event_template_event_template_program_point.order')
            ->getQuery();
    }
    
    /**
     * Konfiguracja sortowania tabeli - używa pól z tabeli pivot
     */
    protected function getTableReorderColumn(): string 
    {
        return 'order';
    }

    /**
     * Dodatkowa konfiguracja dla sortowania tabeli
     * 
     * @return array|null
     */
    protected function getDefaultTableSortColumn(): ?string
    {
        return 'event_template_event_template_program_point.order';
    }
    
    /**
     * Kierunek domyślnego sortowania
     */
    protected function getDefaultTableSortDirection(): ?string
    {
        return 'asc';
    }

    public function reorderTable(array $order): void
    {
        try {
            \Illuminate\Support\Facades\Log::info('Początek reorderTable EventTemplate', ['order' => $order]);
            
            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                $ownerRecord = $this->getOwnerRecord();
                $relationship = $this->getRelationship();
                $pivotTable = $relationship->getTable();
                $foreignPivotKey = $relationship->getForeignPivotKeyName();
                $relatedPivotKey = $relationship->getRelatedPivotKeyName();
                
                // Grupujemy rekordy według dnia przed zmianą kolejności
                $recordsByDay = [];
                foreach ($order as $recordId) {
                    // Pobieramy aktualny dzień z tabeli pivot
                    $currentRecord = \Illuminate\Support\Facades\DB::table($pivotTable)
                        ->where($foreignPivotKey, $ownerRecord->getKey())
                        ->where($relatedPivotKey, $recordId)
                        ->first();
                    
                    if ($currentRecord) {
                        $recordsByDay[$currentRecord->day][] = $recordId;
                    }
                }
                
                // Aktualizujemy kolejność w obrębie każdego dnia
                foreach ($recordsByDay as $day => $recordIds) {
                    foreach ($recordIds as $index => $recordId) {
                        \Illuminate\Support\Facades\Log::info('Aktualizacja rekordu EventTemplate', [
                            'day' => $day,
                            'index' => $index,
                            'recordId' => $recordId,
                            'newOrder' => $index + 1
                        ]);
                        
                        $result = \Illuminate\Support\Facades\DB::table($pivotTable)
                            ->where($foreignPivotKey, $ownerRecord->getKey())
                            ->where($relatedPivotKey, $recordId)
                            ->update(['order' => $index + 1]);
                            
                        \Illuminate\Support\Facades\Log::info('Wynik aktualizacji EventTemplate', ['result' => $result]);
                    }
                }
            });
            
            \Illuminate\Support\Facades\Log::info('Koniec reorderTable EventTemplate - sukces');
        } catch (\Exception $e) {
            // Logowanie błędów
            \Illuminate\Support\Facades\Log::error('Błąd podczas przestawiania EventTemplate: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Uaktualnij kolejność dla wielu elementów jednocześnie
     *
     * @param array $orderData Tablica danych w formacie [id => kolejność]
     * @return void
     */
    public function updateBulkOrder(array $orderData): void
    {
        try {
            Log::info('Aktualizacja wielu rekordów', ['orderData' => $orderData]);
            
            DB::transaction(function () use ($orderData) {
                $ownerRecord = $this->getOwnerRecord();
                $relationship = $this->getRelationship();
                $pivotTable = $relationship->getTable();
                
                foreach ($orderData as $id => $order) {
                    DB::table($pivotTable)
                        ->where('id', $id)
                        ->update(['order' => $order]);
                    
                    Log::debug("Zaktualizowano kolejność w RelationManager", [
                        'id' => $id,
                        'order' => $order
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error('Błąd podczas aktualizacji kolejności: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Przenosi punkt programu do wybranego dnia i ustawia go na końcu tego dnia
     */
    public function moveToDay($record, int $newDay): void
    {
        $pivotTable = $this->getRelationship()->getTable();
        $foreignPivotKey = $this->getRelationship()->getForeignPivotKeyName();
        $relatedPivotKey = $this->getRelationship()->getRelatedPivotKeyName();
        $ownerId = $this->getOwnerRecord()->getKey();

        // Znajdź największy order w nowym dniu
        $maxOrder = DB::table($pivotTable)
            ->where($foreignPivotKey, $ownerId)
            ->where('day', $newDay)
            ->max('order');
        $newOrder = $maxOrder ? $maxOrder + 1 : 1;

        // Zaktualizuj rekord pivot
        DB::table($pivotTable)
            ->where($foreignPivotKey, $ownerId)
            ->where($relatedPivotKey, $record->id)
            ->update([
                'day' => $newDay,
                'order' => $newOrder,
            ]);
    }
}
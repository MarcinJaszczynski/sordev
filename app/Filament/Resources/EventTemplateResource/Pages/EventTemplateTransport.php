<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;
use App\Filament\Resources\EventTemplateResource;
use Filament\Resources\Pages\Page;
use App\Models\EventTemplate;
use App\Models\Bus;
use App\Models\TransportType;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EventTemplateTransport extends Page implements HasForms
{
    protected $listeners = ['toggleAvailability', 'updateAvailabilityNote'];

    public function toggleAvailability($eventTemplateId, $startPlaceId, $endPlaceId, $available)
    {
        if (!$startPlaceId || !$endPlaceId) return;

        $availability = \App\Models\EventTemplateStartingPlaceAvailability::updateOrCreate([
            'event_template_id' => $eventTemplateId,
            'start_place_id' => $startPlaceId,
            'end_place_id' => $endPlaceId,
        ], [
            'available' => $available,
        ]);

        // Przelicz ponownie ceny tylko dla tego miejsca startowego (nie globalnie)
        try {
            $calculator = new \App\Services\UnifiedPriceCalculator();
            // Kalkuluj i zapisz tylko dla pojedynczego startPlaceId
            $calculator->calculateAndPersist($this->record, $startPlaceId, false);

            \Illuminate\Support\Facades\Log::info('Prices recalculated for start_place ' . $startPlaceId . ' after availability change for event template: ' . $this->record->id);

            // Powiadomienie o przeliczeniu cen
            \Filament\Notifications\Notification::make()
                ->title('Dostępność zaktualizowana!')
                ->body('Ceny zostały przeliczone dla zmienionego miejsca startowego.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::error('Failed to recalculate prices after availability change for event template ' . $this->record->id . ': ' . $e->getMessage());

            // Powiadomienie o błędzie
            \Filament\Notifications\Notification::make()
                ->title('Błąd przeliczania cen!')
                ->body('Dostępność została zmieniona, ale wystąpił błąd podczas przeliczania cen.')
                ->warning()
                ->send();
        }

        $this->dispatch('$refresh');
    }

    public function updateAvailabilityNote($eventTemplateId, $startPlaceId, $endPlaceId, $note)
    {
        if (!$startPlaceId || !$endPlaceId) return;

        \App\Models\EventTemplateStartingPlaceAvailability::updateOrCreate([
            'event_template_id' => $eventTemplateId,
            'start_place_id' => $startPlaceId,
            'end_place_id' => $endPlaceId,
        ], [
            'note' => $note,
        ]);
    }
    public function getViewData(): array
    {
        $startPlaceId = $this->record->start_place_id;
        $endPlaceId = $this->record->end_place_id;

        $startingPlaces = \App\Models\Place::where('starting_place', true)->get();
        $startPlace = $this->record->startPlace;
        $endPlace = $this->record->endPlace;

        $toStartPairs = [];
        $fromEndPairs = [];
        $availabilities = [];

        foreach ($startingPlaces as $place) {
            $distance = null;
            if ($startPlaceId) {
                $distance = \App\Models\PlaceDistance::where('from_place_id', $place->id)
                    ->where('to_place_id', $startPlaceId)
                    ->first();
            }
            // Pobierz dostępność dla tej kombinacji
            $availability = \App\Models\EventTemplateStartingPlaceAvailability::where('event_template_id', $this->record->id)
                ->where('start_place_id', $place->id)
                ->where('end_place_id', $startPlaceId)
                ->first();
            $toStartPairs[] = [
                'from' => $place,
                'to' => $startPlace,
                'distance' => $distance?->distance_km,
                'availability' => $availability,
            ];
        }

        foreach ($startingPlaces as $place) {
            $distance = null;
            if ($endPlaceId) {
                $distance = \App\Models\PlaceDistance::where('from_place_id', $endPlaceId)
                    ->where('to_place_id', $place->id)
                    ->first();
            }
            $availability = \App\Models\EventTemplateStartingPlaceAvailability::where('event_template_id', $this->record->id)
                ->where('start_place_id', $endPlaceId)
                ->where('end_place_id', $place->id)
                ->first();
            $fromEndPairs[] = [
                'from' => $endPlace,
                'to' => $place,
                'distance' => $distance?->distance_km,
                'availability' => $availability,
            ];
        }

        return array_merge(parent::getViewData(), [
            'toStartPairs' => $toStartPairs,
            'fromEndPairs' => $fromEndPairs,
            'pricesData' => $this->getPricesData(),
        ]);
    }

    private function getPricesData(): array
    {
        $pricesData = [];
        $startingPlaces = \App\Models\Place::where('starting_place', true)->get();

        // Debug: sprawdź jakie miejsca startowe mamy
        \Illuminate\Support\Facades\Log::info("Getting prices data for template {$this->record->id}, found " . $startingPlaces->count() . " starting places");

        // Znajdź wszystkie polskie waluty (może być duplikatów)
        $polishCurrencyIds = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })->pluck('id')->toArray();

        \Illuminate\Support\Facades\Log::info("Found PLN currency IDs: " . json_encode($polishCurrencyIds));

        foreach ($startingPlaces as $place) {
            // Pobierz wszystkie ceny w polskich walutach dla tego miejsca
            $allPrices = \App\Models\EventTemplatePricePerPerson::with(['eventTemplateQty', 'currency'])
                ->where('event_template_id', $this->record->id)
                ->where('start_place_id', $place->id)
                ->whereIn('currency_id', $polishCurrencyIds)
                ->orderBy('event_template_qty_id')
                ->orderByDesc('id') // najnowszy rekord na górze
                ->get();

            // Grupuj po qty i wybierz tylko najnowszy rekord dla każdej ilości osób
            $pricesData[$place->id] = $allPrices->groupBy('event_template_qty_id')->map(function ($pricesForQty) {
                $latest = $pricesForQty->first();
                return [
                    'qty' => $latest->eventTemplateQty->qty ?? 0,
                    'price_per_person' => $latest->price_per_person,
                ];
            })->values()->toArray();
        }
        return $pricesData;
    }

    /**
     * Znajdź najlepszą polską walutę w systemie (zabezpieczone przed duplikatami)
     */
    private function findBestPolishCurrency(): ?\App\Models\Currency
    {
        // Najpierw znajdź polskie waluty, które faktycznie mają dane cenowe dla tego szablonu
        $polishCurrenciesWithData = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })
            ->whereHas('eventTemplatePrices', function ($q) {
                $q->where('event_template_id', $this->record->id);
            })
            ->orderBy('id') // Preferuj najniższe ID
            ->get();

        // Loguj znalezione waluty z danymi
        \Illuminate\Support\Facades\Log::info("Polish currencies with price data: " . $polishCurrenciesWithData->pluck('name', 'id')->toJson());

        // Jeśli są waluty z danymi, zwróć pierwszą
        if ($polishCurrenciesWithData->isNotEmpty()) {
            return $polishCurrenciesWithData->first();
        }

        // Fallback: zwróć pierwszą polską walutę (nawet bez danych)
        $fallbackCurrency = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })
            ->orderBy('id')
            ->first();

        \Illuminate\Support\Facades\Log::warning("No Polish currency with data found, using fallback: " . ($fallbackCurrency ? $fallbackCurrency->name : 'NONE'));

        return $fallbackCurrency;
    }
    use InteractsWithForms;

    protected static string $resource = EventTemplateResource::class;
    protected static string $view = 'filament.resources.event-template-resource.pages.event-template-transport';

    public EventTemplate $record;
    public ?array $data = [];

    public function mount($record): void
    {
        if (is_array($record) && isset($record['id'])) {
            $this->record = EventTemplate::findOrFail($record['id']);
        } elseif ($record instanceof EventTemplate) {
            $this->record = $record;
        } else {
            $this->record = EventTemplate::findOrFail($record);
        }

        // Sprawdź czy są przeliczone ceny, jeśli nie - przelicz je
        $pricesCount = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $this->record->id)->count();
        if ($pricesCount === 0) {
            // USUNIĘTO: Automatyczne przeliczanie cen starym kalkulatorem
            // Teraz ceny są przeliczane przez getDetailedCalculations() w widgetach
            Log::info('No prices found for event template: ' . $this->record->id . ' - prices will be calculated on demand');
        }

        $this->form->fill([
            'bus_id' => $this->record->bus_id,
            'program_km' => $this->record->program_km ?? 0,
            'start_place_id' => $this->record->start_place_id,
            'end_place_id' => $this->record->end_place_id,
            'transport_notes' => $this->record->transport_notes ?? '',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Wróć do edycji')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => static::getResource()::getUrl('edit', ['record' => $this->record->id]))
                ->color('gray'),
            Actions\Action::make('edit-program')
                ->label('Edytuj program')
                ->icon('heroicon-o-bars-3')
                ->url(fn() => static::getResource()::getUrl('edit-program', ['record' => $this->record->id]))
                ->color('primary'),
            Actions\Action::make('calculate-distances')
                ->label('Przelicz odległości')
                ->icon('heroicon-o-arrow-path')
                ->action('calculateDistances')
                ->color('warning'),
            Actions\Action::make('recalculate-prices')
                ->label('Przelicz ceny (ten szablon)')
                ->icon('heroicon-o-calculator')
                ->requiresConfirmation()
                ->action('recalculatePricesForThisTemplate')
                ->color('success'),
            Actions\Action::make('force-recalculate-prices')
                ->label('WYMUSZ przeliczenie cen')
                ->icon('heroicon-o-calculator')
                ->action('forceRecalculatePrices')
                ->color('danger'),
            Actions\Action::make('clean-duplicate-currencies')
                ->label('Pokaż duplikaty walut')
                ->icon('heroicon-o-exclamation-triangle')
                ->action('showDuplicateCurrencies')
                ->color('warning'),
        ];
    }

    public function recalculatePricesForThisTemplate(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            \Filament\Notifications\Notification::make()
                ->title('Brak użytkownika')
                ->body('Nie można zlecić przeliczenia bez zalogowanego użytkownika.')
                ->danger()
                ->send();
            return;
        }

        try {
            // Zainicjuj progress i zleć job
            \App\Services\PriceRecalcProgress::start($userId, 1);
            // powiadom widgety na stronie
            $this->emit('priceRecalcStarted');
            \App\Jobs\RecalculateSelectedEventTemplatePricesJob::dispatch([(int) $this->record->id], $userId)->afterResponse();

            \Filament\Notifications\Notification::make()
                ->title('Przeliczanie cen zlecone')
                ->body('Zadanie dla tego szablonu zostało dodane do kolejki i wykona się w tle.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            \Filament\Notifications\Notification::make()
                ->title('Nie udało się zlecić przeliczenia')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function calculateDistances()
    {
        $apiKey = '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
        $startPlaceId = $this->record->start_place_id;
        $endPlaceId = $this->record->end_place_id;

        if (!$startPlaceId || !$endPlaceId) {
            \Filament\Notifications\Notification::make()
                ->title('Brak miejsc początkowego lub końcowego!')
                ->danger()
                ->send();
            return;
        }

        $timeout = 8; // Krótszy timeout dla API
        $updated = 0;
        $errorPairs = [];
        $skipped = 0;
        $startTime = time();
        $maxExecutionTime = 25; // Maksymalny czas wykonania w sekundach

        // Znajdź wszystkie miejsca startowe
        $startingPlaces = \App\Models\Place::where('starting_place', true)->get();

        // Zbierz wszystkie pary do przeliczenia (tylko potrzebne kierunki)
        $missingPairs = [];

        // 1. Od miejsc startowych TYLKO do miejsca początkowego szablonu (tam)
        foreach ($startingPlaces as $from) {
            if ($from->id === $startPlaceId) continue; // Pomiń jeśli to to samo miejsce

            $existing = \App\Models\PlaceDistance::where('from_place_id', $from->id)
                ->where('to_place_id', $startPlaceId)
                ->first();

            if (!$existing || !$existing->distance_km) {
                $toPlace = \App\Models\Place::find($startPlaceId);
                if ($toPlace) {
                    $missingPairs[] = ['from' => $from, 'to' => $toPlace, 'type' => 'tam'];
                }
            }
        }

        // 2. Od miejsca końcowego szablonu TYLKO do miejsc startowych (powrót)
        $endPlace = \App\Models\Place::find($endPlaceId);
        if ($endPlace) {
            foreach ($startingPlaces as $to) {
                if ($endPlace->id === $to->id) continue; // Pomiń jeśli to to samo miejsce

                $existing = \App\Models\PlaceDistance::where('from_place_id', $endPlace->id)
                    ->where('to_place_id', $to->id)
                    ->first();

                if (!$existing || !$existing->distance_km) {
                    $missingPairs[] = ['from' => $endPlace, 'to' => $to, 'type' => 'powrót'];
                }
            }
        }

        $totalPairs = count($missingPairs);
        $maxPairsPerBatch = 10; // Maksymalnie 10 par na raz

        // Przetwarzaj w batches z kontrolą czasu
        $processedCount = 0;
        foreach ($missingPairs as $index => $pair) {
            // Sprawdź czas wykonania
            if ((time() - $startTime) >= $maxExecutionTime) {
                break; // Przerwij aby nie przekroczyć limitu czasu
            }

            // Przerwij po określonej liczbie par w tej sesji
            if ($processedCount >= $maxPairsPerBatch) {
                break;
            }

            $from = $pair['from'];
            $to = $pair['to'];

            try {
                // Sprawdź ponownie czy nie została już przeliczona
                $existing = \App\Models\PlaceDistance::where('from_place_id', $from->id)
                    ->where('to_place_id', $to->id)
                    ->first();

                if ($existing && $existing->distance_km) {
                    $skipped++;
                    continue;
                }

                $distance = $this->fetchDistanceWithTimeout($from, $to, $apiKey, $timeout);

                if ($distance !== null) {
                    \App\Models\PlaceDistance::updateOrCreate([
                        'from_place_id' => $from->id,
                        'to_place_id' => $to->id,
                    ], [
                        'distance_km' => $distance,
                        'api_source' => 'openrouteservice',
                    ]);
                    $updated++;
                } else {
                    $errorPairs[] = [
                        'from' => $from->name,
                        'to' => $to->name,
                        'error' => 'brak wyniku API'
                    ];
                }

                $processedCount++;

                // Dodaj pauzę między wywołaniami API
                if ($processedCount % 3 === 0) {
                    usleep(300000); // 0.3 sekundy co 3 wywołania
                }
            } catch (\Throwable $e) {
                $errorPairs[] = [
                    'from' => $from->name,
                    'to' => $to->name,
                    'error' => $e->getMessage()
                ];
                $processedCount++;
            }
        }

        // Sprawdź ile jeszcze zostało (tylko potrzebne kierunki)
        $stillMissing = 0;

        // 1. Od miejsc startowych TYLKO do miejsca początkowego szablonu (tam)
        foreach ($startingPlaces as $from) {
            if ($from->id === $startPlaceId) continue;
            $existing = \App\Models\PlaceDistance::where('from_place_id', $from->id)
                ->where('to_place_id', $startPlaceId)
                ->first();
            if (!$existing || !$existing->distance_km) {
                $stillMissing++;
            }
        }

        // 2. Od miejsca końcowego TYLKO do miejsc startowych (powrót)
        $endPlace = \App\Models\Place::find($endPlaceId);
        if ($endPlace) {
            foreach ($startingPlaces as $to) {
                if ($endPlace->id === $to->id) continue;
                $existing = \App\Models\PlaceDistance::where('from_place_id', $endPlace->id)
                    ->where('to_place_id', $to->id)
                    ->first();
                if (!$existing || !$existing->distance_km) {
                    $stillMissing++;
                }
            }
        }

        $executionTime = time() - $startTime;
        $timeoutReached = $executionTime >= $maxExecutionTime;

        // Przygotuj komunikat
        $message = $timeoutReached ? "Batch ukończony (limit czasu)" : "Przeliczono kompletnie!";
        if ($updated > 0) $message .= " Zapisano: {$updated} odległości.";
        if ($skipped > 0) $message .= " Pominięto: {$skipped} (już istniały).";
        if (count($errorPairs) > 0) $message .= " Błędy: " . count($errorPairs) . ".";
        if ($stillMissing > 0) $message .= " Pozostało: {$stillMissing}.";
        if ($timeoutReached) $message .= " Kliknij ponownie aby kontynuować.";

        \Filament\Notifications\Notification::make()
            ->title($message)
            ->body(json_encode([
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errorPairs,
                'remaining' => $stillMissing,
                'total_processed' => $processedCount,
                'batch_size' => $maxPairsPerBatch,
                'execution_time' => $executionTime,
                'timeout_reached' => $timeoutReached,
                'success_rate' => $processedCount > 0 ? round(($updated / $processedCount) * 100, 1) : 0
            ]))
            ->success()
            ->send();
    }

    public function forceRecalculatePrices()
    {
        try {
            // Usuń wszystkie istniejące ceny dla tego szablonu
            \App\Models\EventTemplatePricePerPerson::where('event_template_id', $this->record->id)->delete();
            \Illuminate\Support\Facades\Log::info("Deleted all existing prices for template {$this->record->id}");

            // Przelicz ponownie
            (new \App\Services\UnifiedPriceCalculator())->recalculateForTemplate($this->record);

            // Sprawdź rezultaty
            $totalPrices = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $this->record->id)->count();
            $pricesWithTransport = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $this->record->id)
                ->whereNotNull('transport_cost')
                ->where('transport_cost', '>', 0)
                ->count();

            \Filament\Notifications\Notification::make()
                ->title('Ceny zostały wymuszone!')
                ->body("Usunięto stare ceny i przeliczono od nowa. Łącznie: {$totalPrices} cen, z transportem: {$pricesWithTransport}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Błąd wymuszenia cen!')
                ->body('Błąd: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function showDuplicateCurrencies()
    {
        try {
            // Znajdź wszystkie waluty
            $allCurrencies = \App\Models\Currency::orderBy('id')->get();

            // Grupuj po podobnych nazwach
            $duplicates = [];
            $polishCurrencies = [];
            $euroCurrencies = [];
            $usdCurrencies = [];

            foreach ($allCurrencies as $currency) {
                $name = strtolower($currency->name ?? '');

                if (str_contains($name, 'polski') || str_contains($name, 'złoty')) {
                    $polishCurrencies[] = "ID: {$currency->id}, Name: '{$currency->name}'";
                } elseif (str_contains($name, 'euro')) {
                    $euroCurrencies[] = "ID: {$currency->id}, Name: '{$currency->name}'";
                } elseif (str_contains($name, 'dolar')) {
                    $usdCurrencies[] = "ID: {$currency->id}, Name: '{$currency->name}'";
                }
            }

            $message = "WYKRYTE DUPLIKATY WALUT:\n\n";

            if (count($polishCurrencies) > 1) {
                $message .= "🔴 POLSKIE ZŁOTE (" . count($polishCurrencies) . "):\n";
                foreach ($polishCurrencies as $curr) {
                    $message .= "- " . $curr . "\n";
                }
                $message .= "\n";
            }

            if (count($euroCurrencies) > 1) {
                $message .= "🔴 EURO (" . count($euroCurrencies) . "):\n";
                foreach ($euroCurrencies as $curr) {
                    $message .= "- " . $curr . "\n";
                }
                $message .= "\n";
            }

            if (count($usdCurrencies) > 1) {
                $message .= "🔴 DOLARY (" . count($usdCurrencies) . "):\n";
                foreach ($usdCurrencies as $curr) {
                    $message .= "- " . $curr . "\n";
                }
                $message .= "\n";
            }

            // Pokaż którą walutę system aktualnie używa
            $bestPLN = $this->findBestPolishCurrency();
            if ($bestPLN) {
                $message .= "✅ SYSTEM UŻYWA: ID {$bestPLN->id} - '{$bestPLN->name}'\n";
            }

            \Filament\Notifications\Notification::make()
                ->title('Analiza duplikatów walut')
                ->body($message)
                ->warning()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Błąd analizy walut!')
                ->body('Błąd: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function fetchDistanceWithTimeout($from, $to, $apiKey, $timeout = 5)
    {
        if (!$from->latitude || !$from->longitude || !$to->latitude || !$to->longitude) return null;
        $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $apiKey . '&start=' . $from->longitude . ',' . $from->latitude . '&end=' . $to->longitude . ',' . $to->latitude;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
            $response = file_get_contents($url, false, $ctx);
            $data = json_decode($response, true);
            if (isset($data['features'][0]['properties']['segments'][0]['distance'])) {
                return round($data['features'][0]['properties']['segments'][0]['distance'] / 1000, 2);
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Dane transportowe')
                    ->description('Zarządzaj transportem dla tego szablonu imprezy')
                    ->schema([
                        Select::make('bus_id')
                            ->label('Autobus')
                            ->options(Bus::all()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Wybierz autobus'),

                        TextInput::make('program_km')
                            ->label('Program (km)')
                            ->numeric()
                            ->default(0)
                            ->placeholder('Ilość kilometrów w realizacji programu'),

                        Select::make('start_place_id')
                            ->label('Miejsce początkowe')
                            ->options(fn() => \App\Models\Place::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->nullable()
                            ->placeholder('Wybierz miejsce początkowe'),

                        Select::make('end_place_id')
                            ->label('Miejsce końcowe')
                            ->options(fn() => \App\Models\Place::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->nullable()
                            ->placeholder('Wybierz miejsce końcowe'),

                        Textarea::make('transport_notes')
                            ->label('Notatki transportowe')
                            ->placeholder('Dodatkowe informacje o transporcie...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->record->update([
            'bus_id' => $data['bus_id'],
            'program_km' => $data['program_km'] ?? 0,
            'start_place_id' => $data['start_place_id'],
            'end_place_id' => $data['end_place_id'],
            'transport_notes' => $data['transport_notes'],
        ]);

        // Przelicz ponownie ceny po aktualizacji danych transportowych
        try {
            (new \App\Services\UnifiedPriceCalculator())->recalculateForTemplate($this->record);
            \Illuminate\Support\Facades\Log::info('Prices recalculated after transport update for event template: ' . $this->record->id);

            // Sprawdź czy zostały zapisane ceny z transportem
            $pricesWithTransport = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $this->record->id)
                ->whereNotNull('transport_cost')
                ->where('transport_cost', '>', 0)
                ->count();

            \Illuminate\Support\Facades\Log::info('Prices with transport cost found: ' . $pricesWithTransport);

            // Powiadomienie o przeliczeniu cen
            \Filament\Notifications\Notification::make()
                ->title('Ceny zostały przeliczone!')
                ->body("Ceny dla dostępnych kierunków zostały automatycznie zaktualizowane. Cen z transportem: {$pricesWithTransport}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to recalculate prices after transport update for event template ' . $this->record->id . ': ' . $e->getMessage());

            // Powiadomienie o błędzie
            \Filament\Notifications\Notification::make()
                ->title('Błąd przeliczania cen!')
                ->body('Wystąpił błąd podczas automatycznego przeliczania cen: ' . $e->getMessage())
                ->danger()
                ->send();
        }

        // Odśwież rekord i formularz, by wartości były widoczne od razu
        $this->record->refresh();
        $this->form->fill([
            'bus_id' => $this->record->bus_id,
            'program_km' => $this->record->program_km ?? 0,
            'start_place_id' => $this->record->start_place_id,
            'end_place_id' => $this->record->end_place_id,
            'transport_notes' => $this->record->transport_notes ?? '',
        ]);
        $this->getSavedNotification()?->send();
    }

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
            ->success()
            ->title('Zapisano!')
            ->body('Dane transportowe zostały zaktualizowane.');
    }

    public function getTitle(): string
    {
        return 'Transport - ' . $this->record->name;
    }
}

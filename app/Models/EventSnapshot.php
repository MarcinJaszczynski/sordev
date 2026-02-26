<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class EventSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'type',
        'name',
        'description',
        'event_data',
        'program_points',
        'calculations',
        'currency_rates',
    'template_prices_snapshot',
        'total_cost_snapshot',
        'created_by',
        'snapshot_date',
    ];

    protected $casts = [
        'event_data' => 'array',
        'program_points' => 'array',
        'calculations' => 'array',
        'currency_rates' => 'array',
    'template_prices_snapshot' => 'array',
        'total_cost_snapshot' => 'decimal:2',
        'snapshot_date' => 'datetime',
    ];

    /**
     * Impreza
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Użytkownik który utworzył snapshot
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Utwórz snapshot imprezy
     */
    public static function createSnapshot(Event $event, string $type = 'original', ?string $name = null, ?string $description = null, ?array $templatePrices = null): self
    {
        // Pobierz punkty programu z pełnymi danymi
        $programPoints = $event->programPoints()
            ->with('templatePoint')
            ->get()
            ->map(function ($point) {
                return [
                    'id' => $point->id,
                    'template_point_id' => $point->event_template_program_point_id,
                    'template_point_name' => $point->templatePoint->name,
                    'template_point_description' => $point->templatePoint->description,
                    'day' => $point->day,
                    'order' => $point->order,
                    'unit_price' => $point->unit_price,
                    'quantity' => $point->quantity,
                    'total_price' => $point->total_price,
                    'notes' => $point->notes,
                    'include_in_program' => $point->include_in_program,
                    'include_in_calculation' => $point->include_in_calculation,
                    'active' => $point->active,
                    'created_at' => $point->created_at,
                ];
            })->toArray();

        // Pobierz dane imprezy
        $eventData = [
            'name' => $event->name,
            'client_name' => $event->client_name,
            'client_email' => $event->client_email,
            'client_phone' => $event->client_phone,
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'participant_count' => $event->participant_count,
            'status' => $event->status,
            'notes' => $event->notes,
            'event_template_id' => $event->event_template_id,
            'event_template_name' => $event->eventTemplate->name,
            'assigned_to' => $event->assigned_to,
            'assigned_user_name' => $event->assignedUser?->name,
        ];

        // Pobierz kalkulacje (sumowanie kosztów)
        $allPoints = $event->programPoints()->with('templatePoint')->get();
        $activePoints = $allPoints->where('active', true);
        $includedForCalculation = \App\Services\ProgramPointHelper::filterIncluded($activePoints);

        $calculations = [
            'total_program_cost' => \App\Services\ProgramPointHelper::sumIncluded($activePoints, 'total_price'),
            'points_count' => $allPoints->count(),
            'active_points_count' => $activePoints->count(),
            'included_in_calculation_count' => \App\Services\ProgramPointHelper::countIncluded($activePoints),
            'cost_breakdown_by_day' => $includedForCalculation
                ->groupBy('day')
                ->map(function ($points) {
                    return [
                        'day_total' => $points->sum('total_price'),
                        'points_count' => $points->count(),
                        'points' => $points->map(function ($point) {
                            return [
                                'name' => $point->templatePoint->name,
                                'total_price' => $point->total_price,
                            ];
                        }),
                    ];
                }),
        ];

        // Pobierz aktualne kursy walut (jeśli są używane)
        $currencyRates = self::getCurrentCurrencyRates();

        // Określ nazwę snapshotu
        $snapshotName = $name ?? match($type) {
            'original' => 'Pierwotny stan imprezy',
            'manual' => 'Snapshot ręczny',
            'status_change' => 'Zmiana statusu',
            default => 'Snapshot',
        };

        return self::create([
            'event_id' => $event->id,
            'type' => $type,
            'name' => $snapshotName,
            'description' => $description,
            'event_data' => $eventData,
            'program_points' => $programPoints,
            'calculations' => $calculations,
            'currency_rates' => $currencyRates,
            'template_prices_snapshot' => $templatePrices,
            'total_cost_snapshot' => $event->total_cost,
            'created_by' => Auth::id(),
            'snapshot_date' => now(),
        ]);
    }

    /**
     * Pobierz aktualne kursy walut
     */
    private static function getCurrentCurrencyRates(): array
    {
        try {
            // Pobierz kursy z tabeli currencies
            $currencies = \App\Models\Currency::all();
            $rates = [];
            
            foreach ($currencies as $currency) {
                $rates[$currency->symbol] = [
                    'rate' => $currency->exchange_rate,
                    'name' => $currency->name,
                    'last_updated' => $currency->last_updated_at ?? $currency->updated_at,
                ];
            }
            
            return $rates;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Przywróć imprezę do stanu ze snapshotu
     */
    public function restoreToEvent(): void
    {
        $event = $this->event;
        
        // Zapisz obecny stan jako snapshot przed przywróceniem
        self::createSnapshot($event, 'manual', 'Backup przed przywróceniem', 'Automatyczny backup przed przywróceniem stanu z: ' . $this->name);
        
        // Przywróć dane imprezy (tylko wybrane pola)
        $event->update([
            'name' => $this->event_data['name'],
            'client_name' => $this->event_data['client_name'],
            'client_email' => $this->event_data['client_email'],
            'client_phone' => $this->event_data['client_phone'],
            'start_date' => $this->event_data['start_date'],
            'end_date' => $this->event_data['end_date'],
            'participant_count' => $this->event_data['participant_count'],
            'notes' => $this->event_data['notes'],
            'assigned_to' => $this->event_data['assigned_to'],
        ]);
        
        // Usuń obecne punkty programu
        $event->programPoints()->delete();
        
        // Odtwórz punkty programu ze snapshotu
        foreach ($this->program_points as $pointData) {
            EventProgramPoint::create([
                'event_id' => $event->id,
                'event_template_program_point_id' => $pointData['template_point_id'],
                'day' => $pointData['day'],
                'order' => $pointData['order'],
                'unit_price' => $pointData['unit_price'],
                'quantity' => $pointData['quantity'],
                'total_price' => $pointData['total_price'],
                'notes' => $pointData['notes'],
                'include_in_program' => $pointData['include_in_program'],
                'include_in_calculation' => $pointData['include_in_calculation'],
                'active' => $pointData['active'],
            ]);
        }
        
        // Przelicz koszt całkowity
        $event->calculateTotalCost();
        
        // Zapisz w historii
        $event->logHistory(
            'restored_from_snapshot',
            null,
            null,
            $this->toArray(),
            "Przywrócono stan imprezy ze snapshotu: {$this->name}"
        );
    }

    /**
     * Porównaj z obecnym stanem imprezy
     */
    public function compareWithCurrent(): array
    {
        $event = $this->event;
        $currentSnapshot = self::createTemporarySnapshot($event);
        
        return [
            'event_changes' => $this->compareEventData($currentSnapshot),
            'program_changes' => $this->compareProgramPoints($currentSnapshot),
            'cost_changes' => $this->compareCosts($currentSnapshot),
        ];
    }

    /**
     * Utwórz tymczasowy snapshot (bez zapisywania do bazy)
     */
    private static function createTemporarySnapshot(Event $event): array
    {
        $programPoints = $event->programPoints()
            ->with('templatePoint')
            ->get()
            ->map(function ($point) {
                return [
                    'template_point_name' => $point->templatePoint->name,
                    'day' => $point->day,
                    'order' => $point->order,
                    'unit_price' => $point->unit_price,
                    'quantity' => $point->quantity,
                    'total_price' => $point->total_price,
                    'include_in_calculation' => $point->include_in_calculation,
                    'active' => $point->active,
                ];
            })->toArray();

        return [
            'event_data' => [
                'name' => $event->name,
                'client_name' => $event->client_name,
                'participant_count' => $event->participant_count,
                'total_cost' => $event->total_cost,
            ],
            'program_points' => $programPoints,
        ];
    }

    /**
     * Porównaj dane imprezy
     */
    private function compareEventData($currentSnapshot): array
    {
        $changes = [];
        $fields = ['name', 'client_name', 'participant_count'];
        
        foreach ($fields as $field) {
            $oldValue = $this->event_data[$field] ?? null;
            $newValue = $currentSnapshot['event_data'][$field] ?? null;
            
            if ($oldValue != $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Porównaj punkty programu
     */
    private function compareProgramPoints($currentSnapshot): array
    {
        $oldPoints = collect($this->program_points);
        $newPoints = collect($currentSnapshot['program_points']);
        
        return [
            'removed' => $oldPoints->whereNotIn('template_point_name', $newPoints->pluck('template_point_name'))->values(),
            'added' => $newPoints->whereNotIn('template_point_name', $oldPoints->pluck('template_point_name'))->values(),
            'modified' => $this->getModifiedPoints($oldPoints, $newPoints),
        ];
    }

    /**
     * Porównaj koszty
     */
    private function compareCosts($currentSnapshot): array
    {
        return [
            'old_total' => $this->total_cost_snapshot,
            'new_total' => $currentSnapshot['event_data']['total_cost'],
            'difference' => $currentSnapshot['event_data']['total_cost'] - $this->total_cost_snapshot,
        ];
    }

    /**
     * Znajdź zmodyfikowane punkty
     */
    private function getModifiedPoints($oldPoints, $newPoints): array
    {
        $modified = [];
        
        foreach ($oldPoints as $oldPoint) {
            $newPoint = $newPoints->firstWhere('template_point_name', $oldPoint['template_point_name']);
            
            if ($newPoint) {
                $changes = [];
                if ($oldPoint['total_price'] != $newPoint['total_price']) {
                    $changes['total_price'] = [
                        'old' => $oldPoint['total_price'],
                        'new' => $newPoint['total_price'],
                    ];
                }
                
                if (!empty($changes)) {
                    $modified[] = [
                        'point_name' => $oldPoint['template_point_name'],
                        'changes' => $changes,
                    ];
                }
            }
        }
        
        return $modified;
    }

    /**
     * Pobierz czytelny typ snapshotu
     */
    public function getReadableTypeAttribute(): string
    {
        return match($this->type) {
            'original' => 'Pierwotny',
            'manual' => 'Ręczny',
            'status_change' => 'Zmiana statusu',
            default => ucfirst($this->type),
        };
    }
}

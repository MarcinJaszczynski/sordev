<?php

namespace App\Models;

use App\Services\EventCalculationSnapshotBuilder;
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
                $pointName = $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id);
                $pointDescription = $point->templatePoint?->description ?? $point->description ?? null;

                return [
                    'id' => $point->id,
                    'template_point_id' => $point->event_template_program_point_id,
                    'template_point_name' => $pointName,
                    'template_point_description' => $pointDescription,
                    'name' => $pointName,
                    'description' => $pointDescription,
                    'day' => $point->day,
                    'order' => $point->order,
                    'start_time' => $point->start_time,
                    'end_time' => $point->end_time,
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

        // Pobierz dane imprezy (bez notes — TipTap HTML „zapytania klienta” nie należy do migawki).
        $eventData = [
            'name' => $event->name,
            'client_name' => $event->client_name,
            'client_email' => $event->client_email,
            'client_phone' => $event->client_phone,
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'participant_count' => $event->participant_count,
            'status' => $event->status,
            'event_template_id' => $event->event_template_id,
            'event_template_name' => $event->eventTemplate?->name,
            'assigned_to' => $event->assigned_to,
            'assigned_user_name' => $event->assignedUser?->name,
        ];

        // Pełna kalkulacja bieżącego wariantu (SSoT: EventCalculationSnapshotBuilder).
        $calculations = app(EventCalculationSnapshotBuilder::class)
            ->buildPersistableCalculation($event);

        // Pobierz aktualne kursy walut (jeśli są używane)
        $currencyRates = self::getCurrentCurrencyRates();

        // Określ nazwę snapshotu
        $snapshotName = $name ?? match ($type) {
            'original' => 'Pierwotny stan imprezy',
            'manual' => 'Snapshot ręczny',
            'status_change' => 'Zmiana statusu',
            default => 'Snapshot',
        };

        $totalCostSnapshot = (float) ($calculations['summary']['total_cost'] ?? $event->total_cost ?? 0);

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
            'total_cost_snapshot' => $totalCostSnapshot,
            'created_by' => Auth::id(),
            'snapshot_date' => now(),
        ]);
    }

    /**
     * Cena za osobę zamrożona w migawce (v2) albo null dla starych rekordów.
     */
    public function pricePerPersonSnapshot(): ?float
    {
        $summary = $this->calculations['summary'] ?? null;
        if (! is_array($summary)) {
            return null;
        }

        if (array_key_exists('price_per_person_rounded', $summary) && $summary['price_per_person_rounded'] !== null) {
            return (float) $summary['price_per_person_rounded'];
        }

        if (array_key_exists('price_per_person', $summary) && $summary['price_per_person'] !== null) {
            return (float) $summary['price_per_person'];
        }

        return null;
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
        self::createSnapshot($event, 'manual', 'Backup przed przywróceniem', 'Automatyczny backup przed przywróceniem stanu z: '.$this->name);

        // Przywróć dane imprezy (tylko wybrane pola).
        // notes celowo poza migawką — nie nadpisujemy zapytania klienta przy restore.
        $event->update([
            'name' => $this->event_data['name'],
            'client_name' => $this->event_data['client_name'],
            'client_email' => $this->event_data['client_email'],
            'client_phone' => $this->event_data['client_phone'],
            'start_date' => $this->event_data['start_date'],
            'end_date' => $this->event_data['end_date'],
            'participant_count' => $this->event_data['participant_count'],
            'assigned_to' => $this->event_data['assigned_to'],
        ]);

        // Usuń obecne punkty programu
        $event->programPoints()->delete();

        // Odtwórz punkty programu ze snapshotu
        foreach ($this->program_points as $pointData) {
            EventProgramPoint::create([
                'event_id' => $event->id,
                'event_template_program_point_id' => $pointData['template_point_id'],
                'name' => $pointData['name'] ?? $pointData['template_point_name'] ?? null,
                'description' => $pointData['description'] ?? $pointData['template_point_description'] ?? null,
                'day' => $pointData['day'],
                'order' => $pointData['order'],
                'start_time' => $pointData['start_time'] ?? null,
                'end_time' => $pointData['end_time'] ?? null,
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
                $pointName = $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id);

                return [
                    'template_point_name' => $pointName,
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

                if (! empty($changes)) {
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
        return match ($this->type) {
            'original' => 'Pierwotny',
            'manual' => 'Ręczny',
            'status_change' => 'Zmiana statusu',
            default => ucfirst($this->type),
        };
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventProgramPoint extends Model
{
    use HasFactory, HasTasks;

    protected $fillable = [
        'event_id',
        'event_template_program_point_id',
        'name',
        'description',
        'office_notes',
        'pilot_notes',
        'day',
        'order',
        'start_time',
        'end_time',
        'duration_hours',
        'duration_minutes',
        'featured_image',
        'gallery_images',
        'unit_price',
        'quantity',
        'total_price',
        'calculated_price',
        'planned_price',
        'paid_price',
        'notes',
        'include_in_program',
        'include_in_calculation',
        'active',
        'show_title_style',
        'show_description',
        'group_size',
        'currency_id',
        'convert_to_pln',
        'parent_id',
        'contractor_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'planned_price' => 'decimal:2',
        'paid_price' => 'decimal:2',
        'include_in_program' => 'boolean',
        'include_in_calculation' => 'boolean',
        'active' => 'boolean',
        'show_title_style' => 'boolean',
        'show_description' => 'boolean',
        'gallery_images' => 'array',
        'convert_to_pln' => 'boolean',
    ];

    /**
     * Parent program point (for hierarchical structure)
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child program points
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    protected static function booted()
    {
        static::saving(function ($point) {
            $storedQuantity = max(1, (int) ($point->quantity ?? 1));
            $groupSize = max(1, (int) ($point->group_size ?? 1));
            $participants = max(1, (int) ($point->event?->participant_count ?? 1));

            if (($point->group_size ?? null) !== null && $storedQuantity <= 1) {
                $point->quantity = max(1, (int) ceil($participants / $groupSize));
            }

            // Automatycznie oblicz total_price
            $point->total_price = ($point->unit_price ?? 0) * ($point->quantity ?? 1);

            // Wylicz cenę kalkulacji na podstawie szablonu (jeśli istnieje)
            if ($point->templatePoint) {
                $templateUnit = (float) ($point->templatePoint->unit_price ?? 0);
                $templateQty = max(1, (int) ($point->quantity ?? 1));
                $point->calculated_price = $templateUnit * $templateQty;
            } else {
                $point->calculated_price = null;
            }

            // Domyślnie planned_price = calculated_price jeśli nie nadpisano
            if (is_null($point->planned_price) || $point->planned_price == 0) {
                $point->planned_price = $point->calculated_price;
            }
        });

        static::updated(function ($point) {
            $event = $point->event;
            if (! $event) {
                return;
            }

            $changes = $point->getChanges();
            $pointName = self::resolvePointName($point);

            foreach ($changes as $field => $newValue) {
                $oldValue = $point->getOriginal($field);
                $event->logHistory(
                    'program_changed',
                    "program_point.{$field}",
                    $oldValue,
                    $newValue,
                    "Zmieniono {$field} w punkcie programu: {$pointName}"
                );
            }

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
            $event->refreshActiveSettlementCosts();
        });

        static::created(function ($point) {
            $event = $point->event;
            if (! $event) {
                return;
            }

            $event->logHistory(
                'program_added',
                null,
                null,
                $point->toArray(),
                'Dodano punkt programu: '.self::resolvePointName($point)
            );

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
            $event->refreshActiveSettlementCosts();
        });

        static::deleted(function ($point) {
            $event = $point->event;
            if (! $event) {
                return;
            }

            $event->logHistory(
                'program_removed',
                null,
                $point->toArray(),
                null,
                'Usunięto punkt programu: '.self::resolvePointName($point)
            );

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
            $event->refreshActiveSettlementCosts();
        });
    }

    /**
     * Impreza
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Szablon punktu programu
     */
    public function templatePoint(): BelongsTo
    {
        return $this->belongsTo(EventTemplateProgramPoint::class, 'event_template_program_point_id');
    }

    /**
     * Waluta punktu programu
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Kontrahent (wykonawca) punktu programu
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * Rezerwacje dla tego punktu programu
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'program_point_id');
    }

    /**
     * Oblicz koszt całkowity na podstawie ceny jednostkowej i ilości
     */
    public function calculateTotalPrice(): void
    {
        $this->total_price = ($this->unit_price ?? 0) * ($this->quantity ?? 1);
        $this->save();
    }

    public function resolveCalculatedQuantity(?int $participantCount = null): int
    {
        $storedQuantity = max(1, (int) ($this->quantity ?? 1));
        $groupSize = max(1, (int) ($this->group_size ?? 1));
        $count = max(1, (int) ($participantCount ?? $this->event?->participant_count ?? 1));
        $calculatedQuantity = max(1, (int) ceil($count / $groupSize));

        if ($storedQuantity > 1) {
            return $storedQuantity;
        }

        return $calculatedQuantity;
    }

    public function resolveEffectiveTotalPrice(?int $participantCount = null): float
    {
        $unitPrice = (float) ($this->unit_price ?? 0);
        $storedQuantity = max(1, (int) ($this->quantity ?? 1));
        $storedTotal = (float) ($this->total_price ?? ($unitPrice * $storedQuantity));
        $calculatedQuantity = $this->resolveCalculatedQuantity($participantCount);
        $isTemplateBasedPoint = ! blank($this->event_template_program_point_id);
        $looksLikeLegacySingleUnit = $isTemplateBasedPoint
            && $storedQuantity <= 1
            && abs($storedTotal - $unitPrice) < 0.01
            && $calculatedQuantity > 1;

        if ($looksLikeLegacySingleUnit) {
            return round($unitPrice * $calculatedQuantity, 2);
        }

        return round($storedTotal, 2);
    }

    /**
     * Duplikuj punkt programu
     */
    public function duplicate(): self
    {
        return self::create([
            'event_id' => $this->event_id,
            'event_template_program_point_id' => $this->event_template_program_point_id,
            'day' => $this->day,
            'order' => $this->getNextOrderInDay(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'total_price' => $this->total_price,
            'notes' => $this->notes,
            'include_in_program' => $this->include_in_program,
            'include_in_calculation' => $this->include_in_calculation,
            'active' => $this->active,
            'show_title_style' => $this->show_title_style,
            'show_description' => $this->show_description,
        ]);
    }

    /**
     * Pobierz następny numer kolejności w danym dniu
     */
    private function getNextOrderInDay(): int
    {
        $maxOrder = self::where('event_id', $this->event_id)
            ->where('day', $this->day)
            ->max('order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Przenieś do innego dnia
     */
    public function moveToDay(int $newDay): void
    {
        $oldDay = $this->day;
        $this->day = $newDay;
        $this->order = $this->getNextOrderInDay();
        $this->save();

        if (! $this->event) {
            return;
        }

        $this->event->logHistory(
            'program_moved',
            'program_point.day',
            $oldDay,
            $newDay,
            "Przeniesiono punkt programu '".self::resolvePointName($this)."' z dnia {$oldDay} do dnia {$newDay}"
        );
    }

    private static function resolvePointName(self $point): string
    {
        return $point->name
            ?? $point->templatePoint?->name
            ?? 'Bez nazwy';
    }
}

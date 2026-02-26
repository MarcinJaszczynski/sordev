<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class EventProgramPoint extends Model
{
    use HasFactory;

    protected $fillable = [
    'event_id',
    'event_template_program_point_id',
    'name',
    'description',
    'office_notes',
    'pilot_notes',
    'day',
    'order',
    'duration_hours',
    'duration_minutes',
    'featured_image',
    'gallery_images',
    'unit_price',
    'quantity',
    'total_price',
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
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
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
            // Automatycznie oblicz total_price
            $point->total_price = ($point->unit_price ?? 0) * ($point->quantity ?? 1);
        });

        static::updated(function ($point) {
            $event = $point->event;
            $changes = $point->getChanges();
            
            foreach ($changes as $field => $newValue) {
                $oldValue = $point->getOriginal($field);
                $event->logHistory(
                    'program_changed', 
                    "program_point.{$field}", 
                    $oldValue, 
                    $newValue, 
                    "Zmieniono {$field} w punkcie programu: {$point->templatePoint->name}"
                );
            }

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
        });

        static::created(function ($point) {
            $event = $point->event;
            $event->logHistory(
                'program_added',
                null,
                null,
                $point->toArray(),
                "Dodano punkt programu: {$point->templatePoint->name}"
            );

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
        });

        static::deleted(function ($point) {
            $event = $point->event;
            $event->logHistory(
                'program_removed',
                null,
                $point->toArray(),
                null,
                "Usunięto punkt programu: {$point->templatePoint->name}"
            );

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
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
     * Oblicz koszt całkowity na podstawie ceny jednostkowej i ilości
     */
    public function calculateTotalPrice(): void
    {
        $this->total_price = ($this->unit_price ?? 0) * ($this->quantity ?? 1);
        $this->save();
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

        $this->event->logHistory(
            'program_moved',
            'program_point.day',
            $oldDay,
            $newDay,
            "Przeniesiono punkt programu '{$this->templatePoint->name}' z dnia {$oldDay} do dnia {$newDay}"
        );
    }
}

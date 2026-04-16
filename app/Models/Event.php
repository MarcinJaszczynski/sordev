<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Contractor;
use App\Models\Place;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasTasks;

    public const STATUS_INQUIRY = 'inquiry';
    public const STATUS_OFFER = 'offer';
    public const STATUS_PROVISIONAL_RESERVATION = 'provisional_reservation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_TO_SETTLE = 'to_settle';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_PENDING_CANCELLATION = 'pending_cancellation';
    public const STATUS_CANCELLED = 'cancelled';

    public const LEGACY_STATUS_MIGRATION_MAP = [
        'draft' => self::STATUS_INQUIRY,
        'confirmed' => self::STATUS_CONFIRMED,
        'in_progress' => self::STATUS_TO_SETTLE,
        'completed' => self::STATUS_SETTLED,
        'cancelled' => self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'event_template_id',
        'start_place_id',
        'contractor_id',
        'name',
        'client_name',
        'client_email',
        'client_phone',
        'start_date',
        'end_date',
        'duration_days',
        'transfer_km',
        'program_km',
        'bus_id',
        'markup_id',
        'participant_count',
        'total_cost',
        'status',
        'notes',
        'office_notes',
        'hotel_notes',
        'pilot_notes',
        'driver_notes',
        'departure_time',
        'transport_company_name',
        'driver_name',
        'driver_phone',
        'vehicle_registration',
        'pickup_place_details',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_cost' => 'decimal:2',
    ];

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_INQUIRY => 'Zapytanie',
            self::STATUS_OFFER => 'Oferta',
            self::STATUS_PROVISIONAL_RESERVATION => 'Wstępna rezerwacja',
            self::STATUS_CONFIRMED => 'Potwierdzona',
            self::STATUS_TO_SETTLE => 'Do rozliczenia',
            self::STATUS_SETTLED => 'Rozliczona',
            self::STATUS_PENDING_CANCELLATION => 'Do anulacji',
            self::STATUS_CANCELLED => 'Anulowana',
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            'gray' => self::STATUS_INQUIRY,
            'info' => self::STATUS_OFFER,
            'warning' => self::STATUS_PROVISIONAL_RESERVATION,
            'success' => self::STATUS_CONFIRMED,
            'primary' => self::STATUS_TO_SETTLE,
            'secondary' => self::STATUS_SETTLED,
            'danger' => [self::STATUS_PENDING_CANCELLATION, self::STATUS_CANCELLED],
        ];
    }

    public static function getEditableStatuses(): array
    {
        return [
            self::STATUS_INQUIRY,
            self::STATUS_OFFER,
            self::STATUS_PROVISIONAL_RESERVATION,
            self::STATUS_CONFIRMED,
        ];
    }

    public static function getDeletableStatuses(): array
    {
        return [
            self::STATUS_INQUIRY,
            self::STATUS_OFFER,
        ];
    }

    public static function getSnapshotTriggerStatuses(): array
    {
        return [
            self::STATUS_CONFIRMED,
            self::STATUS_TO_SETTLE,
            self::STATUS_SETTLED,
            self::STATUS_PENDING_CANCELLATION,
            self::STATUS_CANCELLED,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    protected static function booted()
    {

        static::creating(function ($event) {
            $event->created_by = Auth::id();

            // Generuj unikalny kod imprezy: YY-LOSOWE6
            if (empty($event->code)) {
                $year = now()->format('y');
                do {
                    $random = strtoupper(Str::random(6));
                    $code = $year . '-' . $random;
                } while (self::where('code', $code)->exists());
                $event->code = $code;
            }
        });

        static::created(function ($event) {
            $event->logHistory('created', null, null, $event->toArray(), 'Impreza została utworzona');
        });

        static::updated(function ($event) {
            $changes = $event->getChanges();
            foreach ($changes as $field => $newValue) {
                $oldValue = $event->getOriginal($field);
                $event->logHistory('updated', $field, $oldValue, $newValue, "Zmieniono {$field}");
            }
        });
    }

    /**
     * Szablon imprezy
     */
    public function eventTemplate(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class);
    }

    /**
     * Kontrahent powiązany z imprezą
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
    /**
     * Miejsce startu imprezy
     */
    public function startPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }
    
    /**
     * Twórca imprezy
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Przypisany użytkownik
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Punkty programu dla tej imprezy
     */
    public function programPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class);
    }

    /**
     * Historia zmian
     */
    public function history(): HasMany
    {
        return $this->hasMany(EventHistory::class);
    }

    /**
     * Snapshoty imprezy
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(EventSnapshot::class);
    }

    /**
     * Pierwotny snapshot imprezy
     */
    public function originalSnapshot(): HasOne
    {
        return $this->hasOne(EventSnapshot::class)->where('type', 'original');
    }

    /**
     * Warianty ilości przypisane do imprezy
     */
    public function qtyVariants(): HasMany
    {
        return $this->hasMany(EventQty::class);
    }

    public function resolveGratisCountForParticipantCount(?int $participantCount = null): int
    {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));

        if ($this->relationLoaded('qtyVariants')) {
            $variant = $this->qtyVariants
                ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $count))
                ->first();

            return max(0, (int) ($variant->gratis ?? 0));
        }

        $variant = $this->qtyVariants()
            ->orderByRaw('ABS(qty - ?)', [$count])
            ->first();

        return max(0, (int) ($variant->gratis ?? 0));
    }

    /**
     * Zsynchronizuj wariant ilościowy eventu dla podanej grupy.
     * Aktualizuje gratisy dla wariantu o dokładnym qty, a jeśli go brak —
     * tworzy nowy wariant bazując na najbliższym istniejącym.
     */
    public function syncQtyVariantForGroup(int $participantCount, int $gratisCount): void
    {
        $participantCount = max(1, $participantCount);
        $gratisCount = max(0, $gratisCount);

        $exactVariant = $this->qtyVariants()
            ->where('qty', $participantCount)
            ->orderBy('id')
            ->first();

        if ($exactVariant) {
            $exactVariant->update([
                'gratis' => $gratisCount,
            ]);

            return;
        }

        $closestVariant = $this->qtyVariants()
            ->get()
            ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $participantCount))
            ->first();

        $this->qtyVariants()->create([
            'qty' => $participantCount,
            'gratis' => $gratisCount,
            'staff' => (int) ($closestVariant->staff ?? 1),
            'driver' => (int) ($closestVariant->driver ?? 1),
        ]);
    }

    /**
     * Price per person dla konkretnej imprezy
     */
    public function pricePerPerson(): HasMany
    {
        return $this->hasMany(EventPricePerPerson::class);
    }

    /**
     * Ubezpieczenia dniowe przypisane do imprezy
     */
    public function dayInsurances(): HasMany
    {
        return $this->hasMany(EventDayInsurance::class);
    }

    /**
     * Dostępność miejsc startowych dla imprezy
     */
    public function startingPlaceAvailabilities(): HasMany
    {
        return $this->hasMany(EventStartingPlaceAvailability::class);
    }

    /**
     * Utwórz imprezę na podstawie szablonu
     */
    public static function createFromTemplate(EventTemplate $template, array $data): self
    {
        // Oblicz duration_days oraz end_date na podstawie dat lub użyj wartości domyślnych z szablonu.
        $durationDays = max(1, (int) ($template->duration_days ?? 1));
        $startDate = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date']) : null;
        $endDate = ! empty($data['end_date']) ? \Carbon\Carbon::parse($data['end_date']) : null;

        if ($startDate && $endDate) {
            $durationDays = max(1, $startDate->diffInDays($endDate) + 1);
        }

        if ($startDate && ! $endDate) {
            $endDate = $startDate->copy()->addDays($durationDays - 1);
        }

        $eventPayload = [
            'event_template_id' => $template->id,
            'start_place_id' => $data['start_place_id'] ?? $template->start_place_id ?? null,
            'name' => $data['name'],
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'] ?? null,
            'client_phone' => $data['client_phone'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $endDate?->toDateString(),
            'duration_days' => $durationDays,
            'transfer_km' => $data['transfer_km'] ?? $template->transfer_km ?? 0,
            'program_km' => $data['program_km'] ?? $template->program_km ?? 0,
            'bus_id' => $data['bus_id'] ?? $template->bus_id,
            'markup_id' => $data['markup_id'] ?? $template->markup_id,
            'participant_count' => $data['participant_count'] ?? 1,
            'total_cost' => $data['total_cost'] ?? 0,
            'status' => $data['status'] ?? self::STATUS_INQUIRY,
            'assigned_to' => $data['assigned_to'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if (Schema::hasColumn('events', 'office_notes')) {
            $eventPayload['office_notes'] = $data['office_notes'] ?? null;
        }

        if (Schema::hasColumn('events', 'hotel_notes')) {
            $eventPayload['hotel_notes'] = $data['hotel_notes'] ?? null;
        }

        if (Schema::hasColumn('events', 'pilot_notes')) {
            $eventPayload['pilot_notes'] = $data['pilot_notes'] ?? null;
        }

        if (Schema::hasColumn('events', 'driver_notes')) {
            $eventPayload['driver_notes'] = $data['driver_notes'] ?? null;
        }

        if (Schema::hasColumn('events', 'departure_time')) {
            $eventPayload['departure_time'] = $data['departure_time'] ?? null;
        }

        if (Schema::hasColumn('events', 'transport_company_name')) {
            $eventPayload['transport_company_name'] = $data['transport_company_name'] ?? null;
        }

        if (Schema::hasColumn('events', 'driver_name')) {
            $eventPayload['driver_name'] = $data['driver_name'] ?? null;
        }

        if (Schema::hasColumn('events', 'driver_phone')) {
            $eventPayload['driver_phone'] = $data['driver_phone'] ?? null;
        }

        if (Schema::hasColumn('events', 'vehicle_registration')) {
            $eventPayload['vehicle_registration'] = $data['vehicle_registration'] ?? null;
        }

        if (Schema::hasColumn('events', 'pickup_place_details')) {
            $eventPayload['pickup_place_details'] = $data['pickup_place_details'] ?? null;
        }

        if (Schema::hasColumn('events', 'contractor_id')) {
            $eventPayload['contractor_id'] = $data['contractor_id'] ?? null;
        }

        $event = self::create($eventPayload);

        // Skopiuj punkty programu z szablonu (w tym podpunkty)
        $event->copyProgramPointsFromTemplate();

        // Kopiuj warianty ilości (qty) z szablonu do event-scoped
        try {
            foreach ($template->qtyVariants()->get() as $qty) {
                \App\Models\EventQty::create([
                    'event_id' => $event->id,
                    'qty' => $qty->qty,
                    'gratis' => $qty->gratis,
                    'staff' => $qty->staff,
                    'driver' => $qty->driver,
                ]);
            }
        } catch (\Throwable $e) {
            // ignore if table missing or other issues
        }

        if (array_key_exists('gratis_count', $data)) {
            try {
                $event->syncQtyVariantForGroup(
                    (int) ($data['participant_count'] ?? 1),
                    (int) ($data['gratis_count'] ?? 0)
                );
            } catch (\Throwable $e) {
                // ignore qty sync failures
            }
        }

        // Kopiuj ceny per person (jeśli istnieją) do event-scoped table
        try {
            $templatePrices = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $template->id)->get();
            foreach ($templatePrices as $tp) {
                \App\Models\EventPricePerPerson::create([
                    'event_id' => $event->id,
                    'event_template_qty_id' => $tp->event_template_qty_id,
                    'currency_id' => $tp->currency_id,
                    'start_place_id' => $tp->start_place_id,
                    'price_per_person' => $tp->price_per_person,
                    'transport_cost' => $tp->transport_cost,
                    'price_base' => $tp->price_base,
                    'markup_amount' => $tp->markup_amount,
                    'tax_amount' => $tp->tax_amount,
                    'price_with_tax' => $tp->price_with_tax,
                    'tax_breakdown' => $tp->tax_breakdown,
                ]);
            }
        } catch (\Throwable $e) {
            // ignore if no template prices
        }

        // Kopiuj ubezpieczenia dniowe
        try {
            foreach ($template->dayInsurances()->get() as $di) {
                \App\Models\EventDayInsurance::create([
                    'event_id' => $event->id,
                    'day' => $di->day,
                    'insurance_id' => $di->insurance_id,
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Kopiuj dostępność miejsc startowych
        try {
            foreach ($template->startingPlaceAvailabilities()->get() as $spa) {
                \App\Models\EventStartingPlaceAvailability::create([
                    'event_id' => $event->id,
                    'start_place_id' => $spa->start_place_id,
                    'end_place_id' => $spa->end_place_id,
                    'available' => $spa->available,
                    'note' => $spa->note,
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Utwórz pierwotny snapshot imprezy
        // Przygotuj uproszczony zrzut cen szablonu dla tej lokalizacji startu i standardowych qty
        $templatePricesSnapshot = null;
        try {
            $startId = $event->start_place_id ?? null;
            // Weź tylko najczęściej używane qty (10,20,30) lub te z EventTemplateQty
            $standardQtys = [10,20,30];
            $qtyIds = \App\Models\EventTemplateQty::whereIn('qty', $standardQtys)->pluck('id')->toArray();

            $templatePricesSnapshot = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $template->id)
                ->where(function($q) use ($startId) {
                    if ($startId) {
                        $q->where('start_place_id', $startId);
                    } else {
                        $q->whereNull('start_place_id');
                    }
                })
                ->whereIn('event_template_qty_id', $qtyIds)
                ->orderBy('event_template_qty_id')
                ->get()
                ->map(function($p) {
                    return [
                        'qty' => $p->eventTemplateQty?->qty ?? null,
                        'price_per_person' => $p->price_per_person,
                        'price_with_tax' => $p->price_with_tax,
                        'transport_cost' => $p->transport_cost,
                        'currency_id' => $p->currency_id,
                        'start_place_id' => $p->start_place_id,
                    ];
                })->toArray();
        } catch (\Throwable $e) {
            $templatePricesSnapshot = null;
        }

        EventSnapshot::createSnapshot(
            $event, 
            'original', 
            'Pierwotny stan imprezy',
            'Automatycznie utworzony snapshot w momencie tworzenia imprezy na podstawie szablonu: ' . $template->name,
            $templatePricesSnapshot
        );

        // Wykonaj wstępną kalkulację per-event aby zapisać event-scoped ceny
        try {
            // Preferuj dokładny engine używany dla szablonów, aby uzyskać zgodność kalkulacji
            $engine = new \App\Services\EventTemplateCalculationEngine();
            $detailed = $engine->calculateDetailed($template, $data['start_place_id'] ?? $template->start_place_id ?? null, $data['transfer_km'] ?? null);

            if (!empty($detailed)) {
                // Usuń ewentualne stare wpisy (bezpieczny przebieg)
                \App\Models\EventPricePerPerson::where('event_id', $event->id)->delete();

                foreach ($detailed as $qty => $row) {
                    \App\Models\EventPricePerPerson::create([
                        'event_id' => $event->id,
                        'event_template_qty_id' => $row['event_template_qty_id'] ?? null,
                        'currency_id' => null, // engine returns PLN totals; keep null or set to PLN
                        'start_place_id' => $event->start_place_id ?? null,
                        'price_per_person' => $row['price_per_person'] ?? 0,
                        'transport_cost' => $row['transport_cost'] ?? null,
                        'price_base' => $row['price_base'] ?? null,
                        'markup_amount' => $row['markup_amount'] ?? null,
                        'tax_amount' => $row['tax_amount'] ?? null,
                        'price_with_tax' => $row['price_with_tax'] ?? null,
                        'tax_breakdown' => $row['debug']['taxes'] ?? null,
                    ]);
                }
            } else {
                // fallback to simple per-event calculator
                $calculator = new \App\Services\EventPriceCalculator();
                $calculator->calculateForEvent($event);
            }
        } catch (\Throwable $e) {
            // fallback to simple calculator on any failure
            try {
                $calculator = new \App\Services\EventPriceCalculator();
                $calculator->calculateForEvent($event);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $event;
    }

    /**
     * Skopiuj punkty programu z szablonu (w tym podpunkty)
     */
    public function copyProgramPointsFromTemplate(): void
    {
        if (! $this->eventTemplate) {
            return;
        }

        // Log start of copy for debugging
        try {
            \Illuminate\Support\Facades\Log::info('copyProgramPointsFromTemplate:start', ['event_id' => $this->id, 'event_template_id' => $this->event_template_id ?? null]);
        } catch (\Throwable $_) {
            // ignore logging issues
        }

        $templatePoints = $this->eventTemplate->programPoints()
            ->with(['children', 'currency'])
            ->withPivot(['day', 'order', 'notes', 'start_time', 'end_time', 'include_in_program', 'include_in_calculation', 'active'])
            ->get();

        // Map to keep track of created main points so children can reference parent_id
        $createdPointsByTemplateId = [];

        foreach ($templatePoints as $point) {
            $unitPrice = $this->convertToEventCurrency($point->unit_price ?? 0, $point->currency);
            $groupSize = max(1, (int) ($point->group_size ?? 1));
            $quantity = max(1, (int) ceil(max(1, (int) ($this->participant_count ?? 1)) / $groupSize));

            // Skopiuj główny punkt programu z wszystkimi polami
            $mainPoint = EventProgramPoint::create([
                'event_id' => $this->id,
                'event_template_program_point_id' => $point->id,
                'name' => $point->name ?? null,
                'description' => $point->description ?? null,
                'office_notes' => $point->office_notes ?? null,
                'pilot_notes' => $point->pilot_notes ?? null,
                'day' => $point->pivot->day,
                'order' => $point->pivot->order,
                'start_time' => $point->pivot->start_time,
                'end_time' => $point->pivot->end_time,
                'duration_hours' => $point->duration_hours ?? null,
                'duration_minutes' => $point->duration_minutes ?? null,
                'featured_image' => $point->featured_image ?? null,
                'gallery_images' => is_array($point->gallery_images) ? json_encode(array_values($point->gallery_images), JSON_UNESCAPED_SLASHES) : ($point->gallery_images ?? null),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_price' => $unitPrice * $quantity,
                'notes' => $point->pivot->notes ?? null,
                'include_in_program' => $point->pivot->include_in_program ?? true,
                'include_in_calculation' => $point->pivot->include_in_calculation ?? true,
                'active' => $point->pivot->active ?? true,
                'show_title_style' => $point->pivot->show_title_style ?? true,
                'show_description' => $point->pivot->show_description ?? true,
                'group_size' => $point->group_size ?? null,
                'currency_id' => $point->currency_id ?? null,
                'convert_to_pln' => $point->convert_to_pln ?? false,
            ]);

            $createdPointsByTemplateId[$point->id] = $mainPoint->id;

            // Skopiuj podpunkty jako równorzędne punkty (bez parent_id)
            if ($point->children && $point->children->count() > 0) {
                $childOrder = $point->pivot->order + 0.1;
                foreach ($point->children as $child) {
                    $childUnitPrice = $this->convertToEventCurrency($child->unit_price ?? 0, $child->currency);
                    $childGroupSize = max(1, (int) ($child->group_size ?? 1));
                    $childQuantity = max(1, (int) ceil(max(1, (int) ($this->participant_count ?? 1)) / $childGroupSize));

                    $childPoint = EventProgramPoint::create([
                        'event_id' => $this->id,
                        'event_template_program_point_id' => $child->id,
                        'name' => $child->name ?? null,
                        'description' => $child->description ?? null,
                        'office_notes' => $child->office_notes ?? null,
                        'pilot_notes' => $child->pilot_notes ?? null,
                        'day' => $point->pivot->day,
                        'order' => $childOrder,
                        'start_time' => null,
                        'end_time' => null,
                        'duration_hours' => $child->duration_hours ?? null,
                        'duration_minutes' => $child->duration_minutes ?? null,
                        'featured_image' => $child->featured_image ?? null,
                        'gallery_images' => is_array($child->gallery_images) ? json_encode(array_values($child->gallery_images), JSON_UNESCAPED_SLASHES) : ($child->gallery_images ?? null),
                        'unit_price' => $childUnitPrice,
                        'quantity' => $childQuantity,
                        'total_price' => $childUnitPrice * $childQuantity,
                        'notes' => $child->pivot->notes ?? null,
                        // Use child's pivot flags if present; do not inherit from parent — each child should be independent
                        'include_in_program' => $child->pivot->include_in_program ?? true,
                        'include_in_calculation' => $child->pivot->include_in_calculation ?? true,
                        'active' => $child->pivot->active ?? true,
                        'show_title_style' => $child->pivot->show_title_style ?? true,
                        'show_description' => $child->pivot->show_description ?? true,
                        'group_size' => $child->group_size ?? null,
                        'currency_id' => $child->currency_id ?? null,
                        'convert_to_pln' => $child->convert_to_pln ?? false,
                        // tworzymy podpunkt jako osobny, równorzędny wpis (parent_id pozostaje null)
                    ]);

                    $childOrder += 0.1;
                }
            }
        }

        // Log completion for debugging
        try {
            \Illuminate\Support\Facades\Log::info('copyProgramPointsFromTemplate:done', ['event_id' => $this->id, 'created_points_count' => $this->programPoints()->count()]);
        } catch (\Throwable $_) {
            // ignore logging issues
        }
        $this->logHistory('program_copied', null, null, null, 'Skopiowano punkty programu z szablonu (w tym podpunkty)');
        $this->calculateTotalCost();
    }

    /**
     * Konwertuj cenę do waluty imprezy (PLN)
     */
    private function convertToEventCurrency(float $price, $currency = null): float
    {
        if (!$currency || $currency->symbol === 'PLN') {
            return $price;
        }

        // Pobierz kurs waluty z tabeli currencies
        $exchangeRate = \App\Models\Currency::where('symbol', $currency->symbol)->first()?->exchange_rate ?? 1;
        
        return $price * $exchangeRate;
    }

    /**
     * Oblicz całkowity koszt imprezy
     */
    public function calculateTotalCost(): void
    {
        $totalCost = $this->resolvedBaseTotalCost();

        $this->total_cost = $totalCost;
        $this->saveQuietly();
    }

    /**
     * Pobiera pełny koszt imprezy z uwzględnieniem narzutu i podatków.
     * Uwzględnia zmiany w punktach programu konkretnej imprezy.
     */
    public function resolvedFullTotalCost(
        ?int $participantCount = null,
        ?int $gratisCount = null,
        ?int $startPlaceId = null
    ): float
    {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));
        $startPlace = (int) ($startPlaceId ?? $this->start_place_id ?? 0);

        // Priorytet 1: Odczytaj price_with_tax z tabeli event_price_per_person
        // (kopia z "Cennika" szablonu — identyczna z zakładką Cennik/Kalkulacja).
        $priceRows = $this->pricePerPerson()
            ->with(['eventTemplateQty:id,qty', 'currency:id,code'])
            ->get()
            ->filter(fn ($r) => $r->currency && strtoupper($r->currency->code) === 'PLN')
            ->when($startPlace > 0, fn ($c) => $c->filter(fn ($r) => (int) $r->start_place_id === $startPlace))
            ->sortBy(fn ($r) => abs((int) ($r->eventTemplateQty->qty ?? 0) - $count));

        if ($priceRows->isNotEmpty()) {
            $best = $priceRows->first();
            if ((float) $best->price_with_tax > 0) {
                return round((float) $best->price_with_tax, 2);
            }
        }

        // Priorytet 2: Silnik kalkulacji
        if ($this->eventTemplate && $startPlace > 0) {
            try {
                $variant = $this->qtyVariants()->orderByRaw('ABS(qty - ?)', [$count])->first();
                $gratis = max(0, (int) ($gratisCount ?? $variant->gratis ?? 0));
                $engine = new \App\Services\EventTemplateCalculationEngine();
                $result = $engine->calculateDetailedForCustomGroup(
                    $this->eventTemplate, $count, $gratis, $startPlace
                );
                if (!empty($result) && isset($result['price_with_tax'])) {
                    return round((float) $result['price_with_tax'], 2);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Event::resolvedFullTotalCost engine failed', [
                    'event_id' => $this->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // Priorytet 3: Baza + marża + podatki (głęboki fallback)
        $baseCost = $this->resolvedBaseTotalCost($participantCount, $gratisCount, $startPlaceId);
        $markupPercent = (float) ($this->markup?->percent ?? $this->eventTemplate?->markup?->percent ?? 0);
        $markupAmount = $baseCost * ($markupPercent / 100);
        $totalTaxAmount = 0.0;
        if ($this->eventTemplate) {
            foreach ($this->eventTemplate->taxes as $tax) {
                if (!$tax->is_active) continue;
                $totalTaxAmount += $tax->calculateTaxAmount($baseCost, $markupAmount);
            }
        }
        return round($baseCost + $markupAmount + $totalTaxAmount, 2);
    }

    /**
     * Odświeża aktywne rozliczenie kosztami z aktualnego stanu eventu.
     * Nie tworzy nowego rozliczenia, jeśli jeszcze nie istnieje.
     */
    public function refreshActiveSettlementCosts(): void
    {
        $settlement = $this->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')
            ->first();

        if (! $settlement) {
            return;
        }

        try {
            $settlement->importFromEvent();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Event::refreshActiveSettlementCosts failed', [
                'event_id' => $this->id,
                'settlement_id' => $settlement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
    * Pobierz bazowy całkowity koszt imprezy (bez narzutu), spójny z kalkulacją imprezy.
     */
    public function resolvedBaseTotalCost(
        ?int $participantCount = null,
        ?int $gratisCount = null,
        ?int $startPlaceId = null
    ): float
    {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));
        $startPlace = (int) ($startPlaceId ?? $this->start_place_id ?? 0);

        $gratis = $gratisCount;
        if ($gratis === null) {
            $variant = $this->qtyVariants()
                ->orderByRaw('ABS(qty - ?)', [$count])
                ->first();

            $gratis = (int) ($variant->gratis ?? 0);
        }

        $gratis = max(0, (int) $gratis);

        // Jeżeli event ma własne punkty programu, traktujemy je jako źródło prawdy
        // (m.in. po ręcznych zmianach cen w RelationManagerze).
        $points = $this->programPoints()->where('active', true)->get();
        if ($points->isNotEmpty()) {
            $programCost = (float) \App\Services\ProgramPointHelper::sumIncluded($points, 'total_price');
            $insuranceCost = $this->resolvedInsuranceCost($count, $gratis);

            return round($programCost + $insuranceCost, 2);
        }

        if ($this->eventTemplate && $startPlace > 0) {
            try {
                $engine = new \App\Services\EventTemplateCalculationEngine();
                $result = $engine->calculateDetailedForCustomGroup(
                    $this->eventTemplate,
                    $count,
                    $gratis,
                    $startPlace,
                    null,
                    false
                );

                if (!empty($result) && array_key_exists('price_base', $result) && $result['price_base'] !== null) {
                    return round((float) $result['price_base'], 2);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Event::resolvedBaseTotalCost engine failed',
                    ['event_id' => $this->id, 'error' => $e->getMessage()]
                );
            }
        }

        $priceRows = $this->pricePerPerson()
            ->with('eventTemplateQty:id,qty')
            ->get()
            ->sortBy(function ($row) use ($count) {
                $qty = (int) ($row->eventTemplateQty->qty ?? $row->event_template_qty_id ?? 0);
                return abs($qty - $count);
            });

        if ($priceRows->isNotEmpty()) {
            $bestMatch = $priceRows->first();

            if ($bestMatch && $bestMatch->price_base !== null) {
                return round((float) $bestMatch->price_base, 2);
            }

            if (
                $bestMatch &&
                $bestMatch->price_with_tax !== null &&
                $bestMatch->markup_amount !== null
            ) {
                return round(max(0, ((float) $bestMatch->price_with_tax) - ((float) $bestMatch->markup_amount)), 2);
            }
        }

        return 0.0;
    }

    /**
     * Koszt ubezpieczeń przypisanych do dni eventu.
     * Liczenie zgodne z EventProgramTree: suma ubezpieczeń per dzień × (uczestnicy + gratis).
     */
    protected function resolvedInsuranceCost(int $participantCount, int $gratisCount = 0): float
    {
        $count = max(0, $participantCount) + max(0, $gratisCount);
        if ($count <= 0) {
            return 0.0;
        }

        $daysGrouped = [];
        foreach ($this->dayInsurances()->with('insurance')->get() as $dayInsurance) {
            $insurance = $dayInsurance->insurance;
            if (! $insurance || ! $insurance->insurance_enabled || ! $insurance->active) {
                continue;
            }

            $day = (int) ($dayInsurance->day ?? 0);
            if ($day <= 0) {
                continue;
            }

            $daysGrouped[$day] = ($daysGrouped[$day] ?? 0) + (float) ($insurance->price_per_person ?? 0);
        }

        $totalInsurance = 0.0;
        foreach ($daysGrouped as $daySum) {
            $totalInsurance += $daySum * $count;
        }

        return round($totalInsurance, 2);
    }

    /**
     * Pobierz cenę za osobę z pełnej kalkulacji (silnik + tab pricePerPerson + fallback)
     */
    public function resolvedPricePerPerson(?int $participantCount = null): float
    {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));
        
        // Priority 1: Engine calculation with template + gratis
        if ($this->eventTemplate && $this->start_place_id) {
            try {
                $variant = $this->qtyVariants()
                    ->orderByRaw('ABS(qty - ?)', [$count])
                    ->first();
                
                $gratis = (int) ($variant->gratis ?? 0);
                
                $engine = new \App\Services\EventTemplateCalculationEngine();
                $result = $engine->calculateDetailedForCustomGroup(
                    $this->eventTemplate,
                    $count,
                    $gratis,
                    $this->start_place_id,
                    null,
                    false
                );
                
                if (!empty($result) && isset($result['price_per_person'])) {
                    return round((float) $result['price_per_person'], 2);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Event::resolvedPricePerPerson engine failed',
                    ['event_id' => $this->id, 'error' => $e->getMessage()]
                );
            }
        }
        
        // Priority 2: EventPricePerPerson table - find closest qty
        // Load pricePerPerson with eventTemplateQty to find closest by qty
        $priceRows = $this->pricePerPerson()
            ->with('eventTemplateQty:id,qty')
            ->get()
            ->sortBy(function ($row) use ($count) {
                $qty = (int) ($row->eventTemplateQty->qty ?? $row->event_template_qty_id ?? 0);
                return abs($qty - $count);
            });
        
        if ($priceRows->isNotEmpty()) {
            $bestMatch = $priceRows->first();
            if ((float) $bestMatch->price_per_person > 0) {
                return round((float) $bestMatch->price_per_person, 2);
            }
        }
        
        // Priority 3: Fallback to total_cost / count
        $totalCost = (float) ($this->total_cost ?? 0);
        if ($totalCost > 0) {
            return round($totalCost / $count, 2);
        }
        
        return 0.0;
    }

    /**
     * Zapisz historię zmian
     */
    public function logHistory(string $action, ?string $field = null, $oldValue = null, $newValue = null, ?string $description = null): void
    {
        EventHistory::create([
            'event_id' => $this->id,
            'user_id' => Auth::id() ?? 1, // Fallback do user ID 1 jeśli brak auth
            'action' => $action,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);
    }

    /**
     * Sprawdź czy impreza może być edytowana
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, self::getEditableStatuses(), true);
    }

    /**
     * Zmień status imprezy
     */
    public function changeStatus(string $newStatus, ?string $reason = null): void
    {
        $oldStatus = $this->status;
        $labels = self::getStatusOptions();
        $oldStatusLabel = $labels[$oldStatus] ?? $oldStatus;
        $newStatusLabel = $labels[$newStatus] ?? $newStatus;
        
        // Utwórz snapshot przed zmianą statusu (dla ważnych statusów)
        if (in_array($newStatus, self::getSnapshotTriggerStatuses(), true)) {
            EventSnapshot::createSnapshot(
                $this,
                'status_change',
                "Snapshot przed zmianą na '{$newStatusLabel}'",
                "Snapshot utworzony przed zmianą statusu z '{$oldStatusLabel}' na '{$newStatusLabel}'" . ($reason ? ". Powód: {$reason}" : '')
            );
        }
        
        $this->update(['status' => $newStatus]);
        
        $description = "Zmieniono status z '{$oldStatusLabel}' na '{$newStatusLabel}'";
        if ($reason) {
            $description .= ". Powód: {$reason}";
        }
        
        $this->logHistory('status_changed', 'status', $oldStatus, $newStatus, $description);
    }

    /**
     * Utwórz ręczny snapshot
     */
    public function createManualSnapshot(?string $name = null, ?string $description = null): EventSnapshot
    {
        return EventSnapshot::createSnapshot(
            $this,
            'manual',
            $name ?? 'Snapshot ręczny ' . now()->format('d.m.Y H:i'),
            $description ?? 'Ręcznie utworzony snapshot'
        );
    }

    /**
     * Przywróć do pierwotnego stanu
     */
    public function restoreToOriginal(): bool
    {
        $originalSnapshot = $this->originalSnapshot;
        
        if (!$originalSnapshot) {
            return false;
        }
        
        $originalSnapshot->restoreToEvent();
        return true;
    }

    /**
     * Porównaj z pierwotnym stanem
     */
    public function compareWithOriginal(): ?array
    {
        $originalSnapshot = $this->originalSnapshot;
        
        if (!$originalSnapshot) {
            return null;
        }
        
        return $originalSnapshot->compareWithCurrent();
    }

    /**
     * Autokar przypisany do imprezy
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Markup przypisany do imprezy
     */
    public function markup(): BelongsTo
    {
        return $this->belongsTo(Markup::class);
    }

    /**
     * Rozliczenia imprezy
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(EventSettlement::class);
    }

    /**
     * Aktywne/ostatnie rozliczenie imprezy
     */
    public function activeSettlement()
    {
        return $this->hasOne(EventSettlement::class)->latest();
    }

    /**
     * Umowy i płatności online powiązane z imprezą
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(EventAgreement::class);
    }

    /**
     * Rezerwacje dla tej imprezy
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function buildIndividualAgreementReport(?Collection $agreements = null): array
    {
        $source = $agreements
            ?: $this->agreements()
                ->orderBy('agreement_date')
                ->orderBy('id')
                ->get();

        $agreements = $source
            ->filter(function (EventAgreement $agreement): bool {
                return $agreement->agreement_type === EventAgreement::TYPE_INDIVIDUAL
                    && !in_array($agreement->status, ['template', 'cancelled'], true);
            })
            ->values();

        $rows = $agreements
            ->map(function (EventAgreement $agreement): array {
                $amountDue = (float) $agreement->amount_due;
                $amountPaid = (float) $agreement->amount_paid;
                $amountRemaining = max(0, $amountDue - $amountPaid);

                return [
                    'agreement' => $agreement,
                    'agreement_number' => $agreement->agreement_number ?: ('UM-' . $agreement->id),
                    'participant_name' => $agreement->participant_name ?: '—',
                    'payer_name' => $agreement->signer_name ?: $agreement->customer_name ?: '—',
                    'payer_email' => $agreement->signer_email ?: $agreement->customer_email ?: '—',
                    'payer_phone' => $agreement->signer_phone ?: $agreement->customer_phone ?: '—',
                    'status' => $agreement->status,
                    'status_label' => $agreement->status_label,
                    'payment_status' => $agreement->payment_status,
                    'payment_status_label' => $agreement->payment_status_label,
                    'amount_due' => $amountDue,
                    'amount_paid' => $amountPaid,
                    'amount_remaining' => $amountRemaining,
                    'currency' => strtoupper((string) ($agreement->currency ?: 'PLN')),
                    'signed_at' => $agreement->signed_at,
                    'paid_at' => $agreement->paid_at,
                ];
            })
            ->values();

        $totalCount = $rows->count();
        $targetParticipants = max($totalCount, (int) ($this->participant_count ?? 0));
        $paidCount = $rows->where('payment_status', 'paid')->count();
        $amountDueTotal = (float) $rows->sum('amount_due');
        $amountPaidTotal = (float) $rows->sum('amount_paid');

        // For progress and remaining amount we prefer the event target participant count.
        // If pricing for this target cannot be resolved, keep contract-based totals.
        $summaryAmountDue = $amountDueTotal;
        $pricePerParticipant = $targetParticipants > 0
            ? (float) $this->resolvedPricePerPerson($targetParticipants)
            : 0.0;

        if ($targetParticipants > 0 && $pricePerParticipant > 0) {
            $summaryAmountDue = max($amountDueTotal, $pricePerParticipant * $targetParticipants);
        }

        $amountRemainingTotal = max(0, $summaryAmountDue - $amountPaidTotal);
        $unpaidCount = max(0, $targetParticipants - $paidCount);

        return [
            'agreements' => $agreements,
            'rows' => $rows,
            'summary' => [
                'total' => $totalCount,
                'target_participants' => $targetParticipants,
                'paid' => $paidCount,
                'unpaid' => $unpaidCount,
                'amount_due' => $summaryAmountDue,
                'amount_paid' => $amountPaidTotal,
                'amount_remaining' => $amountRemainingTotal,
                'payment_progress_label' => sprintf('%d/%d', $paidCount, max(1, $targetParticipants)),
            ],
        ];
    }

    /**
     * Dokumenty powiązane bezpośrednio z imprezą
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EventDocument::class)->orderBy('sort_order')->orderBy('created_at');
    }
}

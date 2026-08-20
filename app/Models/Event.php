<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Services\EventProgramScheduleService;
use App\Services\TemplateProgramPointCopier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasStickyNotes, HasTasks;

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
        'transport_contractor_id',
        'name',
        'client_name',
        'client_email',
        'client_phone',
        'start_date',
        'end_date',
        'duration_days',
        'program_day_start_times',
        'program_day_routes',
        'transfer_km',
        'program_km',
        'bus_id',
        'use_manual_transport_cost',
        'manual_transport_cost',
        'markup_id',
        'participant_count',
        'diet_info',
        'return_time',
        'substitution_time',
        'total_cost',
        'status',
        'notes',
        'office_notes',
        'hotel_notes',
        'hotel_pricing_mode',
        'hotel_flat_stay_amount',
        'hotel_flat_stay_currency_id',
        'hotel_flat_stay_convert_to_pln',
        'pilot_notes',
        'driver_notes',
        'departure_time',
        'transport_company_name',
        'driver_name',
        'driver_phone',
        'vehicle_registration',
        'bus_info',
        'pickup_place_details',
        'adress_transport_start',
        'adress_transport_end',
        'insurance_policy_number',
        'insurance_terms',
        'insurance_document_path',
        'insurance_amount',
        'insurance_payment_status',
        'insurance_status',
        'insurance_paid_at',
        'created_by',
        'assigned_to',
        'pilot_funds_paid',
        'pilot_funds_paid_at',
        'pilot_funds_paid_by',
        'pilot_advance_planned_amount',
        'pilot_advance_planned_at',
        'pilot_advance_planned_by',
        'pilot_advance_paid_amount',
        'pilot_advance_paid_currency_id',
        'pilot_advance_paid_comment',
        'pilot_portal_show_currency_exchange',
        'pilot_portal_show_bus_collections',
        'shared_with_pilot',
        'shared_with_pilot_at',
        'shared_with_pilot_by',
        'pilot_trip_email_sent_at',
        'check_in_status',
        'check_in_notes',
        'driver_pickup_info_sent_at',
        'driver_pickup_info_sent_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_cost' => 'decimal:2',
        'manual_transport_cost' => 'decimal:2',
        'use_manual_transport_cost' => 'boolean',
        'insurance_amount' => 'decimal:2',
        'insurance_paid_at' => 'datetime',
        'pilot_funds_paid' => 'boolean',
        'pilot_funds_paid_at' => 'datetime',
        'pilot_advance_planned_amount' => 'decimal:2',
        'pilot_advance_planned_at' => 'datetime',
        'pilot_advance_paid_amount' => 'decimal:2',
        'pilot_portal_show_currency_exchange' => 'boolean',
        'pilot_portal_show_bus_collections' => 'boolean',
        'shared_with_pilot' => 'boolean',
        'shared_with_pilot_at' => 'datetime',
        'pilot_trip_email_sent_at' => 'datetime',
        'driver_pickup_info_sent_at' => 'datetime',
        'hotel_flat_stay_convert_to_pln' => 'boolean',
        'program_day_start_times' => 'array',
        'program_day_routes' => 'array',
    ];

    public const DEFAULT_PROGRAM_DAY_START = '08:00';

    public function programDayStartTime(int $day): string
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            return self::DEFAULT_PROGRAM_DAY_START.':00';
        }

        $times = is_array($this->program_day_start_times) ? $this->program_day_start_times : [];
        $raw = $times[(string) $day] ?? $times[$day] ?? self::DEFAULT_PROGRAM_DAY_START;

        return $this->normalizeProgramDayStartTime((string) $raw);
    }

    public function programDayStartTimeLabel(int $day): string
    {
        return substr($this->programDayStartTime($day), 0, 5);
    }

    /**
     * @return array<string, string>
     */
    public function programDayStartTimes(): array
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            return [];
        }

        $times = is_array($this->program_day_start_times) ? $this->program_day_start_times : [];
        $normalized = [];

        foreach ($times as $day => $time) {
            $normalized[(string) $day] = $this->normalizeProgramDayStartTime((string) $time);
        }

        return $normalized;
    }

    public function setProgramDayStartTime(int $day, string $time): void
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            return;
        }

        $times = is_array($this->program_day_start_times) ? $this->program_day_start_times : [];
        $times[(string) $day] = substr($this->normalizeProgramDayStartTime($time), 0, 5);
        $this->program_day_start_times = $times;
    }

    public function programDayRoute(int $day): ?string
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            return null;
        }

        $routes = is_array($this->program_day_routes) ? $this->program_day_routes : [];
        $raw = $routes[(string) $day] ?? $routes[$day] ?? null;

        if (! is_string($raw)) {
            return null;
        }

        $normalized = trim($raw);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, string>
     */
    public function programDayRoutes(): array
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            return [];
        }

        $routes = is_array($this->program_day_routes) ? $this->program_day_routes : [];
        $normalized = [];

        foreach ($routes as $day => $route) {
            if (! is_string($route)) {
                continue;
            }

            $value = trim($route);

            if ($value !== '') {
                $normalized[(string) $day] = $value;
            }
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    public function setProgramDayRoute(int $day, ?string $route): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            return;
        }

        $routes = is_array($this->program_day_routes) ? $this->program_day_routes : [];
        $route = is_string($route) ? trim($route) : null;

        if ($route === null || $route === '') {
            unset($routes[(string) $day], $routes[$day]);
        } else {
            $routes[(string) $day] = mb_substr($route, 0, 500);
        }

        $this->program_day_routes = $routes !== [] ? $routes : null;
    }

    protected function normalizeProgramDayStartTime(string $time): string
    {
        $time = trim($time);

        if ($time === '') {
            return self::DEFAULT_PROGRAM_DAY_START.':00';
        }

        try {
            $normalized = strlen($time) > 5 ? substr($time, 0, 8) : $time;
            $format = strlen($time) > 5 ? 'H:i:s' : 'H:i';

            return Carbon::createFromFormat($format, $normalized)->format('H:i:s');
        } catch (\Throwable) {
            return self::DEFAULT_PROGRAM_DAY_START.':00';
        }
    }

    public static function getCheckInStatusOptions(): array
    {
        return [
            'pending' => 'Do zrobienia',
            'in_progress' => 'W trakcie',
            'completed' => 'Zakończona',
        ];
    }

    public function isCheckInCompleted(): bool
    {
        if (! Schema::hasColumn('events', 'check_in_status')) {
            return false;
        }

        return ($this->check_in_status ?? 'pending') === 'completed';
    }

    public function requiresDriverPickupInfo(): bool
    {
        return filled($this->transport_contractor_id)
            || filled($this->bus_id)
            || filled($this->transport_company_name)
            || filled($this->driver_name)
            || filled($this->driver_phone);
    }

    public function isDriverPickupInfoSent(): bool
    {
        if (! Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
            return false;
        }

        return filled($this->driver_pickup_info_sent_at);
    }

    public static function getInsuranceStatusOptions(): array
    {
        return [
            'pending' => 'Do zrobienia',
            'in_progress' => 'W trakcie',
            'completed' => 'Gotowe',
        ];
    }

    public static function getInsurancePaymentStatusOptions(): array
    {
        return [
            'pending' => 'Do zapłaty',
            'partial' => 'Częściowo opłacone',
            'paid' => 'Opłacone',
        ];
    }

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

    public static function statusListCellClass(?string $status): string
    {
        return 'event-list-status-cell event-list-status-cell--'.($status ?: 'unknown');
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

    /**
     * Unikalny kod imprezy: 2 cyfry roku + 6 znaków alfanumerycznych (A–Z, 0–9).
     */
    public static function generateUniqueCode(?Carbon $at = null): string
    {
        $year = ($at ?? now())->format('y');
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = $year.$suffix;
        } while (self::where('code', $code)->exists());

        return $code;
    }

    protected static function booted()
    {

        static::creating(function ($event) {
            if (empty($event->created_by)) {
                $event->created_by = Auth::id();
            }

            if (empty($event->code)) {
                $event->code = self::generateUniqueCode();
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
     * Zamawiający imprezy (jeden lub więcej kontrahentów)
     */
    public function orderingContractors(): BelongsToMany
    {
        $relation = $this->belongsToMany(Contractor::class, 'event_contractor')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('contractors.name');

        $pivotColumns = ['sort_order'];

        if (Schema::hasColumn('event_contractor', 'contact_id')) {
            $pivotColumns[] = 'contact_id';
        }

        if (Schema::hasColumn('event_contractor', 'department_label')) {
            $pivotColumns[] = 'department_label';
        }

        return $relation->withPivot($pivotColumns);
    }

    /**
     * @param  array<int, int>|array<int, array<string, mixed>>|null  $parties
     */
    public function syncOrderingContractors(array $parties): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        if ($parties !== [] && is_int($parties[0] ?? null)) {
            $legacyParties = collect($parties)
                ->filter()
                ->map(fn ($id): array => ['contractor_id' => (int) $id])
                ->all();

            app(\App\Services\EventOrderingPartyService::class)->syncForEvent($this, $legacyParties);

            return;
        }

        app(\App\Services\EventOrderingPartyService::class)->syncForEvent($this, $parties);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $parties
     */
    public function syncOrderingParties(?array $parties): void
    {
        app(\App\Services\EventOrderingPartyService::class)->syncForEvent($this, $parties);
    }

    public function syncPrimaryClientFromOrderingParties(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        $parties = app(\App\Services\EventOrderingPartyService::class)->partiesToFormState($this->fresh());
        $payload = app(\App\Services\EventOrderingPartyService::class)->primaryClientAttributes($parties);

        if ($payload === []) {
            return;
        }

        $this->forceFill($payload)->saveQuietly();
    }

    public function formattedOrderingPartiesNames(): string
    {
        if (Schema::hasTable('event_contractor')) {
            $this->loadMissing('orderingContractors');

            if ($this->orderingContractors->isNotEmpty()) {
                $service = app(\App\Services\EventOrderingPartyService::class);
                $hasContactPivot = Schema::hasColumn('event_contractor', 'contact_id');
                $hasDepartmentPivot = Schema::hasColumn('event_contractor', 'department_label');

                $contactIds = $hasContactPivot
                    ? $this->orderingContractors
                        ->pluck('pivot.contact_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all()
                    : [];

                $contactsById = self::contactsByIdsCached($contactIds);

                return $this->orderingContractors
                    ->map(function (Contractor $contractor) use ($service, $hasContactPivot, $hasDepartmentPivot, $contactsById): string {
                        $contactId = $hasContactPivot
                            ? ($contractor->pivot->contact_id ?? null)
                            : null;
                        $department = $hasDepartmentPivot
                            ? ($contractor->pivot->department_label ?? null)
                            : null;
                        $contact = $contactId ? $contactsById->get((int) $contactId) : null;

                        return $service->formatPartyLabelFromModels($contact, $contractor, $department);
                    })
                    ->implode(', ');
            }
        }

        return (string) ($this->client_name ?? '—');
    }

    /**
     * Cache Contact w ramach requestu — unika N+1 na liście imprez.
     *
     * @param  array<int, int>  $contactIds
     * @return \Illuminate\Support\Collection<int, \App\Models\Contact>
     */
    private static function contactsByIdsCached(array $contactIds): \Illuminate\Support\Collection
    {
        if ($contactIds === []) {
            return collect();
        }

        /** @var array<int, \App\Models\Contact> $cache */
        static $cache = [];

        $missing = array_values(array_filter(
            $contactIds,
            fn (int $id): bool => ! array_key_exists($id, $cache)
        ));

        if ($missing !== []) {
            foreach (\App\Models\Contact::query()->whereIn('id', $missing)->get() as $contact) {
                $cache[(int) $contact->id] = $contact;
            }
        }

        return collect($contactIds)
            ->mapWithKeys(fn (int $id) => [$id => $cache[$id] ?? null])
            ->filter();
    }

    /**
     * Kontrahent transportowy (firma transportowa)
     */
    public function transportContractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'transport_contractor_id');
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

    public function pilotAdvancePlannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pilot_advance_planned_by');
    }

    public function sharedWithPilotByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_pilot_by');
    }

    public function portalAccesses(): HasMany
    {
        return $this->hasMany(EventPortalAccess::class);
    }

    public function clientInvoiceRequests(): HasMany
    {
        return $this->hasMany(ClientInvoiceRequest::class);
    }

    public function pilotFundsPaidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pilot_funds_paid_by');
    }

    public function pilotAdvancePaidCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'pilot_advance_paid_currency_id');
    }

    public function showsPilotCurrencyExchange(): bool
    {
        if (! Schema::hasColumn('events', 'pilot_portal_show_currency_exchange')) {
            return true;
        }

        return (bool) ($this->pilot_portal_show_currency_exchange ?? true);
    }

    public function showsPilotBusCollections(): bool
    {
        if (! Schema::hasColumn('events', 'pilot_portal_show_bus_collections')) {
            return false;
        }

        return (bool) ($this->pilot_portal_show_bus_collections ?? false);
    }

    public function pilotAdvanceLines(): HasMany
    {
        return $this->hasMany(PilotAdvanceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function driverPickupInfoSentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_pickup_info_sent_by');
    }

    public function scopeForPilot($query, User $user)
    {
        $query = $query
            ->where('assigned_to', $user->id)
            ->where('status', '!=', self::STATUS_CANCELLED);

        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $query->where('shared_with_pilot', true);
        }

        return $query;
    }

    public function scopeUpcoming($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($inner) use ($today): void {
            $inner
                ->whereDate('end_date', '>=', $today)
                ->orWhere(function ($fallback) use ($today): void {
                    $fallback
                        ->whereNull('end_date')
                        ->whereDate('start_date', '>=', $today);
                });
        });
    }

    public function scopeCompleted($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($inner) use ($today): void {
            $inner
                ->whereDate('end_date', '<', $today)
                ->orWhere(function ($fallback) use ($today): void {
                    $fallback
                        ->whereNull('end_date')
                        ->whereDate('start_date', '<', $today);
                });
        });
    }

    /**
     * Punkty programu dla tej imprezy
     */
    public function programPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class);
    }

    /**
     * Punkty programu oznaczone jako hotel/nocleg
     */
    public function hotelProgramPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class)
            ->where('is_hotel', true)
            ->with('contractor')
            ->orderBy('day')
            ->orderBy('order');
    }

    /**
     * Punkty programu oznaczone jako dodatkowa usługa hotelu (bankiet, obiad, DJ itp.)
     */
    public function hotelServiceProgramPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class)
            ->where('is_hotel_service', true)
            ->with('contractor')
            ->orderBy('day')
            ->orderBy('order');
    }

    public function hotelStays(): HasMany
    {
        return $this->hasMany(EventHotelStay::class)->orderBy('day');
    }

    /**
     * Kontrahenci (hotele) przypisani do planu noclegów tej imprezy.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function assignedHotelContractorIds(): \Illuminate\Support\Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('event_hotel_stays')) {
            return collect();
        }

        return $this->hotelStays()
            ->get()
            ->pluck('contractor_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    public function hotelCorrespondenceLogs(): HasMany
    {
        return $this->hasMany(HotelCorrespondenceLog::class)->orderByDesc('contacted_at');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class)->orderBy('last_name')->orderBy('first_name');
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->where('status', EventParticipant::STATUS_ACTIVE);
    }

    public function dateForProgramDay(int $day): ?Carbon
    {
        if (! $this->start_date) {
            return null;
        }

        return $this->start_date->copy()->addDays(max(0, $day - 1));
    }

    /**
     * Punkty programu oznaczone jako transport.
     */
    public function transportProgramPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class)
            ->where('is_transport', true)
            ->with('contractor')
            ->orderBy('day')
            ->orderBy('order');
    }

    /**
     * Synchronizuje dane transportu imprezy z oznaczonych punktów programu.
     */
    public function syncTransportFromProgramPoints(): void
    {
        if (! Schema::hasColumn('events', 'transport_contractor_id')) {
            return;
        }

        $transportPoint = $this->transportProgramPoints()
            ->whereNotNull('contractor_id')
            ->first();

        if (! $transportPoint) {
            return;
        }

        $payload = [
            'transport_contractor_id' => $transportPoint->contractor_id,
        ];

        if (Schema::hasColumn('events', 'transport_company_name')) {
            $payload['transport_company_name'] = $transportPoint->contractor?->name ?: ($this->transport_company_name ?: null);
        }

        $this->fill($payload);
        $this->saveQuietly();
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
            $exactVariant = $this->qtyVariants
                ->where('qty', $count)
                ->sortBy('id')
                ->first();

            if ($exactVariant) {
                return max(0, (int) ($exactVariant->gratis ?? 0));
            }

            $variant = $this->qtyVariants
                ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $count))
                ->first();

            return max(0, (int) ($variant?->gratis ?? 0));
        }

        $exactVariant = $this->qtyVariants()
            ->where('qty', $count)
            ->orderBy('id')
            ->first();

        if ($exactVariant) {
            return max(0, (int) ($exactVariant->gratis ?? 0));
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

    public function participantResignations(): HasMany
    {
        return $this->hasMany(EventParticipantResignation::class);
    }

    /**
     * Ręcznie zamrożone wartości etapów kalkulacji (cena klienta / koszt).
     */
    public function calculationStages(): HasMany
    {
        return $this->hasMany(EventCalculationStage::class);
    }

    /**
     * Zwraca ręcznie zamrożony wpis etapu kalkulacji (jeśli istnieje).
     */
    public function calculationStage(string $stage): ?EventCalculationStage
    {
        return $this->calculationStages->firstWhere('stage', $stage);
    }

    /**
     * Ubezpieczenia dniowe przypisane do imprezy
     */
    public function dayInsurances(): HasMany
    {
        return $this->hasMany(EventDayInsurance::class);
    }

    public function requiresInsuranceWorkflow(): bool
    {
        if (! Schema::hasTable('event_day_insurance')) {
            return false;
        }

        if ($this->relationLoaded('dayInsurances')) {
            return $this->dayInsurances
                ->contains(fn (EventDayInsurance $row) => ! blank($row->insurance_id));
        }

        return $this->dayInsurances()
            ->whereNotNull('insurance_id')
            ->exists();
    }

    public function isInsuranceCompleted(): bool
    {
        if (! $this->requiresInsuranceWorkflow()) {
            return true;
        }

        return filled($this->insurance_policy_number)
            && filled($this->insurance_terms)
            && filled($this->insurance_document_path)
            && ($this->insurance_status === 'completed')
            && ($this->insurance_payment_status === 'paid');
    }

    public function hasInsuranceDataSaved(): bool
    {
        if (! $this->requiresInsuranceWorkflow()) {
            return true;
        }

        return filled($this->insurance_policy_number)
            || filled($this->insurance_terms)
            || filled($this->insurance_document_path)
            || filled($this->insurance_amount)
            || filled($this->insurance_paid_at)
            || ($this->insurance_status && $this->insurance_status !== 'pending')
            || ($this->insurance_payment_status && $this->insurance_payment_status !== 'pending');
    }

    public function insuranceChecklistLabel(): string
    {
        if (! $this->requiresInsuranceWorkflow()) {
            return 'Brak wymogu';
        }

        if ($this->isInsuranceCompleted()) {
            return 'Kompletne';
        }

        if ($this->hasInsuranceDataSaved()) {
            $parts = array_filter([
                filled($this->insurance_policy_number) ? 'polisa '.$this->insurance_policy_number : null,
                filled($this->insurance_document_path) ? 'dokument wgrany' : null,
            ]);

            return 'W trakcie'.($parts !== [] ? ' ('.implode(', ', $parts).')' : '');
        }

        return 'Brak danych';
    }

    public static function normalizeInsuranceDocumentPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path[array_key_first($path)] ?? null;
        }

        return filled($path) ? (string) $path : null;
    }

    public function updateInsuranceFromFormData(array $data): void
    {
        $this->update([
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
            'insurance_status' => $data['insurance_status'] ?? 'pending',
            'insurance_payment_status' => $data['insurance_payment_status'] ?? 'pending',
            'insurance_amount' => filled($data['insurance_amount'] ?? null) ? (float) $data['insurance_amount'] : null,
            'insurance_paid_at' => $data['insurance_paid_at'] ?? null,
            'insurance_document_path' => self::normalizeInsuranceDocumentPath($data['insurance_document_path'] ?? null),
            'insurance_terms' => $data['insurance_terms'] ?? null,
        ]);
    }

    public function insuranceSaveSummary(): string
    {
        $parts = array_filter([
            filled($this->insurance_policy_number) ? 'Polisa: '.$this->insurance_policy_number : null,
            filled($this->insurance_document_path) ? 'Dokument zapisany' : null,
            $this->insuranceChecklistLabel(),
        ]);

        return $parts !== [] ? implode(' • ', $parts) : 'Brak zapisanych danych ubezpieczenia';
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

        if (Schema::hasColumn('events', 'diet_info')) {
            $eventPayload['diet_info'] = $data['diet_info'] ?? null;
        }

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

        if (Schema::hasColumn('events', 'transport_contractor_id')) {
            $eventPayload['transport_contractor_id'] = $data['transport_contractor_id'] ?? null;
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

        if (Schema::hasColumn('events', 'bus_info')) {
            $eventPayload['bus_info'] = $data['bus_info'] ?? null;
        }

        if (Schema::hasColumn('events', 'pickup_place_details')) {
            $eventPayload['pickup_place_details'] = $data['pickup_place_details'] ?? null;
        }

        if (Schema::hasColumn('events', 'contractor_id')) {
            $eventPayload['contractor_id'] = $data['contractor_id'] ?? null;
        }

        if (Schema::hasColumn('events', 'use_manual_transport_cost')) {
            $eventPayload['use_manual_transport_cost'] = (bool) ($data['use_manual_transport_cost'] ?? false);
            $eventPayload['manual_transport_cost'] = filled($data['manual_transport_cost'] ?? null)
                ? (float) $data['manual_transport_cost']
                : null;
        }

        $orderingParties = $data['ordering_parties'] ?? null;
        $orderingContractorIds = $data['orderingContractors'] ?? null;
        unset($data['ordering_parties'], $data['orderingContractors']);

        $event = self::create($eventPayload);

        if (is_array($orderingParties) && $orderingParties !== []) {
            $event->syncOrderingParties($orderingParties);
        } elseif (is_array($orderingContractorIds) && $orderingContractorIds !== []) {
            $event->syncOrderingContractors($orderingContractorIds);
        }

        // Skopiuj punkty programu z szablonu (w tym podpunkty)
        $event->copyProgramPointsFromTemplate();

        try {
            $hotelPlan = app(\App\Services\EventHotelPlanService::class);
            $hotelPlan->snapshotFromTemplate($event);
            $hotelPlan->linkStaysToProgramPoints($event);
        } catch (\Throwable $e) {
            // ignore when hotel tables unavailable
        }

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
            $standardQtys = [10, 20, 30];
            $qtyIds = \App\Models\EventTemplateQty::whereIn('qty', $standardQtys)->pluck('id')->toArray();

            $templatePricesSnapshot = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $template->id)
                ->where(function ($q) use ($startId) {
                    if ($startId) {
                        $q->where('start_place_id', $startId);
                    } else {
                        $q->whereNull('start_place_id');
                    }
                })
                ->whereIn('event_template_qty_id', $qtyIds)
                ->orderBy('event_template_qty_id')
                ->get()
                ->map(function ($p) {
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
            'Automatycznie utworzony snapshot w momencie tworzenia imprezy na podstawie szablonu: '.$template->name,
            $templatePricesSnapshot
        );

        // Wykonaj wstępną kalkulację per-event aby zapisać event-scoped ceny
        try {
            // Preferuj dokładny engine używany dla szablonów, aby uzyskać zgodność kalkulacji
            $engine = new \App\Services\EventTemplateCalculationEngine;
            $detailed = $engine->calculateDetailed($template, $data['start_place_id'] ?? $template->start_place_id ?? null, $data['transfer_km'] ?? null);

            if (! empty($detailed)) {
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
                $calculator = new \App\Services\EventPriceCalculator;
                $calculator->calculateForEvent($event);
            }
        } catch (\Throwable $e) {
            // fallback to simple calculator on any failure
            try {
                $calculator = new \App\Services\EventPriceCalculator;
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

        try {
            \Illuminate\Support\Facades\Log::info('copyProgramPointsFromTemplate:start', ['event_id' => $this->id, 'event_template_id' => $this->event_template_id ?? null]);
        } catch (\Throwable $_) {
        }

        app(TemplateProgramPointCopier::class)->copyToEvent($this);

        app(EventProgramScheduleService::class)->bootstrapFromTemplate($this->fresh());

        try {
            \Illuminate\Support\Facades\Log::info('copyProgramPointsFromTemplate:done', ['event_id' => $this->id, 'created_points_count' => $this->programPoints()->count()]);
        } catch (\Throwable $_) {
        }

        $this->logHistory('program_copied', null, null, null, 'Skopiowano punkty programu z szablonu (w tym podpunkty)');
        $this->calculateTotalCost();
        $this->refreshActiveSettlementCosts();
        $this->syncTransportFromProgramPoints();
    }

    /**
     * Konwertuj cenę do waluty imprezy (PLN)
     */
    private function convertToEventCurrency(float $price, $currency = null): float
    {
        if (! $currency || $currency->symbol === 'PLN') {
            return $price;
        }

        // Pobierz kurs waluty z tabeli currencies
        $exchangeRate = \App\Models\Currency::where('symbol', $currency->symbol)->first()?->exchange_rate ?? 1;

        return $price * $exchangeRate;
    }

    /**
     * Przelicz ilości (quantity) w punktach programu na podstawie aktualnego participant_count.
     * Dotyczy tylko punktów z ustawionym group_size (ilość grup zależy od liczby uczestników).
     */
    public function resyncProgramPointQuantities(): void
    {
        $participantCount = max(1, (int) ($this->participant_count ?? 1));

        $this->programPoints()
            ->whereNotNull('group_size')
            ->where('group_size', '>', 0)
            ->each(function ($point) use ($participantCount) {
                $groupSize = max(1, (int) $point->group_size);
                $newQuantity = max(1, (int) ceil($participantCount / $groupSize));
                $previousQuantity = max(1, (int) ($point->quantity ?? 1));
                $previousCalculated = round((float) ($point->calculated_price ?? 0), 2);
                $previousPlanned = round((float) ($point->planned_price ?? 0), 2);

                $point->quantity = $newQuantity;
                $point->total_price = round((float) ($point->unit_price ?? 0) * $newQuantity, 2);

                if ($point->templatePoint) {
                    $newCalculated = round((float) ($point->templatePoint->unit_price ?? 0) * $newQuantity, 2);
                    $point->calculated_price = $newCalculated;

                    // Jeśli planowana była dotychczas zgodna z kalkulacją, traktujemy ją jako automatyczną
                    // i też ją przeliczamy przy zmianie liczby uczestników.
                    $wasAutoPlanned = ($previousPlanned <= 0.0)
                        || (abs($previousPlanned - $previousCalculated) < 0.01)
                        || ($previousQuantity === 1);

                    if ($wasAutoPlanned) {
                        $point->planned_price = $newCalculated;
                    }
                }

                $point->saveQuietly();
            });
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
    ): float {
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
                $engine = new \App\Services\EventTemplateCalculationEngine;
                $result = $engine->calculateDetailedForCustomGroup(
                    $this->eventTemplate, $count, $gratis, $startPlace
                );
                if (! empty($result) && isset($result['price_with_tax'])) {
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
                if (! $tax->is_active) {
                    continue;
                }
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
    ): float {
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
                $engine = new \App\Services\EventTemplateCalculationEngine;
                $result = $engine->calculateDetailedForCustomGroup(
                    $this->eventTemplate,
                    $count,
                    $gratis,
                    $startPlace,
                    null,
                    false
                );

                if (! empty($result) && array_key_exists('price_base', $result) && $result['price_base'] !== null) {
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
    /**
     * Publiczny dostęp do kosztu ubezpieczeń (dla wspólnego kalkulatora).
     */
    public function insuranceCostPln(int $participantCount, int $gratisCount = 0): float
    {
        return $this->resolvedInsuranceCost($participantCount, $gratisCount);
    }

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

        // Priority 1: ręcznie ustawione ceny w tabeli event_price_per_person
        $manualPrice = $this->resolveStoredPricePerPerson($count, manualOnly: true);
        if ($manualPrice !== null) {
            return $manualPrice;
        }

        $storedPrice = $this->resolveStoredPricePerPerson($count, manualOnly: false);
        if ($storedPrice !== null) {
            return $storedPrice;
        }

        // Priority 2: silnik kalkulacji (szablon + gratis)
        if ($this->eventTemplate && $this->start_place_id) {
            try {
                $variant = $this->qtyVariants()
                    ->orderByRaw('ABS(qty - ?)', [$count])
                    ->first();

                $gratis = (int) ($variant->gratis ?? 0);

                $engine = new \App\Services\EventTemplateCalculationEngine;
                $result = $engine->calculateDetailedForCustomGroup(
                    $this->eventTemplate,
                    $count,
                    $gratis,
                    $this->start_place_id,
                    null,
                    false
                );

                if (! empty($result) && isset($result['price_per_person'])) {
                    return round((float) $result['price_per_person'], 2);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Event::resolvedPricePerPerson engine failed',
                    ['event_id' => $this->id, 'error' => $e->getMessage()]
                );
            }
        }

        // Priority 3: Fallback do total_cost / count
        $totalCost = (float) ($this->total_cost ?? 0);
        if ($totalCost > 0) {
            return round($totalCost / $count, 2);
        }

        return 0.0;
    }

    private function resolveStoredPricePerPerson(int $count, bool $manualOnly): ?float
    {
        $query = $this->pricePerPerson()
            ->with('eventTemplateQty:id,qty');

        if ($manualOnly) {
            $query->where('is_manual', true);
        }

        $priceRows = $query->get()
            ->sortBy(function ($row) use ($count) {
                $qty = (int) ($row->eventTemplateQty->qty ?? $row->event_template_qty_id ?? 0);

                return abs($qty - $count);
            });

        if ($priceRows->isEmpty()) {
            return null;
        }

        $bestMatch = $priceRows->first();
        if ((float) $bestMatch->price_per_person > 0) {
            return round((float) $bestMatch->price_per_person, 2);
        }

        if ((float) ($bestMatch->price_with_tax ?? 0) > 0) {
            return round((float) $bestMatch->price_with_tax, 2);
        }

        return null;
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
                "Snapshot utworzony przed zmianą statusu z '{$oldStatusLabel}' na '{$newStatusLabel}'".($reason ? ". Powód: {$reason}" : '')
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
            $name ?? 'Snapshot ręczny '.now()->format('d.m.Y H:i'),
            $description ?? 'Ręcznie utworzony snapshot'
        );
    }

    /**
     * Przywróć do pierwotnego stanu
     */
    public function restoreToOriginal(): bool
    {
        $originalSnapshot = $this->originalSnapshot;

        if (! $originalSnapshot) {
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

        if (! $originalSnapshot) {
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

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class);
    }

    /**
     * Aktywne/ostatnie rozliczenie imprezy
     */
    public function activeSettlement()
    {
        return $this->hasOne(EventSettlement::class)
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latestOfMany();
    }

    public function latestSettlement()
    {
        return $this->hasOne(EventSettlement::class)->latestOfMany();
    }

    /**
     * Umowy i płatności online powiązane z imprezą
     */
    public function agreements(): HasMany
    {
        $dateColumn = $this->agreementDateColumn();

        if (Schema::hasTable('contracts')) {
            return $this->hasMany(Contract::class)
                ->orderByDesc($dateColumn)
                ->orderByDesc('id');
        }

        return $this->hasMany(EventAgreement::class)
            ->orderByDesc($dateColumn)
            ->orderByDesc('id');
    }

    private function agreementDateColumn(): string
    {
        return Schema::hasTable('contracts') ? 'contract_date' : 'agreement_date';
    }

    /**
     * Rezerwacje dla tej imprezy
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function busCollections(): HasMany
    {
        return $this->hasMany(EventBusCollection::class);
    }

    public function buildIndividualAgreementReport(?Collection $agreements = null): array
    {
        $source = $agreements ?: $this->agreements()->get();

        $agreements = $source
            ->filter(function (EventAgreement|Contract $agreement): bool {
                return $agreement->agreement_type === Contract::TYPE_INDIVIDUAL
                    && ! in_array($agreement->status, ['template', 'cancelled'], true);
            })
            ->values();

        $rows = $agreements
            ->map(function (EventAgreement|Contract $agreement): array {
                $amountDue = (float) $agreement->amount_due;
                $amountPaid = (float) $agreement->amount_paid;
                $amountRemaining = max(0, $amountDue - $amountPaid);

                return [
                    'agreement' => $agreement,
                    'agreement_number' => $agreement->agreement_number ?: ('UM-'.$agreement->id),
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
            ->sortBy('participant_name', SORT_NATURAL | SORT_FLAG_CASE)
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

    public static function templatePointLooksLikeHotel(object $point): bool
    {
        $name = mb_strtolower(trim((string) ($point->name ?? '')));

        if ($name === '') {
            return false;
        }

        foreach (['hotel', 'nocleg', 'zakwater', 'pensjonat', 'hostel'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }
}

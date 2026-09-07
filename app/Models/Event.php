<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Services\EventProgramScheduleService;
use App\Services\TemplateProgramPointCopier;
use App\Support\ProgramPointCostPricing;
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

class Event extends Model
{
    use HasFactory, HasStickyNotes, HasTasks;

    public const STATUS_INQUIRY = 'inquiry';

    public const STATUS_OFFER = 'offer';

    public const STATUS_PROVISIONAL_RESERVATION = 'provisional_reservation';

    public const STATUS_CONFIRMED = 'confirmed';

    /** Potwierdzona + już odprawiona (jak OdprawaOK w OISE — ten sam filtr co Potwierdzona). */
    public const STATUS_ODPRAWA_OK = 'odprawa_ok';

    public const STATUS_TO_SETTLE = 'to_settle';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_PENDING_CANCELLATION = 'pending_cancellation';

    public const STATUS_CANCELLED = 'cancelled';

    public const LEGACY_STATUS_MIGRATION_MAP = [
        'draft' => self::STATUS_INQUIRY,
        'confirmed' => self::STATUS_CONFIRMED,
        'OdprawaOK' => self::STATUS_ODPRAWA_OK,
        'in_progress' => self::STATUS_TO_SETTLE,
        'completed' => self::STATUS_SETTLED,
        'cancelled' => self::STATUS_CANCELLED,
    ];

    /** Maksymalna długość kodu imprezy (kolumna `events.code` + wniosek o fakturę). */
    public const CODE_MAX_LENGTH = 32;

    /** @var array<int, \App\Models\Contact> */
    private static array $contactsByIdCache = [];

    protected $fillable = [
        'event_template_id',
        'start_place_id',
        'program_start_place_id',
        'contractor_id',
        'transport_contractor_id',
        'driver_contractor_id',
        'name',
        'code',
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
        'insurance_insured_list_path',
        'insurance_amount',
        'insurance_payment_status',
        'insurance_status',
        'insurance_paid_at',
        'tfg_defaults',
        'created_by',
        'assigned_to',
        'office_caretaker_id',
        'pilot_contractor_id',
        'pilot_settlement_form',
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
        'pilot_portal_show_attendance',
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
        'tfg_defaults' => 'array',
        'pilot_funds_paid' => 'boolean',
        'pilot_settlement_form' => \App\Enums\ContractorSettlementForm::class,
        'pilot_funds_paid_at' => 'datetime',
        'pilot_advance_planned_amount' => 'decimal:2',
        'pilot_advance_planned_at' => 'datetime',
        'pilot_advance_paid_amount' => 'decimal:2',
        'pilot_portal_show_currency_exchange' => 'boolean',
        'pilot_portal_show_bus_collections' => 'boolean',
        'pilot_portal_show_attendance' => 'boolean',
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

        // Slot fakultatywny nie ma trasy przejazdu (to nie jest dzień wycieczki).
        if ($this->isFacultativeProgramDay($day)) {
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
     * Trasy przejazdu tylko dla dni wycieczki (bez dnia fakultatywnego).
     *
     * @return array<string, string>
     */
    public function programDayRoutes(): array
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            return [];
        }

        $routes = is_array($this->program_day_routes) ? $this->program_day_routes : [];
        $coreDays = $this->resolveCoreProgramDaysCount();
        $normalized = [];

        foreach ($routes as $day => $route) {
            if (! is_string($route)) {
                continue;
            }

            $dayNumber = (int) $day;
            if ($dayNumber < 1 || $dayNumber > $coreDays) {
                continue;
            }

            $value = trim($route);

            if ($value !== '') {
                $normalized[(string) $dayNumber] = $value;
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

        // Nie zapisujemy tras na slot fakultatywny; ewentualny stary wpis czyścimy.
        if ($this->isFacultativeProgramDay($day)) {
            unset($routes[(string) $day], $routes[$day]);
            $this->program_day_routes = $routes !== [] ? $routes : null;

            return;
        }

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
        if ($this->status === self::STATUS_ODPRAWA_OK) {
            return true;
        }

        if (! Schema::hasColumn('events', 'check_in_status')) {
            return false;
        }

        return ($this->check_in_status ?? 'pending') === 'completed';
    }

    public function requiresDriverPickupInfo(): bool
    {
        return filled($this->transport_contractor_id)
            || filled($this->driver_contractor_id)
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
            self::STATUS_ODPRAWA_OK => 'Odprawa OK',
            self::STATUS_TO_SETTLE => 'Do rozliczenia',
            self::STATUS_SETTLED => 'Rozliczona',
            self::STATUS_PENDING_CANCELLATION => 'Do anulacji',
            self::STATUS_CANCELLED => 'Anulowana',
        ];
    }

    /**
     * Statusy z grupy „Potwierdzone” (jak w OISE: Potwierdzona + OdprawaOK w jednym filtrze).
     *
     * @return list<string>
     */
    public static function getConfirmedLikeStatuses(): array
    {
        return [
            self::STATUS_CONFIRMED,
            self::STATUS_ODPRAWA_OK,
        ];
    }

    public function isConfirmedLike(): bool
    {
        return in_array($this->status, self::getConfirmedLikeStatuses(), true);
    }

    public static function getStatusColors(): array
    {
        // Paleta docs/03: Draft=gray, Oferta/W trakcie=info, Potwierdzona/Zrealizowana=success, Anulowana=danger
        return [
            'gray' => self::STATUS_INQUIRY,
            'info' => [self::STATUS_OFFER, self::STATUS_PROVISIONAL_RESERVATION],
            'warning' => self::STATUS_TO_SETTLE,
            'success' => [self::STATUS_CONFIRMED, self::STATUS_ODPRAWA_OK, self::STATUS_SETTLED],
            'danger' => [self::STATUS_PENDING_CANCELLATION, self::STATUS_CANCELLED],
        ];
    }

    /**
     * Kolor badge Filament dla pojedynczego statusu (docs UX).
     */
    public static function statusBadgeColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_INQUIRY => 'gray',
            self::STATUS_OFFER, self::STATUS_PROVISIONAL_RESERVATION => 'info',
            self::STATUS_TO_SETTLE => 'warning',
            self::STATUS_CONFIRMED, self::STATUS_ODPRAWA_OK, self::STATUS_SETTLED => 'success',
            self::STATUS_PENDING_CANCELLATION, self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
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
            self::STATUS_ODPRAWA_OK,
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
            self::STATUS_ODPRAWA_OK,
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

    /**
     * Normalizuje ręcznie wpisany kod: bez spacji, wielkie litery.
     * Pusty wpis zwraca null (generator nadaje kod przy tworzeniu).
     */
    public static function normalizeCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/\s+/', '', $value));

        return $normalized === '' ? null : $normalized;
    }

    public function setCodeAttribute(mixed $value): void
    {
        $this->attributes['code'] = self::normalizeCode(
            $value === null ? null : (string) $value
        );
    }

    protected static function booted()
    {

        static::creating(function ($event) {
            if (empty($event->created_by)) {
                $event->created_by = Auth::id();
            }

            if (blank($event->code)) {
                $event->code = self::generateUniqueCode();
            }
        });

        static::updating(function ($event) {
            if ($event->isDirty('code') && blank($event->code)) {
                $event->code = $event->getOriginal('code');
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

            if ($event->wasChanged('office_caretaker_id') && $event->office_caretaker_id) {
                $caretaker = $event->officeCaretaker()->first();
                if ($caretaker) {
                    app(\App\Services\ClientTripInquiryOfficeNotifier::class)
                        ->notifyNewCaretakerAboutOpenInquiries($event, $caretaker);
                }
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

        if (Schema::hasColumn('event_contractor', 'notes')) {
            $pivotColumns[] = 'notes';
        }

        if (Schema::hasColumn('event_contractor', 'goes_on_trip')) {
            $pivotColumns[] = 'goes_on_trip';
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
                $hasNotesPivot = Schema::hasColumn('event_contractor', 'notes');

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
                    ->values()
                    ->map(function (Contractor $contractor, int $index) use ($service, $hasContactPivot, $hasDepartmentPivot, $hasNotesPivot, $contactsById): string {
                        $contactId = $hasContactPivot
                            ? ($contractor->pivot->contact_id ?? null)
                            : null;
                        $department = $hasDepartmentPivot
                            ? ($contractor->pivot->department_label ?? null)
                            : null;
                        $notes = $hasNotesPivot
                            ? ($contractor->pivot->notes ?? null)
                            : null;
                        $contact = $contactId ? $contactsById->get((int) $contactId) : null;

                        return $service->formatPartyLabelFromModels(
                            $contact,
                            $contractor,
                            $department,
                            $notes,
                            $index === 0,
                        );
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

        $missing = array_values(array_filter(
            $contactIds,
            fn (int $id): bool => ! array_key_exists($id, self::$contactsByIdCache)
        ));

        if ($missing !== []) {
            foreach (\App\Models\Contact::query()->whereIn('id', $missing)->get() as $contact) {
                self::$contactsByIdCache[(int) $contact->id] = $contact;
            }
        }

        return collect($contactIds)
            ->mapWithKeys(fn (int $id) => [$id => self::$contactsByIdCache[$id] ?? null])
            ->filter();
    }

    /**
     * Czyści cache kontaktów — wymagane między testami (RefreshDatabase + static).
     */
    public static function clearContactsByIdsCache(): void
    {
        self::$contactsByIdCache = [];
    }

    /**
     * Kontrahent transportowy (firma transportowa)
     */
    public function transportContractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'transport_contractor_id');
    }

    public function driverContractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'driver_contractor_id');
    }

    /**
     * Miejsce startu imprezy (podstawienie autokaru).
     */
    public function startPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }

    /**
     * Początek programu (miejsce, do którego liczony jest transfer).
     */
    public function programStartPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'program_start_place_id');
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
     * Opiekun imprezy w biurze (opcjonalny) — pierwsze powiadomienia z portalu.
     */
    public function officeCaretaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_caretaker_id');
    }

    public function pilotContractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'pilot_contractor_id');
    }

    /**
     * Forma rozliczenia pilota na imprezie — override albo domyślna z karty kontrahenta.
     */
    public function resolvePilotSettlementForm(): ?\App\Enums\ContractorSettlementForm
    {
        if ($this->pilot_settlement_form instanceof \App\Enums\ContractorSettlementForm) {
            return $this->pilot_settlement_form;
        }

        $override = \App\Enums\ContractorSettlementForm::tryFromMixed($this->pilot_settlement_form);
        if ($override) {
            return $override;
        }

        $this->loadMissing('pilotContractor');

        $fromContractor = $this->pilotContractor?->settlement_form;

        return $fromContractor instanceof \App\Enums\ContractorSettlementForm
            ? $fromContractor
            : \App\Enums\ContractorSettlementForm::tryFromMixed($fromContractor);
    }

    public function resolvedPilotSettlementFormLabel(): ?string
    {
        return $this->resolvePilotSettlementForm()?->label();
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
            return false;
        }

        return (bool) ($this->pilot_portal_show_currency_exchange ?? false);
    }

    public function showsPilotBusCollections(): bool
    {
        if (! Schema::hasColumn('events', 'pilot_portal_show_bus_collections')) {
            return false;
        }

        return (bool) ($this->pilot_portal_show_bus_collections ?? false);
    }

    public function showsPilotAttendance(): bool
    {
        if (! Schema::hasColumn('events', 'pilot_portal_show_attendance')) {
            return false;
        }

        return (bool) ($this->pilot_portal_show_attendance ?? false);
    }

    public function pilotAdvanceLines(): HasMany
    {
        return $this->hasMany(PilotAdvanceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function pilotFeeLines(): HasMany
    {
        return $this->hasMany(PilotFeeLine::class)->orderBy('sort_order')->orderBy('id');
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
        return self::applyExcludeHotelTransferNames(
            $this->hasMany(EventProgramPoint::class)
                ->where('is_hotel', true)
                ->with(['contractor', 'contractorLocation'])
                ->orderBy('day')
                ->orderBy('order')
        );
    }

    /**
     * Punkty programu oznaczone jako dodatkowa usługa hotelu (bankiet, obiad, DJ itp.)
     */
    public function hotelServiceProgramPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class)
            ->where('is_hotel_service', true)
            ->with(['contractor', 'contractorLocation'])
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
     * Horyzont trwania imprezy (bez dnia fakultatywnego).
     *
     * Bierze max(duration_days, szablon, span dat) — świadomie bez max(day) punktów,
     * bo punkty na day = core+1 to opcje fakultatywne, nie wydłużenie wycieczki.
     * Chroni też przed błędnym duration_days=1 przy prawidłowym szablonie.
     */
    public function resolveCoreProgramDaysCount(): int
    {
        $this->loadMissing('eventTemplate');

        $fromDates = 1;
        if ($this->start_date && $this->end_date) {
            $fromDates = max(1, (int) $this->start_date->copy()->startOfDay()
                ->diffInDays($this->end_date->copy()->startOfDay()) + 1);
        }

        return max(
            1,
            (int) ($this->duration_days ?? 0),
            (int) ($this->eventTemplate?->duration_days ?? 0),
            $fromDates,
        );
    }

    /**
     * Czy dzień programu to slot opcji fakultatywnych (po horyzoncie trwania).
     */
    public function isFacultativeProgramDay(int $day): bool
    {
        return max(1, $day) > $this->resolveCoreProgramDaysCount();
    }

    /**
     * Numer dnia slotu opcji fakultatywnych (zawsze core+1).
     */
    public function facultativeProgramDay(): int
    {
        return $this->resolveCoreProgramDaysCount() + 1;
    }

    /**
     * Ogranicza day punktu do horyzontu wycieczki (i opcjonalnie slotu fakultatywnego).
     *
     * day === core+1 zostaje tylko gdy $allowFacultative; większe wartości (np. 99)
     * wracają na ostatni dzień wycieczki — nie tworzą przypadkowego fakultatywu.
     */
    public function clampProgramPointDay(int $day, bool $allowFacultative = true): int
    {
        $day = max(1, $day);
        $core = $this->resolveCoreProgramDaysCount();

        if ($day <= $core) {
            return $day;
        }

        if ($allowFacultative && $day === $core + 1) {
            return $core + 1;
        }

        return $core;
    }

    /**
     * Etykieta dnia w UI programu (Lista / zakładki).
     */
    public function programDayLabel(int $day): string
    {
        $day = max(1, $day);

        if ($this->isFacultativeProgramDay($day)) {
            return 'Opcje fakultatywne';
        }

        return 'Dzień '.$day;
    }

    /**
     * Liczba dni programu w UI Programu (zakładki + lista), w tym slot fakultatywny.
     *
     * Bierze max(core, max dzień punktów), ale nigdy powyżej core+1 —
     * chroni przed „Dzień 99”. Slot fakultatywny (core+1) nadal widać,
     * gdy są na nim punkty. Trasy (Transport, PDF) → resolveCoreProgramDaysCount().
     */
    public function resolveProgramDaysCount(): int
    {
        $core = $this->resolveCoreProgramDaysCount();
        $maxAllowed = $core + 1;

        $maxPointDay = 1;
        if ($this->exists && Schema::hasTable('event_program_points')) {
            $maxPointDay = max(1, (int) EventProgramPoint::query()
                ->where('event_id', $this->getKey())
                ->max('day'));
        }

        return min(
            $maxAllowed,
            max($core, $maxPointDay),
        );
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
        return $this->resolveQtyFieldForParticipantCount('gratis', $participantCount);
    }

    public function resolveDriverCountForParticipantCount(?int $participantCount = null): int
    {
        return $this->resolveQtyFieldForParticipantCount('driver', $participantCount);
    }

    public function resolveStaffCountForParticipantCount(?int $participantCount = null): int
    {
        return $this->resolveQtyFieldForParticipantCount('staff', $participantCount);
    }

    /**
     * Osoby operacyjne na noc / rezerwację hotelu — niezależnie od tego, czy pilot/kierowca są już przypisani.
     */
    public function resolveOperationalHeadcountForParticipantCount(?int $participantCount = null): int
    {
        $paying = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));

        return $paying
            + $this->resolveGratisCountForParticipantCount($paying)
            + $this->resolveStaffCountForParticipantCount($paying)
            + $this->resolveDriverCountForParticipantCount($paying);
    }

    protected function resolveQtyFieldForParticipantCount(string $field, ?int $participantCount = null): int
    {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));
        // Brak wariantu: obsługa/kierowca domyślnie 1, gratis 0.
        $default = in_array($field, ['staff', 'driver'], true) ? 1 : 0;

        return max(0, (int) ($this->closestQtyVariantForParticipantCount($count)?->{$field} ?? $default));
    }

    /**
     * Puste (null/'') → $defaultWhenEmpty. Jawne 0 = bez roli (obsługa/kierowca).
     */
    public static function normalizeOperationalCount(mixed $value, int $defaultWhenEmpty = 1): int
    {
        if ($value === null || $value === '') {
            return max(0, $defaultWhenEmpty);
        }

        return max(0, (int) $value);
    }

    protected function closestQtyVariantForParticipantCount(int $count): ?EventQty
    {
        if ($this->relationLoaded('qtyVariants')) {
            $exactVariant = $this->qtyVariants
                ->where('qty', $count)
                ->sortBy('id')
                ->first();

            if ($exactVariant) {
                return $exactVariant;
            }

            return $this->qtyVariants
                ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $count))
                ->first();
        }

        $exactVariant = $this->qtyVariants()
            ->where('qty', $count)
            ->orderBy('id')
            ->first();

        if ($exactVariant) {
            return $exactVariant;
        }

        return $this->qtyVariants()
            ->orderByRaw('ABS(qty - ?)', [$count])
            ->first();
    }

    /**
     * Zsynchronizuj wariant ilościowy eventu dla podanej grupy.
     * Aktualizuje gratis / obsługa / kierowca dla wariantu o dokładnym qty,
     * a jeśli go brak — tworzy nowy wariant bazując na najbliższym istniejącym.
     *
     * staffCount / driverCount: null = nie nadpisuj (przy update) albo dziedzicz z najbliższego (przy create).
     * Jawne 0 = wycieczka bez obsługi / bez kierowcy.
     */
    public function syncQtyVariantForGroup(
        int $participantCount,
        int $gratisCount,
        ?int $staffCount = null,
        ?int $driverCount = null,
    ): void {
        $participantCount = max(1, $participantCount);
        $gratisCount = max(0, $gratisCount);
        $staffCount = $staffCount !== null ? max(0, $staffCount) : null;
        $driverCount = $driverCount !== null ? max(0, $driverCount) : null;

        $exactVariant = $this->qtyVariants()
            ->where('qty', $participantCount)
            ->orderBy('id')
            ->first();

        if ($exactVariant) {
            $payload = ['gratis' => $gratisCount];

            if ($staffCount !== null) {
                $payload['staff'] = $staffCount;
            }

            if ($driverCount !== null) {
                $payload['driver'] = $driverCount;
            }

            $exactVariant->update($payload);

            return;
        }

        $closestVariant = $this->qtyVariants()
            ->get()
            ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $participantCount))
            ->first();

        $this->qtyVariants()->create([
            'qty' => $participantCount,
            'gratis' => $gratisCount,
            'staff' => $staffCount ?? (int) ($closestVariant->staff ?? 1),
            'driver' => $driverCount ?? (int) ($closestVariant->driver ?? 1),
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

    public function insurancePolicies(): HasMany
    {
        return $this->hasMany(EventInsurancePolicy::class)->orderBy('id');
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
        return app(\App\Services\EventInsuranceOperationalSync::class)->isEventInsurancePaid($this);
    }

    public function hasInsuranceDataSaved(): bool
    {
        if (! $this->requiresInsuranceWorkflow()) {
            return true;
        }

        if (Schema::hasTable('event_insurance_policies')) {
            $policies = $this->relationLoaded('insurancePolicies')
                ? $this->insurancePolicies
                : $this->insurancePolicies()->get();

            if ($policies->contains(fn (EventInsurancePolicy $policy): bool => $policy->hasOperationalData())) {
                return true;
            }
        }

        return filled($this->insurance_policy_number)
            || filled($this->insurance_terms)
            || filled($this->insurance_document_path)
            || filled($this->insurance_insured_list_path)
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

        $policies = Schema::hasTable('event_insurance_policies')
            ? ($this->relationLoaded('insurancePolicies')
                ? $this->insurancePolicies
                : $this->insurancePolicies()->get())
            : collect();

        if ($policies->isNotEmpty()) {
            $parts = $policies
                ->filter(fn (EventInsurancePolicy $policy): bool => $policy->hasOperationalData())
                ->map(function (EventInsurancePolicy $policy): ?string {
                    return filled($policy->policy_number) ? 'polisa '.$policy->policy_number : null;
                })
                ->filter()
                ->values()
                ->all();

            $docs = $policies->contains(fn (EventInsurancePolicy $p): bool => filled($p->document_path));
            $lists = $policies->contains(fn (EventInsurancePolicy $p): bool => filled($p->insured_list_path));

            $extra = array_filter([
                $docs ? 'polisa wgrana' : null,
                $lists ? 'lista ubezpieczonych' : null,
            ]);

            $parts = array_values(array_unique(array_merge($parts, $extra)));

            return 'W trakcie'.($parts !== [] ? ' ('.implode(', ', $parts).')' : '');
        }

        if (($this->insurance_status ?? 'pending') === 'in_progress' || $this->hasInsuranceDataSaved()) {
            $parts = array_filter([
                filled($this->insurance_policy_number) ? 'polisa '.$this->insurance_policy_number : null,
                filled($this->insurance_document_path) ? 'polisa wgrana' : null,
                filled($this->insurance_insured_list_path) ? 'lista ubezpieczonych' : null,
            ]);

            return 'W trakcie'.($parts !== [] ? ' ('.implode(', ', $parts).')' : '');
        }

        return 'Do zrobienia — uzupełnij polisę i opłać wszystkie pozycje w kosztach';
    }

    public static function normalizeInsuranceDocumentPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path[array_key_first($path)] ?? null;
        }

        return filled($path) ? (string) $path : null;
    }

    /**
     * Filament FileUpload trzyma stan jako tablicę — pojedynczy string z bazy powoduje błąd zapisu / podglądu (404).
     *
     * @return list<string>|null
     */
    public static function insuranceFileUploadState(mixed $path): ?array
    {
        $normalized = self::normalizeInsuranceDocumentPath($path);

        return $normalized !== null ? [$normalized] : null;
    }

    /**
     * Pliki operacyjne ubezpieczenia widoczne dla pilota (panel + pakiet PDF).
     *
     * @return list<array{key: string, label: string, path: string}>
     */
    public function insuranceFilesForPilot(): array
    {
        $files = [];

        if (Schema::hasTable('event_insurance_policies')) {
            $policies = $this->relationLoaded('insurancePolicies')
                ? $this->insurancePolicies
                : $this->insurancePolicies()->get();

            foreach ($policies as $index => $policy) {
                $suffix = $policies->count() > 1 ? ' #'.($index + 1) : '';
                $number = filled($policy->policy_number) ? ' ('.$policy->policy_number.')' : '';

                $policyPath = self::normalizeInsuranceDocumentPath($policy->document_path);
                if (filled($policyPath)) {
                    $files[] = [
                        'key' => 'policy_'.$policy->id,
                        'label' => 'Polisa ubezpieczeniowa'.$suffix.$number,
                        'path' => (string) $policyPath,
                    ];
                }

                $listPath = self::normalizeInsuranceDocumentPath($policy->insured_list_path);
                if (filled($listPath)) {
                    $files[] = [
                        'key' => 'insured_list_'.$policy->id,
                        'label' => 'Oryginalna lista ubezpieczonych'.$suffix.$number,
                        'path' => (string) $listPath,
                    ];
                }
            }

            if ($files !== []) {
                return $files;
            }
        }

        $policyPath = self::normalizeInsuranceDocumentPath($this->insurance_document_path);
        if (filled($policyPath)) {
            $files[] = [
                'key' => 'policy',
                'label' => 'Polisa ubezpieczeniowa',
                'path' => (string) $policyPath,
            ];
        }

        $listPath = self::normalizeInsuranceDocumentPath($this->insurance_insured_list_path ?? null);
        if (filled($listPath)) {
            $files[] = [
                'key' => 'insured_list',
                'label' => 'Oryginalna lista ubezpieczonych',
                'path' => (string) $listPath,
            ];
        }

        return $files;
    }

    /**
     * Lustro agregatu polis → kolumny events.insurance_* (kompatybilność wsteczna).
     */
    public function refreshInsuranceAggregateMirror(): void
    {
        if (! Schema::hasTable('event_insurance_policies')
            || ! Schema::hasColumn('events', 'insurance_policy_number')) {
            return;
        }

        $policies = $this->insurancePolicies()->orderBy('id')->get();

        if ($policies->isEmpty()) {
            $payload = [
                'insurance_policy_number' => null,
                'insurance_status' => 'pending',
                'insurance_payment_status' => 'pending',
                'insurance_amount' => null,
                'insurance_paid_at' => null,
                'insurance_document_path' => null,
                'insurance_terms' => null,
            ];

            if (Schema::hasColumn('events', 'insurance_insured_list_path')) {
                $payload['insurance_insured_list_path'] = null;
            }

            $this->forceFill($payload)->saveQuietly();

            return;
        }

        $primary = $policies->first(
            fn (EventInsurancePolicy $policy): bool => $policy->hasOperationalData()
        ) ?? $policies->first();

        $sync = app(\App\Services\EventInsuranceOperationalSync::class);
        $aggregateState = $sync->resolvePaymentStateForDayIds(
            $this,
            $this->dayInsurances()
                ->whereNotNull('insurance_id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );

        $payload = [
            'insurance_policy_number' => $primary?->policy_number,
            'insurance_status' => match ($aggregateState) {
                'paid' => 'completed',
                'partial' => 'in_progress',
                default => $policies->contains(fn (EventInsurancePolicy $p) => $p->hasOperationalData())
                    ? 'in_progress'
                    : 'pending',
            },
            'insurance_payment_status' => match ($aggregateState) {
                'paid' => 'paid',
                'partial' => 'partial',
                default => 'pending',
            },
            'insurance_amount' => $primary?->amount,
            'insurance_paid_at' => $policies
                ->filter(fn (EventInsurancePolicy $p): bool => filled($p->paid_at))
                ->sortByDesc(fn (EventInsurancePolicy $p) => $p->paid_at?->timestamp ?? 0)
                ->first()
                ?->paid_at,
            'insurance_document_path' => $policies
                ->first(fn (EventInsurancePolicy $p): bool => filled($p->document_path))
                ?->document_path,
            'insurance_terms' => $primary?->terms,
        ];

        if (Schema::hasColumn('events', 'insurance_insured_list_path')) {
            $payload['insurance_insured_list_path'] = $policies
                ->first(fn (EventInsurancePolicy $p): bool => filled($p->insured_list_path))
                ?->insured_list_path;
        }

        $this->forceFill($payload)->saveQuietly();
        $this->unsetRelation('insurancePolicies');
    }

    public function updateInsuranceFromFormData(array $data): void
    {
        if (Schema::hasTable('event_insurance_policies')) {
            $policy = $this->insurancePolicies()->orderBy('id')->first();

            if ($policy) {
                app(\App\Actions\Events\UpdateEventInsurancePolicyAction::class)(
                    $policy,
                    $data,
                );
            } else {
                app(\App\Actions\Events\CreateEventInsurancePolicyAction::class)(
                    $this,
                    $data,
                    day: null,
                    insuranceIds: [],
                );
            }

            $this->refresh();

            return;
        }

        $payload = [
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
            'insurance_status' => $data['insurance_status'] ?? 'pending',
            'insurance_payment_status' => $data['insurance_payment_status'] ?? 'pending',
            'insurance_amount' => filled($data['insurance_amount'] ?? null) ? (float) $data['insurance_amount'] : null,
            'insurance_paid_at' => $data['insurance_paid_at'] ?? null,
            'insurance_document_path' => self::normalizeInsuranceDocumentPath($data['insurance_document_path'] ?? null),
            'insurance_terms' => $data['insurance_terms'] ?? null,
        ];

        if (Schema::hasColumn('events', 'insurance_insured_list_path')) {
            $payload['insurance_insured_list_path'] = self::normalizeInsuranceDocumentPath(
                $data['insurance_insured_list_path'] ?? null
            );
        }

        $this->update($payload);

        try {
            app(\App\Services\EventInsurancePolicySettlementSync::class)->sync($this->fresh() ?? $this);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Event insurance policy settlement sync failed', [
                'event_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function insuranceSaveSummary(): string
    {
        $parts = array_filter([
            filled($this->insurance_policy_number) ? 'Polisa: '.$this->insurance_policy_number : null,
            filled($this->insurance_document_path) ? 'Plik polisy zapisany' : null,
            filled($this->insurance_insured_list_path) ? 'Lista ubezpieczonych zapisana' : null,
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
            'start_place_id' => $data['start_place_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
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

        if (Schema::hasColumn('events', 'program_start_place_id')) {
            $eventPayload['program_start_place_id'] = $data['program_start_place_id']
                ?? $template->start_place_id
                ?? null;
        }

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

        if (Schema::hasColumn('events', 'substitution_time')) {
            $eventPayload['substitution_time'] = $data['substitution_time'] ?? null;
        }

        if (Schema::hasColumn('events', 'departure_time')) {
            $eventPayload['departure_time'] = $data['departure_time'] ?? null;
        }

        if (Schema::hasColumn('events', 'return_time')) {
            $eventPayload['return_time'] = $data['return_time'] ?? null;
        }

        if (Schema::hasColumn('events', 'transport_company_name')) {
            $eventPayload['transport_company_name'] = $data['transport_company_name'] ?? null;
        }

        if (Schema::hasColumn('events', 'transport_contractor_id')) {
            $eventPayload['transport_contractor_id'] = $data['transport_contractor_id'] ?? null;
        }

        if (Schema::hasColumn('events', 'driver_contractor_id')) {
            $eventPayload['driver_contractor_id'] = $data['driver_contractor_id'] ?? null;
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

        // Kopiuj warianty ilości (qty) z szablonu — PRZED planem hotelowym (algorytm DP używa gratis/staff/driver).
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

        // Gdy szablon nie ma jeszcze wierszy PPP → qtyVariants() jest puste; i tak potrzebujemy
        // wariantu dla participant_count (kalkulacja / hotel / ubezpieczenia z gratisami).
        try {
            $participantCount = max(1, (int) ($event->participant_count ?? 1));
            $hasParticipantVariant = $event->qtyVariants()
                ->where('qty', $participantCount)
                ->exists();
            if (! $hasParticipantVariant) {
                $nearest = $template->qtyVariants()
                    ->get()
                    ->sortBy(fn ($v) => abs((int) $v->qty - $participantCount))
                    ->first();
                \App\Models\EventQty::create([
                    'event_id' => $event->id,
                    'qty' => $participantCount,
                    'gratis' => (int) ($nearest->gratis ?? 0),
                    'staff' => (int) ($nearest->staff ?? 1),
                    'driver' => (int) ($nearest->driver ?? 1),
                ]);
            }
        } catch (\Throwable $e) {
            // ignore qty bootstrap failures
        }

        if (
            array_key_exists('gratis_count', $data)
            || array_key_exists('staff_count', $data)
            || array_key_exists('driver_count', $data)
        ) {
            try {
                $event->syncQtyVariantForGroup(
                    (int) ($data['participant_count'] ?? 1),
                    (int) ($data['gratis_count'] ?? $event->resolveGratisCountForParticipantCount()),
                    array_key_exists('staff_count', $data)
                        ? self::normalizeOperationalCount($data['staff_count'])
                        : null,
                    array_key_exists('driver_count', $data)
                        ? self::normalizeOperationalCount($data['driver_count'])
                        : null,
                );
            } catch (\Throwable $e) {
                // ignore qty sync failures
            }
        }

        try {
            $hotelPlan = app(\App\Services\EventHotelPlanService::class);
            $hotelPlan->snapshotFromTemplate($event);
            $hotelPlan->linkStaysToProgramPoints($event);
            app(\App\Services\EventParticipantPropagationService::class)->assignOperationalOccupants($event->fresh());
        } catch (\Throwable $e) {
            // ignore when hotel tables unavailable
        }

        // Ubezpieczenia dniowe (idempotentnie, w horyzoncie imprezy).
        try {
            app(\App\Actions\Events\SyncEventDayInsurancesFromTemplateAction::class)($event, $template);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('copy day insurances from template failed', [
                'event_id' => $event->id,
                'event_template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Po hotelu + qty + ubezpieczeniach: pełny re-import settlement (wcześniejszy seed
        // z copyProgramPointsFromTemplate nie widział jeszcze dayInsurances / planu hotelowego).
        try {
            $event->refreshActiveSettlementCosts();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('createFromTemplate: final settlement refresh failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
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

        // Ceny per-person: SSoT EventCostCalculator na stanie imprezy (program, hotel, ubezpieczenia,
        // transfer_km dla wybranego miejsca startu) — nie silnik szablonu z surowym transfer_km.
        try {
            \App\Models\EventPricePerPerson::query()->where('event_id', $event->id)->delete();
            (new \App\Services\EventPriceCalculator)->calculateForEvent($event->fresh([
                'bus',
                'markup',
                'eventTemplate.taxes',
                'eventTemplate.markup',
                'qtyVariants',
                'programPoints.currency',
                'dayInsurances.insurance',
                'hotelStays.roomLines.currency',
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('createFromTemplate: EventPriceCalculator failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
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
        // Impreza z szablonu = wstępnie wypełniona: draft settlement + koszty planu z programu.
        EventSettlement::findOrCreateActiveForEvent($this);
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
     * Przelicz ilości (quantity) w punktach programu na podstawie osób koszowych
     * (płacący + zaznaczone dodatki: opiekunowie / pilot / kierowca).
     * Dotyczy punktów z group_size > 0.
     */
    public function resyncProgramPointQuantities(): void
    {
        $payingCount = max(1, (int) ($this->participant_count ?? 1));

        $this->programPoints()
            ->whereNotNull('group_size')
            ->where('group_size', '>', 0)
            ->each(function ($point) use ($payingCount) {
                $costHeadcount = ProgramPointCostPricing::costHeadcountForPoint($point, $this, $payingCount);
                $groupSize = max(1, (int) $point->group_size);
                $newQuantity = max(1, (int) ceil($costHeadcount / $groupSize));
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
     * Pełny koszt / suma do zapłaty grupy (baza + marża + podatki) — to samo źródło co kalkulator.
     */
    public function resolvedFullTotalCost(
        ?int $participantCount = null,
        ?int $gratisCount = null,
        ?int $startPlaceId = null
    ): float {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));
        $gratis = $gratisCount !== null
            ? max(0, (int) $gratisCount)
            : $this->resolveGratisCountForParticipantCount($count);

        try {
            $calc = \App\Services\EventCostCalculator::for($this)->calculate($count, $gratis);
            $total = round((float) ($calc['total_pln'] ?? 0), 2);
            if ($total > 0) {
                return $total;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Event::resolvedFullTotalCost calculator failed', [
                'event_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: ręczna cena × płacący (gdy brak punktów / planu do kalkulacji).
        $manualPpp = $this->resolveStoredManualPlnPricePerPerson($count);
        if ($manualPpp !== null && $manualPpp > 0) {
            return round($manualPpp * $count, 2);
        }

        return 0.0;
    }

    /**
     * Odświeża aktywne rozliczenie kosztami z aktualnego stanu eventu.
     * Nie tworzy nowego rozliczenia, jeśli jeszcze nie istnieje.
     */
    public function refreshActiveSettlementCosts(): void
    {
        app(\App\Services\EventSettlementSyncService::class)->refreshActiveCosts($this);
    }

    /**
     * Bazowy koszt imprezy (bez marży/podatków) — delegacja do EventCostCalculator.
     * Parametr $startPlaceId zachowany dla BC (kalkulator bierze place z eventu).
     */
    public function resolvedBaseTotalCost(
        ?int $participantCount = null,
        ?int $gratisCount = null,
        ?int $startPlaceId = null
    ): float {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));
        $gratis = $gratisCount !== null
            ? max(0, (int) $gratisCount)
            : $this->resolveGratisCountForParticipantCount($count);

        try {
            $calc = \App\Services\EventCostCalculator::for($this)->calculate($count, $gratis);
            $base = round((float) ($calc['base_pln'] ?? 0), 2);
            if ($base > 0) {
                return $base;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Event::resolvedBaseTotalCost calculator failed', [
                'event_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Ostatni fallback: zapisane total_cost (historycznie = baza).
        return round((float) ($this->total_cost ?? 0), 2);
    }

    /**
     * Koszt ubezpieczeń NNW przypisanych do dni imprezy.
     * SSoT: InsuranceCostCalculator — (płacący + gratis) × suma cen dnia.
     */
    public function insuranceCostPln(int $participantCount, int $gratisCount = 0): float
    {
        return $this->resolvedInsuranceCost($participantCount, $gratisCount);
    }

    protected function resolvedInsuranceCost(int $participantCount, int $gratisCount = 0): float
    {
        $dayInsurances = $this->relationLoaded('dayInsurances')
            ? $this->dayInsurances
            : $this->dayInsurances()->with('insurance')->get();

        $dayInsurances->loadMissing('insurance');

        return \App\Services\InsuranceCostCalculator::totalForDayAssignments(
            $dayInsurances,
            $participantCount,
            $gratisCount
        );
    }

    /**
     * Cena za osobę (PLN, zaokrąglona) — jedno źródło z EventCostCalculator.
     * Ręczna cena (is_manual) ma priorytet nad kalkulacją.
     */
    public function resolvedPricePerPerson(?int $participantCount = null): float
    {
        $count = max(1, (int) ($participantCount ?? $this->participant_count ?? 1));

        $manualPrice = $this->resolveStoredManualPlnPricePerPerson($count);
        if ($manualPrice !== null) {
            return $manualPrice;
        }

        try {
            $gratis = $this->resolveGratisCountForParticipantCount($count);
            $calc = \App\Services\EventCostCalculator::for($this)->calculate($count, $gratis);
            $rounded = (float) ($calc['price_per_person_rounded'] ?? 0);
            if ($rounded > 0) {
                return round($rounded, 2);
            }
            $raw = (float) ($calc['price_per_person'] ?? 0);
            if ($raw > 0) {
                return round($raw, 2);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Event::resolvedPricePerPerson calculator failed',
                ['event_id' => $this->id, 'error' => $e->getMessage()]
            );
        }

        return 0.0;
    }

    /**
     * Ręczna cena PLN z event_price_per_person (is_manual).
     */
    private function resolveStoredManualPlnPricePerPerson(int $count): ?float
    {
        $rows = $this->pricePerPerson()
            ->where('is_manual', true)
            ->with(['eventTemplateQty:id,qty', 'currency:id,code,symbol'])
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $plnIds = \App\Models\Currency::plnIds();

        $plnRows = $rows->filter(function ($row) use ($plnIds): bool {
            if ($plnIds !== [] && in_array((int) ($row->currency_id ?? 0), $plnIds, true)) {
                return true;
            }

            $code = strtoupper((string) ($row->currency?->code ?: $row->currency?->symbol ?: ''));

            return $code === 'PLN' || $code === '' || $row->currency_id === null;
        });

        $pool = $plnRows->isNotEmpty() ? $plnRows : $rows;

        $bestMatch = $pool->sortBy(function ($row) use ($count) {
            $qty = (int) ($row->eventTemplateQty->qty ?? $row->event_template_qty_id ?? 0);

            return abs($qty - $count);
        })->first();

        if (! $bestMatch) {
            return null;
        }

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

        $payload = ['status' => $newStatus];

        // Odprawa OK = potwierdzona i już odprawiona — utrzymuj spójność z gotowością operacyjną.
        if ($newStatus === self::STATUS_ODPRAWA_OK
            && Schema::hasColumn('events', 'check_in_status')
            && ($this->check_in_status ?? 'pending') !== 'completed'
        ) {
            $payload['check_in_status'] = 'completed';
        }

        $this->update($payload);

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
     * Flota operacyjna przypisana do imprezy (główny / wahadło / transfer).
     */
    public function eventVehicles(): HasMany
    {
        return $this->hasMany(EventVehicle::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function mainEventVehicle(): ?EventVehicle
    {
        if (! Schema::hasTable('event_vehicles')) {
            return null;
        }

        $rows = $this->relationLoaded('eventVehicles')
            ? $this->eventVehicles
            : $this->eventVehicles()->with('vehicle')->get();

        return $rows
            ->sortBy(fn (EventVehicle $row): array => [
                $row->role?->value === \App\Enums\EventVehicleRole::Main->value ? 0 : 1,
                (int) $row->sort_order,
                (int) $row->id,
            ])
            ->first();
    }

    public function mainFleetVehicle(): ?Vehicle
    {
        $assignment = $this->mainEventVehicle();
        if (! $assignment) {
            return null;
        }

        return $assignment->relationLoaded('vehicle')
            ? $assignment->vehicle
            : $assignment->vehicle()->first();
    }

    /**
     * Ustaw events.vehicle_registration na nr rej. pojazdu z rolą „główny” (fallback: pierwszy).
     */
    public function syncVehicleRegistrationFromFleet(): void
    {
        if (! Schema::hasTable('event_vehicles') || ! Schema::hasColumn('events', 'vehicle_registration')) {
            return;
        }

        $assignment = $this->eventVehicles()
            ->with('vehicle')
            ->get()
            ->sortBy(fn (EventVehicle $row): array => [
                $row->role?->value === \App\Enums\EventVehicleRole::Main->value ? 0 : 1,
                (int) $row->sort_order,
                (int) $row->id,
            ])
            ->first();

        $registration = $assignment?->vehicle?->registration_number;
        if (! filled($registration) || $this->vehicle_registration === $registration) {
            return;
        }

        $this->forceFill(['vehicle_registration' => $registration])->saveQuietly();
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
     * Szablon harmonogramu wpłat klientów (D−N / % / waluta→pilot).
     */
    public function paymentInstallmentTemplates(): HasMany
    {
        return $this->hasMany(EventPaymentInstallmentTemplate::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
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

    /**
     * Edytowalne pakiety PDF (pilot / hotel / kierowca / teczka).
     */
    public function packageDocuments(): HasMany
    {
        return $this->hasMany(EventPackageDocument::class);
    }

    /**
     * Heurystyka: nazwa wskazuje na nocleg, a nie dojazd/przejazd „do hotelu”.
     * „Przejazd do hotelu” zawiera „hotel”, ale to nie punkt hotelowy.
     */
    public static function templatePointLooksLikeHotel(object $point): bool
    {
        $name = mb_strtolower(trim((string) ($point->name ?? '')));

        if ($name === '' || self::programPointNameLooksLikeHotelTransfer($name)) {
            return false;
        }

        foreach (['hotel', 'nocleg', 'zakwater', 'pensjonat', 'hostel'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Przejazd / dojazd / transfer związany z hotelem — nie mylić z samym noclegiem.
     */
    public static function programPointNameLooksLikeHotelTransfer(?string $name): bool
    {
        $normalized = mb_strtolower(trim((string) $name));

        if ($normalized === '') {
            return false;
        }

        foreach (['przejazd', 'dojazd', 'transfer', 'powrót', 'powrot', 'wyjazd', 'odjazd', 'przyjazd'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Punkt programu oznaczony jako hotel, ale nazwą wskazujący na dojazd — nie właściwy nocleg.
     */
    public static function isHotelTransferProgramPoint(EventProgramPoint $point): bool
    {
        return (bool) ($point->is_hotel ?? false)
            && self::programPointNameLooksLikeHotelTransfer((string) ($point->name ?? ''));
    }

    /**
     * Wyklucza punkty typu „Przejazd do hotelu” z zapytań o właściwy nocleg hotelowy.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation
     */
    public static function applyExcludeHotelTransferNames($query)
    {
        foreach (['przejazd', 'dojazd', 'transfer', 'powrót', 'powrot', 'wyjazd', 'odjazd', 'przyjazd'] as $needle) {
            $query->whereRaw('LOWER(COALESCE(name, \'\')) NOT LIKE ?', ['%'.$needle.'%']);
        }

        return $query;
    }
}

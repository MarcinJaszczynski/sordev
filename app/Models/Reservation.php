<?php

namespace App\Models;

use App\Services\ReservationTaskSyncService;
use App\Support\ReservationAmountParser;
use App\Support\Reservations\ReservationHistoryLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $reservation) {
            $reservation->created_by ??= auth()->id();
            $reservation->currency_id ??= Currency::defaultPlnId();
            $reservation->reserved_at ??= now();
        });

        static::saving(function (self $reservation) {
            foreach (['reserved_at', 'expires_at', 'confirm_by', 'confirmed_at', 'deposit_due_at', 'deposit_paid_at'] as $attribute) {
                $value = $reservation->{$attribute};

                if ($value instanceof Carbon && ($value->year < 1970 || $value->year > 2038)) {
                    throw ValidationException::withMessages([
                        $attribute => 'Podaj poprawną datę w formacie DD.MM.RRRR.',
                    ]);
                }
            }

            $programPoint = $reservation->program_point_id
                ? EventProgramPoint::find($reservation->program_point_id)
                : null;

            $settlementCost = $reservation->settlement_cost_id
                ? EventSettlementCost::with('settlement')->find($reservation->settlement_cost_id)
                : null;

            $reservation->event_id ??= $programPoint?->event_id ?? $settlementCost?->settlement?->event_id;
            if ($programPoint?->contractor_id) {
                $reservation->contractor_id = $programPoint->contractor_id;
            } else {
                $reservation->contractor_id ??= $settlementCost?->contractor_id;
            }

            if (
                $reservation->isDirty('status')
                && in_array($reservation->status, ['confirmed', 'partially_confirmed', 'completed'], true)
                && blank($reservation->confirmed_at)
            ) {
                $reservation->confirmed_at = now()->toDateString();
            }

            if (! $reservation->program_point_id && $settlementCost?->source_type === 'program_point' && $settlementCost->source_id) {
                $reservation->program_point_id = $settlementCost->source_id;
            }

            if ($reservation->isDirty('reserved_amount') || $reservation->isDirty('participant_count') || $reservation->isDirty('amount_basis')) {
                $count = max(1, (int) ($reservation->participant_count ?? 1));
                $basis = (string) ($reservation->amount_basis ?? 'lump_sum');

                if ($basis === 'per_person' && $reservation->isDirty('reserved_amount')) {
                    // reserved_amount już powinno być łączne po dehydracji formularza
                } elseif ($basis === 'lump_sum' && $reservation->isDirty('reserved_amount')) {
                    $reservation->reserved_amount = ReservationAmountParser::resolve(
                        $reservation->reserved_amount,
                        1,
                    );
                } elseif (! $reservation->isDirty('reserved_amount') && $reservation->isDirty('participant_count') && $basis === 'per_person') {
                    // przelicz łączną kwotę przy zmianie liczby osób (stawka bez zmian)
                    $original = $reservation->getOriginal('reserved_amount');
                    $originalCount = max(1, (int) ($reservation->getOriginal('participant_count') ?? 1));
                    if ($original !== null) {
                        $unit = (float) $original / $originalCount;
                        $reservation->reserved_amount = round($unit * $count, 2);
                    }
                }
            }
        });

        static::created(function (self $reservation): void {
            ReservationHistoryLogger::logCreated($reservation);
        });

        static::updated(function (self $reservation): void {
            ReservationHistoryLogger::logUpdated($reservation);
        });

        static::deleted(function (self $reservation): void {
            ReservationHistoryLogger::logDeleted($reservation);
            app(ReservationTaskSyncService::class)->retireAll($reservation);
        });

        static::saved(function (self $reservation) {
            $reservation->loadMissing(['programPoint.event', 'settlementCost.settlement']);

            if (
                blank($reservation->program_point_id)
                && $reservation->settlementCost
                && $reservation->settlementCost->source_type === 'program_point'
                && $reservation->settlementCost->source_id
            ) {
                $reservation->forceFill([
                    'program_point_id' => $reservation->settlementCost->source_id,
                ])->saveQuietly();

                $reservation->loadMissing(['programPoint.event', 'settlementCost.settlement']);
            }

            if ($reservation->contractor_id && $reservation->programPoint && blank($reservation->programPoint->contractor_id)) {
                $reservation->programPoint->forceFill([
                    'contractor_id' => $reservation->contractor_id,
                ])->saveQuietly();
            }

            if ($reservation->contractor_id && $reservation->settlementCost && blank($reservation->settlementCost->contractor_id)) {
                $reservation->settlementCost->forceFill([
                    'contractor_id' => $reservation->contractor_id,
                ])->saveQuietly();
            }

            if ($reservation->programPoint?->event) {
                $event = $reservation->programPoint->event;
                $settlement = EventSettlement::findOrCreateActiveForEvent($event);
                $cost = $settlement->upsertCostFromProgramPoint(
                    $reservation->programPoint->fresh(['templatePoint', 'currency', 'reservations'])
                );

                $updates = [];

                if ($reservation->settlement_cost_id !== $cost->id) {
                    $updates['settlement_cost_id'] = $cost->id;
                }

                if ($reservation->event_id !== $event->id) {
                    $updates['event_id'] = $event->id;
                }

                if (! empty($updates)) {
                    $reservation->forceFill($updates)->saveQuietly();
                }

                $reservation->loadMissing('settlementCost');
            }

            $reservation->syncSettlementDepositState();

            $reservation->settlementCost?->settlement?->recalculateTotals();

            app(ReservationTaskSyncService::class)->sync($reservation);
        });
    }

    protected $fillable = [
        'settlement_cost_id',
        'event_id',
        'program_point_id',
        'contractor_id',
        'booking_reference',
        'participant_count',
        'reserved_amount',
        'currency_id',
        'convert_to_pln',
        'amount_basis',
        'participant_scope',
        'status',
        'reserved_at',
        'expires_at',
        'confirm_by',
        'confirmed_at',
        'deposit_due_at',
        'deposit_paid_at',
        'notes',
        'office_notes',
        'created_by',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'confirm_by' => 'date',
        'confirmed_at' => 'date',
        'deposit_due_at' => 'date',
        'deposit_paid_at' => 'date',
        'reserved_amount' => 'decimal:2',
        'participant_count' => 'integer',
        'convert_to_pln' => 'boolean',
    ];

    public static array $statuses = [
        'pending' => 'Oczekuje',
        'confirmed' => 'Potwierdzona',
        'partially_confirmed' => 'Częściowo potwierdzona',
        'cancelled' => 'Anulowana',
        'completed' => 'Zakończona',
        'not_required' => 'Nie wymaga',
    ];

    public static array $amountBases = [
        'per_person' => 'Za osobę',
        'lump_sum' => 'Za grupę (łącznie)',
    ];

    public static array $participantScopes = [
        'all' => 'Wszyscy uczestnicy',
        'paying' => 'Tylko płacący',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function settlementCost(): BelongsTo
    {
        return $this->belongsTo(EventSettlementCost::class, 'settlement_cost_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function programPoint(): BelongsTo
    {
        return $this->belongsTo(EventProgramPoint::class, 'program_point_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReservationAttachment::class);
    }

    public function historyEntries(): HasMany
    {
        return $this->hasMany(ReservationHistory::class)->latest('created_at');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function isActiveBooking(): bool
    {
        return ! in_array($this->status, ['cancelled', 'not_required'], true);
    }

    public function syncSettlementDepositState(): void
    {
        $cost = $this->settlementCost;

        if (! $cost || ! filled($this->deposit_paid_at)) {
            return;
        }

        $paidAt = $this->deposit_paid_at instanceof Carbon
            ? $this->deposit_paid_at
            : Carbon::parse($this->deposit_paid_at);

        $updates = [];

        if (! $cost->paid_at || $cost->paid_at->toDateString() !== $paidAt->toDateString()) {
            $updates['paid_at'] = $paidAt;
        }

        if (! in_array($cost->payment_status, ['advance_paid', 'paid'], true)) {
            $updates['payment_status'] = 'advance_paid';
        }

        if ($updates !== []) {
            $cost->forceFill($updates)->saveQuietly();
        }
    }

    public function getIsExpiredAttribute(): bool
    {
        $deadline = $this->confirm_by ?? $this->expires_at;

        return $deadline && $deadline instanceof Carbon && $deadline->isPast();
    }

    public function getIsConfirmedAttribute(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_confirmed', 'completed']);
    }
}

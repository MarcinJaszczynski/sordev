<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $reservation) {
            $reservation->created_by ??= auth()->id();
        });

        static::saving(function (self $reservation) {
            $programPoint = $reservation->program_point_id
                ? EventProgramPoint::find($reservation->program_point_id)
                : null;

            $settlementCost = $reservation->settlement_cost_id
                ? EventSettlementCost::with('settlement')->find($reservation->settlement_cost_id)
                : null;

            $reservation->event_id ??= $programPoint?->event_id ?? $settlementCost?->settlement?->event_id;
            $reservation->contractor_id ??= $programPoint?->contractor_id ?? $settlementCost?->contractor_id;

            if (! $reservation->program_point_id && $settlementCost?->source_type === 'program_point' && $settlementCost->source_id) {
                $reservation->program_point_id = $settlementCost->source_id;
            }
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
            }

            $reservation->settlementCost?->settlement?->recalculateTotals();
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
        'status',
        'reserved_at',
        'expires_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'reserved_amount' => 'decimal:2',
        'participant_count' => 'integer',
    ];

    public static array $statuses = [
        'pending' => 'Oczekuje',
        'confirmed' => 'Potwierdzona',
        'partially_confirmed' => 'Częściowo potwierdzona',
        'cancelled' => 'Anulowana',
        'completed' => 'Zakończona',
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

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsConfirmedAttribute(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_confirmed', 'completed']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class LegacyEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'legacy_id',
        'office_id',
        'name',
        'legacy_status',
        'start_datetime',
        'end_datetime',
        'duration_days',
        'participant_count',
        'guardians_count',
        'free_count',
        'client_name',
        'client_street',
        'client_city',
        'client_nip',
        'client_contact_person',
        'client_phone',
        'client_email',
        'pilot',
        'driver',
        'bus_board_time',
        'advance_payment',
        'notes',
        'start_description',
        'end_description',
        'diet_alert',
        'pilot_notes',
        'order_note',
        'legacy_purchaser_id',
        'contractor_id',
        'elements_json',
        'contractors_json',
        'payments_json',
        'notes_json',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'bus_board_time' => 'datetime',
        'elements_json' => 'array',
        'contractors_json' => 'array',
        'payments_json' => 'array',
        'notes_json' => 'array',
    ];

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function contractors(): BelongsToMany
    {
        return $this->belongsToMany(Contractor::class, 'contractor_legacy_event')
            ->withPivot(['role', 'type_name'])
            ->withTimestamps();
    }

    public function scopeForContractor($query, int $contractorId)
    {
        if (Schema::hasTable('contractor_legacy_event')) {
            return $query->where(function ($q) use ($contractorId): void {
                $q->where('contractor_id', $contractorId)
                    ->orWhereIn('id', function ($sub) use ($contractorId): void {
                        $sub->select('legacy_event_id')
                            ->from('contractor_legacy_event')
                            ->where('contractor_id', $contractorId);
                    });
            });
        }

        return $query->where('contractor_id', $contractorId);
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'Zakończona' => 'success',
            'Anulowana' => 'danger',
            'Archiwum' => 'gray',
            default => 'warning',
        };
    }
}

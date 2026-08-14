<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Support\StoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class EventSettlementDocument extends Model
{
    use HasFactory, HasStickyNotes, HasTasks;

    protected $fillable = [
        'settlement_id',
        'document_type',
        'document_number',
        'vendor_name',
        'total_amount',
        'currency_id',
        'issue_date',
        'payment_date',
        'payment_method',
        'payer_scope',
        'linked_cost_ids',
        'files',
        'notes',
        'attach_to_pilot_pdf',
        'attach_to_hotel_pdf',
        'attach_to_driver_pdf',
        'attach_to_folder_pdf',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'payment_date' => 'datetime',
        'linked_cost_ids' => 'array',
        'files' => 'array',
        'attach_to_pilot_pdf' => 'boolean',
        'attach_to_hotel_pdf' => 'boolean',
        'attach_to_driver_pdf' => 'boolean',
        'attach_to_folder_pdf' => 'boolean',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowany',
        'rejected' => 'Odrzucony',
    ];

    public static array $documentTypes = [
        'invoice' => 'Faktura',
        'payment_proof' => 'Dowód zapłaty',
        'wz' => 'WZ (wydanie zewnętrzne)',
        'receipt' => 'Paragon',
        'insurance_policy' => 'Polisa',
        'other' => 'Inny dokument',
    ];

    public static array $paymentMethods = [
        'cash' => 'Gotówka',
        'transfer' => 'Przelew',
        'card' => 'Karta',
        'other' => 'Inny',
    ];

    public static array $payerScopes = [
        'office' => 'Biuro',
        'pilot' => 'Pilot',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_by ??= Auth::id();
        });

        static::deleting(function (self $document): void {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            foreach ($document->files ?? [] as $path) {
                if (is_string($path) && $path !== '' && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        });
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getLinkedCostsSummaryAttribute(): string
    {
        $costIds = collect($this->linked_cost_ids ?? [])->filter()->values();
        if ($costIds->isEmpty()) {
            return 'Brak powiązanych pozycji';
        }

        $costs = EventSettlementCost::query()
            ->where('settlement_id', $this->settlement_id)
            ->whereIn('id', $costIds)
            ->orderBy('order')
            ->get(['id', 'name']);

        if ($costs->isEmpty()) {
            return 'Brak powiązanych pozycji';
        }

        return $costs->map(fn ($cost) => '#'.$cost->id.' '.$cost->name)->join(', ');
    }

    public function setFilesAttribute($value): void
    {
        $files = collect(is_array($value) ? $value : [])
            ->map(fn ($path) => StoragePath::normalize(is_string($path) ? $path : null))
            ->filter()
            ->values()
            ->all();

        $this->attributes['files'] = json_encode($files);
    }
}

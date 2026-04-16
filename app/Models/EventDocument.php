<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use App\Support\StoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class EventDocument extends Model
{
    use HasFactory, HasTasks;

    protected $fillable = [
        'event_id',
        'settlement_cost_id',
        'name',
        'notes',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'attach_to_pilot_pdf',
        'attach_to_hotel_pdf',
        'attach_to_driver_pdf',
        'attach_to_folder_pdf',
        'sort_order',
        'approval_status',
        'is_offer',
        'offer_status',
        'offer_sent_at',
        'offer_response_at',
        'offer_response_notes',
        'offer_modification_notes',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by',
    ];

    protected $casts = [
        'attach_to_pilot_pdf' => 'boolean',
        'attach_to_hotel_pdf' => 'boolean',
        'attach_to_driver_pdf' => 'boolean',
        'attach_to_folder_pdf' => 'boolean',
        'is_offer' => 'boolean',
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'settlement_cost_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'offer_sent_at' => 'datetime',
        'offer_response_at' => 'datetime',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowany',
        'rejected' => 'Odrzucony',
    ];

    public static array $offerStatuses = [
        'draft' => 'Szkic',
        'sent' => 'Wysłana',
        'responded' => 'Jest odpowiedź',
        'accepted' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
        'changes_requested' => 'Do modyfikacji',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (Auth::check()) {
                $model->created_by ??= Auth::id();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function settlementCost(): BelongsTo
    {
        return $this->belongsTo(EventSettlementCost::class, 'settlement_cost_id');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        if (!$this->file_size) {
            return '';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }

    public function setFilePathAttribute(?string $value): void
    {
        $this->attributes['file_path'] = StoragePath::normalize($value);
    }

    public function getPublicUrlAttribute(): ?string
    {
        return StoragePath::publicUrl($this->file_path);
    }

    public static array $pdfTargetLabels = [
        'attach_to_pilot_pdf'  => 'Pakiet pilota',
        'attach_to_hotel_pdf'  => 'Pakiet hotelu',
        'attach_to_driver_pdf' => 'Pakiet kierowcy',
        'attach_to_folder_pdf' => 'Pakiet teczki',
    ];
}

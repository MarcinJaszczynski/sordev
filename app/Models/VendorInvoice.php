<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class VendorInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'import_batch_id',
        'source',
        'ksef_number',
        'invoice_number',
        'issue_date',
        'sale_date',
        'due_date',
        'received_date',
        'payment_date',
        'currency',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'paid_amount',
        'payment_status',
        'payment_method',
        'approval_status',
        'matching_status',
        'seller_nip',
        'seller_name',
        'seller_street',
        'seller_post_code',
        'seller_city',
        'seller_country',
        'seller_email',
        'buyer_nip',
        'buyer_name',
        'contractor_id',
        'event_id',
        'event_program_point_id',
        'event_settlement_cost_id',
        'settlement_document_id',
        'sync_to_settlement',
        'pdf_path',
        'original_filename',
        'notes',
        'internal_notes',
        'matching_hints',
        'raw_payload',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'sale_date' => 'date',
        'due_date' => 'date',
        'received_date' => 'date',
        'payment_date' => 'date',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'sync_to_settlement' => 'boolean',
        'matching_hints' => 'array',
        'raw_payload' => 'array',
        'approved_at' => 'datetime',
    ];

    public static array $paymentStatuses = [
        'due' => 'Do zapłaty',
        'paid' => 'Opłacona',
        'partial' => 'Częściowo opłacona',
        'cancelled' => 'Anulowana',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
    ];

    public static array $matchingStatuses = [
        'auto_matched' => 'Dopasowana auto',
        'manual' => 'Przypisana ręcznie',
        'unmatched' => 'Do opracowania',
        'needs_review' => 'Wymaga weryfikacji',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(VendorInvoiceImportBatch::class, 'import_batch_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function programPoint(): BelongsTo
    {
        return $this->belongsTo(EventProgramPoint::class, 'event_program_point_id');
    }

    public function settlementCost(): BelongsTo
    {
        return $this->belongsTo(EventSettlementCost::class, 'event_settlement_cost_id');
    }

    public function settlementDocument(): BelongsTo
    {
        return $this->belongsTo(EventSettlementDocument::class, 'settlement_document_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorInvoiceLine::class)->orderBy('line_order');
    }

    public function searchableText(): string
    {
        $parts = [
            $this->invoice_number,
            $this->ksef_number,
            $this->seller_name,
            $this->notes,
            $this->internal_notes,
        ];

        foreach ($this->lines as $line) {
            $parts[] = $line->name;
            $parts[] = $line->description;
        }

        if (is_array($this->raw_payload)) {
            $parts[] = json_encode($this->raw_payload, JSON_UNESCAPED_UNICODE);
        }

        return implode(' ', array_filter($parts));
    }

    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->pdf_path) {
            return null;
        }

        return Storage::disk(config('invoices.storage_disk', 'public'))->url($this->pdf_path);
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'due'
            && $this->due_date
            && $this->due_date->isPast();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorInvoiceImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'source_type',
        'status',
        'source_filename',
        'source_path',
        'imported_count',
        'matched_count',
        'unmatched_count',
        'error_count',
        'error_log',
        'meta',
        'completed_at',
    ];

    protected $casts = [
        'error_log' => 'array',
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];

    public static array $sourceTypes = [
        'csv' => 'CSV KSeF',
        'xml' => 'XML KSeF',
        'pdf_bulk' => 'Zbiorczy PDF',
    ];

    public static array $statuses = [
        'pending' => 'Oczekuje',
        'processing' => 'Przetwarzanie',
        'completed' => 'Zakończony',
        'failed' => 'Błąd',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'import_batch_id');
    }
}

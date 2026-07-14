<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankPaymentImportBatch extends Model
{
    protected $fillable = [
        'imported_by',
        'bank',
        'source_filename',
        'lines_total',
        'lines_applied',
        'lines_skipped',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankPaymentImportLine::class, 'batch_id');
    }
}

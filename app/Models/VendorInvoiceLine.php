<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorInvoiceLine extends Model
{
    protected $fillable = [
        'vendor_invoice_id',
        'line_order',
        'name',
        'quantity',
        'unit',
        'vat_rate',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'description',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }
}

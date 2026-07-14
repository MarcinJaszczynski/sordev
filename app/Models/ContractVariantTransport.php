<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVariantTransport extends Model
{
    protected $fillable = [
        'contract_variant_id',
        'transport_code',
        'icao_codes',
        'sort_order',
    ];

    protected $casts = [
        'icao_codes' => 'array',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ContractVariant::class, 'contract_variant_id');
    }
}

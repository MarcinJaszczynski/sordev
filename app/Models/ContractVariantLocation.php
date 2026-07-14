<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVariantLocation extends Model
{
    protected $fillable = [
        'contract_variant_id',
        'scope_type',
        'country_code',
        'locality',
        'sort_order',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ContractVariant::class, 'contract_variant_id');
    }
}

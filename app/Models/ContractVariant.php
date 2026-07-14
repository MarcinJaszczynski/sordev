<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractVariant extends Model
{
    protected $fillable = [
        'contract_id',
        'sort_order',
        'travelers_count',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ContractVariantLocation::class)->orderBy('sort_order');
    }

    public function transports(): HasMany
    {
        return $this->hasMany(ContractVariantTransport::class)->orderBy('sort_order');
    }
}

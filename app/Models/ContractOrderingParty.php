<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractOrderingParty extends Model
{
    protected $fillable = [
        'contract_id',
        'contractor_id',
        'sort_order',
        'name',
        'email',
        'phone',
        'nip',
        'street',
        'house_number',
        'city',
        'postal_code',
        'notes',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return array<string, string|null>
     */
    public static function snapshotFromContractor(Contractor $contractor): array
    {
        return [
            'contractor_id' => $contractor->id,
            'name' => $contractor->name,
            'email' => $contractor->email,
            'phone' => $contractor->phone,
            'nip' => $contractor->nip,
            'street' => $contractor->street,
            'house_number' => $contractor->house_number,
            'city' => $contractor->city,
            'postal_code' => $contractor->postal_code,
        ];
    }
}

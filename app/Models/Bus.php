<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'capacity',
        'package_price_per_day',
        'package_km_per_day',
        'extra_km_price',
        'currency',
        'convert_to_pln',
    ];

    /**
     * Czy model ma atrybuty potrzebne do wyceny transportu
     * (odróżnia pełny rekord od eager load typu bus:id,name).
     */
    public function hasTransportPricingAttributesLoaded(): bool
    {
        $attrs = $this->getAttributes();

        foreach (['capacity', 'package_price_per_day', 'package_km_per_day', 'extra_km_price', 'currency'] as $key) {
            if (! array_key_exists($key, $attrs)) {
                return false;
            }
        }

        return true;
    }
}

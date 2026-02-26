<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insurance extends Model
{
    use SoftDeletes;

    protected $table = 'insurances';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'description',
        'price_per_person',
        'active',
        'insurance_per_day',
        'insurance_per_person',
        'insurance_enabled',
    ];

    protected $casts = [
        'price_per_person' => 'decimal:2',
        'active' => 'boolean',
        'insurance_per_day' => 'boolean',
        'insurance_per_person' => 'boolean',
        'insurance_enabled' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function eventTemplateDayInsurances()
    {
        return $this->hasMany(EventTemplateDayInsurance::class);
    }
}

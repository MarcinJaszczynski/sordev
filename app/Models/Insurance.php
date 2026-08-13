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
        'coverage_type',
        'description',
        'price_per_person',
        'active',
        'insurance_per_day',
        'insurance_per_person',
        'insurance_enabled',
    ];

    public const COVERAGE_NNW = 'nnw';

    public const COVERAGE_KL = 'kl';

    public static function coverageTypeOptions(): array
    {
        return [
            self::COVERAGE_NNW => 'NNW',
            self::COVERAGE_KL => 'KL',
        ];
    }

    public static function coverageTypeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return self::coverageTypeOptions()[$type] ?? strtoupper($type);
    }

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

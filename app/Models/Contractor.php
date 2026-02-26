<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ContractorType;

/**
 * Model Contractor
 * Reprezentuje kontrahenta w systemie.
 *
 * @property int $id
 * @property string $name
 * @property string|null $street
 * @property string|null $house_number
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $status
 * @property string|null $office_notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Contractor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Pola masowo przypisywalne
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'street',
        'house_number',
        'city',
        'postal_code',
        'status',
        'office_notes',
    ];

    /**
     * Relacja wiele-do-wielu z kontaktami
     */
    public function contacts()
    {
    return $this->belongsToMany(Contact::class, 'contractor_contact');
    }

    /**
     * Relacja wiele-do-wielu z typami kontrahentów
     */
    public function types()
    {
        return $this->belongsToMany(ContractorType::class, 'contractor_contractortype')->withTimestamps();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    use HasFactory, HasTasks, SoftDeletes;

    /**
     * Pola masowo przypisywalne
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'firstname',
        'surname',
        'email',
        'phone',
        'nip',
        'www',
        'street',
        'house_number',
        'city',
        'postal_code',
        'region',
        'country',
        'description',
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

    /**
     * Punkty programu imprezy wykonywane przez tego kontrahenta
     */
    public function programPoints()
    {
        return $this->hasMany(EventProgramPoint::class);
    }

    /**
     * Wydatki/koszty rozliczenia związane z tym kontrahentą
     */
    public function settlementCosts()
    {
        return $this->hasMany(EventSettlementCost::class);
    }

    /**
     * Rezerwacje złożone przez tego kontrahenta
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}

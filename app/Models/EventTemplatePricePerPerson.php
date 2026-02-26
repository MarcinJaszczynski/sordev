<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model EventTemplatePricePerPerson
 * Reprezentuje cenę za osobę dla wariantu ilości uczestników w szablonie wydarzenia.
 *
 * @property int $id
 * @property int $event_template_id
 * @property int $event_template_qty_id
 * @property int $currency_id
 * @property float $price_per_person
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class EventTemplatePricePerPerson extends Model
{
    use HasFactory;
    protected $table = 'event_template_price_per_person';
    /**
     * Pola masowo przypisywalne
     * @var array<int, string>
     */
    protected $fillable = [
        'event_template_id',
        'event_template_qty_id',
        'currency_id',
        'start_place_id',
        'price_per_person',
        'transport_cost',
        'price_base',
        'markup_amount',
        'tax_amount',
        'price_with_tax',
        'tax_breakdown',
    ];

    /**
     * Castowanie pól
     */
    protected $casts = [
        'price_per_person' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'price_base' => 'decimal:2',
        'markup_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'price_with_tax' => 'decimal:2',
        'tax_breakdown' => 'array',
    ];

    /**
     * Relacja do szablonu wydarzenia
     */
    public function eventTemplate()
    {
        return $this->belongsTo(EventTemplate::class);
    }
    /**
     * Relacja do wariantu ilości uczestników
     */
    public function eventTemplateQty()
    {
        return $this->belongsTo(EventTemplateQty::class);
    }
    /**
     * Relacja do waluty
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Relacja do miejsca startowego
     */
    public function startPlace()
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }
}

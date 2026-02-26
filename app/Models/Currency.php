<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Currency
 * Reprezentuje walutę w systemie.
 *
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property float $exchange_rate
 * @property \Illuminate\Support\Carbon|null $last_updated_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Currency extends Model
{
    use HasFactory;

    /**
     * Pola masowo przypisywalne
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'symbol',
        'exchange_rate',
        'last_updated_at',
    ];

    /**
     * Rzutowanie pól na typy
     * @var array<string, string>
     */
    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    /**
     * Automatyczne ustawianie daty ostatniej aktualizacji przy tworzeniu
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($currency) {
            if (is_null($currency->last_updated_at)) {
                $currency->last_updated_at = now();
            }
        });
    }

    /**
     * Relacja do cen szablonów eventów
     */
    public function eventTemplatePrices()
    {
        return $this->hasMany(EventTemplatePricePerPerson::class);
    }

    /**
     * Zwraca (cache static) tablicę ID waluty PLN (różne warianty nazwy/kodu).
     */
    public static function plnIds(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = static::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })->pluck('id')->toArray();
        return $cache;
    }
}

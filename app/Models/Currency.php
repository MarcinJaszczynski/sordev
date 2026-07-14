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
     *
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
     *
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
     * Historyczne kursy walut
     */
    public function rateSnapshots()
    {
        return $this->hasMany(CurrencyRateSnapshot::class);
    }

    /**
     * Zwraca (cache static) tablicę ID waluty PLN (różne warianty nazwy/kodu).
     */
    public static function plnIds(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = static::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })->pluck('id')->toArray();

        return $cache;
    }

    public function displayLabel(): string
    {
        $code = filled($this->code) ? trim((string) $this->code) : null;
        $symbol = filled($this->symbol) ? trim((string) $this->symbol) : null;
        $name = filled($this->name) ? trim((string) $this->name) : null;

        if ($code !== null) {
            if ($name !== null && strcasecmp($code, $name) !== 0) {
                return "{$code} — {$name}";
            }

            return $code;
        }

        if ($symbol !== null) {
            if ($name !== null && strcasecmp($symbol, $name) !== 0) {
                return "{$symbol} — {$name}";
            }

            return $symbol;
        }

        return $name ?? "Waluta #{$this->id}";
    }

    /**
     * Opcje Select w Filament — zawsze string, nigdy null (legacy: brak code).
     *
     * @return array<int, string>
     */
    public static function filamentSelectOptions(): array
    {
        return static::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $currency): array => [
                (int) $currency->id => $currency->displayLabel(),
            ])
            ->all();
    }

    public static function defaultPlnId(): ?int
    {
        $plnIds = static::plnIds();
        if ($plnIds !== []) {
            return (int) $plnIds[0];
        }

        return static::query()->where('code', 'PLN')->value('id')
            ?? static::query()->where('symbol', 'PLN')->value('id');
    }
}

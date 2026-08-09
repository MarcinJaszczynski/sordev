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

    /** @var list<int>|null */
    private static ?array $plnIdsCache = null;

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

        static::saved(fn () => static::clearPlnIdsCache());
        static::deleted(fn () => static::clearPlnIdsCache());
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
     * Zwraca tablicę ID waluty PLN (różne warianty nazwy/kodu).
     *
     * Cache jest czyszczony przy zmianie walut oraz z testów (RefreshDatabase
     * nie odpala eventów Eloquent przy truncate).
     *
     * @return list<int>
     */
    public static function plnIds(): array
    {
        if (self::$plnIdsCache !== null) {
            return self::$plnIdsCache;
        }

        self::$plnIdsCache = static::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return self::$plnIdsCache;
    }

    public static function clearPlnIdsCache(): void
    {
        self::$plnIdsCache = null;
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

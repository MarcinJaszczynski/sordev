<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model KategoriaSzablonu
 * Reprezentuje kategorię szablonu w systemie.
 *
 * @property int $id
 * @property string $nazwa
 * @property string|null $opis
 * @property string|null $uwagi
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class KategoriaSzablonu extends Model
{
    /**
     * Pola masowo przypisywalne
     * @var array<int, string>
     */
    protected $fillable = [
        'nazwa',
        'opis',
        'uwagi',
        'parent_id',
    ];

    /**
     * Relacja polimorficzna do tagów
     */
    public function tags()
    {
        return $this->morphToMany(\App\Models\Tag::class, 'taggable');
    }

    /**
     * Relacja do kategorii nadrzędnej
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Relacja do kategorii podrzędnych
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Payer
 * Reprezentuje płatnika w systemie.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Payer extends Model
{
    use HasFactory;

    /**
     * Pola masowo przypisywalne
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];
}

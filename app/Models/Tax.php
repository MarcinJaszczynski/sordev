<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = [
        'name',
        'percentage',
        'apply_to_base',
        'apply_to_markup',
        'is_active',
        'description',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'apply_to_base' => 'boolean',
        'apply_to_markup' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Oblicza kwotę podatku na podstawie podanych wartości
     * 
     * @param float $baseAmount Suma bez narzutu
     * @param float $markupAmount Narzut
     * @return float Kwota podatku
     */
    public function calculateTaxAmount(float $baseAmount, float $markupAmount = 0): float
    {
        if (!$this->is_active) {
            return 0;
        }

        $taxableAmount = 0;

        if ($this->apply_to_base) {
            $taxableAmount += $baseAmount;
        }

        if ($this->apply_to_markup) {
            $taxableAmount += $markupAmount;
        }

        return ($taxableAmount * $this->percentage) / 100;
    }

    /**
     * Scope dla aktywnych podatków
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Zwraca formatowaną wartość procentową
     */
    public function getFormattedPercentageAttribute(): string
    {
        return $this->percentage . '%';
    }

    /**
     * Relacja wiele-do-wielu z szablonami imprez
     */
    public function eventTemplates()
    {
        return $this->belongsToMany(EventTemplate::class, 'event_template_tax');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorLocation extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            if (! $location->is_primary || ! $location->contractor_id) {
                return;
            }

            static::query()
                ->where('contractor_id', $location->contractor_id)
                ->when($location->exists, fn ($query) => $query->whereKeyNot($location->getKey()))
                ->update(['is_primary' => false]);
        });
    }

    protected $fillable = [
        'contractor_id',
        'name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'region',
        'country',
        'contact_first_name',
        'contact_last_name',
        'phone',
        'email',
        'is_primary',
        'status',
        'office_notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function contactDisplayName(): ?string
    {
        $name = trim(implode(' ', array_filter([
            filled($this->contact_first_name) ? trim((string) $this->contact_first_name) : null,
            filled($this->contact_last_name) ? trim((string) $this->contact_last_name) : null,
        ])));

        return $name !== '' ? $name : null;
    }

    public function displayLabel(): string
    {
        $this->loadMissing('contractor');

        $contractorName = $this->contractor?->displayLabel() ?? 'Kontrahent';
        $branch = trim((string) $this->name);
        $city = filled($this->city) ? trim((string) $this->city) : 'brak miasta';

        if ($branch === '') {
            return "{$contractorName} ({$city})";
        }

        return "{$contractorName} — {$branch} ({$city})";
    }

    public function shortLabel(): string
    {
        $branch = trim((string) $this->name);
        $city = filled($this->city) ? trim((string) $this->city) : null;

        if ($branch !== '' && $city) {
            return "{$branch} ({$city})";
        }

        if ($branch !== '') {
            return $branch;
        }

        return $city ?? "Oddział #{$this->id}";
    }
}

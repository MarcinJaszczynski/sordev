<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventInsurancePolicy extends Model
{
    protected $table = 'event_insurance_policies';

    protected $fillable = [
        'event_id',
        'policy_number',
        'status',
        'payment_status',
        'amount',
        'paid_at',
        'document_path',
        'insured_list_path',
        'terms',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function dayInsurances(): HasMany
    {
        return $this->hasMany(EventDayInsurance::class, 'event_insurance_policy_id');
    }

    public function isCompleted(): bool
    {
        return app(\App\Services\EventInsuranceOperationalSync::class)
            ->resolvePaymentStateForDayIds(
                $this->event ?? $this->event()->firstOrFail(),
                $this->dayInsurances()->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ) === 'paid';
    }

    public function hasOperationalData(): bool
    {
        return filled($this->policy_number)
            || filled($this->terms)
            || filled($this->document_path)
            || filled($this->insured_list_path)
            || filled($this->amount)
            || filled($this->paid_at)
            || (($this->status ?? 'pending') !== 'pending')
            || (($this->payment_status ?? 'pending') !== 'pending');
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormState(): array
    {
        return [
            'insurance_policy_number' => $this->policy_number,
            'insurance_amount' => $this->amount,
            'insurance_document_path' => Event::insuranceFileUploadState($this->document_path),
            'insurance_insured_list_path' => Event::insuranceFileUploadState($this->insured_list_path),
            'insurance_terms' => $this->terms,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributesFromFormData(array $data): array
    {
        return [
            'policy_number' => $data['insurance_policy_number'] ?? null,
            'amount' => filled($data['insurance_amount'] ?? null) ? (float) $data['insurance_amount'] : null,
            'document_path' => Event::normalizeInsuranceDocumentPath($data['insurance_document_path'] ?? null),
            'insured_list_path' => Event::normalizeInsuranceDocumentPath($data['insurance_insured_list_path'] ?? null),
            'terms' => $data['insurance_terms'] ?? null,
        ];
    }
}

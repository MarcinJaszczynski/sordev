<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContractorSettlementForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Umowa cywilnoprawna z pilotem na imprezę (1:1).
 * Źródło tożsamości: contractor_id; PDF i share w panelu są opcjonalne.
 */
class EventPilotAgreement extends Model
{
    protected $fillable = [
        'event_id',
        'contractor_id',
        'contract_template_id',
        'settlement_form',
        'contract_number',
        'body_html',
        'pdf_path',
        'generated_at',
        'generated_by',
        'sent_at',
        'sent_by',
        'shared_in_portal',
        'event_document_id',
    ];

    protected $casts = [
        'settlement_form' => ContractorSettlementForm::class,
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'shared_in_portal' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function eventDocument(): BelongsTo
    {
        return $this->belongsTo(EventDocument::class);
    }

    public function hasPdf(): bool
    {
        return filled($this->pdf_path) && Storage::disk('public')->exists($this->pdf_path);
    }

    public function settlementFormLabel(): string
    {
        $form = $this->settlement_form instanceof ContractorSettlementForm
            ? $this->settlement_form
            : ContractorSettlementForm::tryFromMixed($this->settlement_form);

        return $form?->label() ?? 'Umowa o dzieło';
    }
}

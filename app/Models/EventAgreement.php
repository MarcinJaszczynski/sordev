<?php

namespace App\Models;

use App\Models\Concerns\HasCustomAgreementContent;
use App\Services\AgreementPaymentSyncService;
use App\Services\AgreementTemplateRenderer;
use App\Services\ContractGroupPricingService;
use App\Services\ContractOrderingPartyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Umowy w tabeli event_agreements (legacy, przed migracją do contracts).
 */
class EventAgreement extends Model
{
    use HasCustomAgreementContent;
    use HasFactory;

    public const TYPE_GROUP = 'group';

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CUSTOM = 'custom';

    public const BODY_EDIT_TEMPLATE = 'template';

    public const BODY_EDIT_MANUAL = 'manual';

    public const BODY_EDIT_UPLOAD = 'upload';

    public const PAYMENT_SCHEME_LUMP_SUM = 'lump_sum';

    public const PAYMENT_SCHEME_INSTALLMENTS = 'installments';

    public const PAYMENT_SCHEME_INDIVIDUAL = 'individual';

    protected $table = 'event_agreements';

    protected $fillable = [
        'event_id',
        'contract_template_id',
        'agreement_type',
        'participant_payment_id',
        'title',
        'agreement_number',
        'agreement_date',
        'event_name',
        'event_start_date',
        'event_end_date',
        'customer_name',
        'customer_email',
        'customer_phone',
        'ordering_party_notes',
        'participant_name',
        'participant_birth_date',
        'participant_email',
        'participant_phone',
        'participant_count',
        'unit_price',
        'payment_scheme',
        'amount_due',
        'amount_paid',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'signer_name',
        'signer_email',
        'signer_phone',
        'signed_at',
        'paid_at',
        'public_token',
        'public_token_expires_at',
        'agreement_body',
        'body_edit_mode',
        'custom_agreement_document_path',
        'attachments',
        'admin_notes',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'agreement_date' => 'date',
        'event_start_date' => 'date',
        'event_end_date' => 'date',
        'participant_birth_date' => 'date',
        'unit_price' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'signed_at' => 'datetime',
        'paid_at' => 'datetime',
        'public_token_expires_at' => 'datetime',
        'attachments' => 'array',
        'meta' => 'array',
    ];

    public static array $statuses = [
        'draft' => 'Szkic',
        'sent' => 'Wysłana',
        'signed' => 'Podpisana',
        'completed' => 'Opłacona',
        'cancelled' => 'Anulowana',
    ];

    public static array $paymentStatuses = [
        'pending' => 'Oczekuje na płatność',
        'paid' => 'Opłacona',
        'failed' => 'Nieudana',
    ];

    public static array $paymentMethods = [
        'demo_transfer' => 'Przelew online (demo)',
        'demo_card' => 'Karta (demo)',
        'demo_blik' => 'BLIK (demo)',
        'other' => 'Inna',
    ];

    public static array $types = [
        self::TYPE_GROUP => 'Grupowa',
        self::TYPE_INDIVIDUAL => 'Indywidualna',
        self::TYPE_CUSTOM => 'Umowa własna',
    ];

    public static array $bodyEditModes = [
        self::BODY_EDIT_TEMPLATE => 'Z szablonu',
        self::BODY_EDIT_MANUAL => 'Ręczna edycja',
        self::BODY_EDIT_UPLOAD => 'Wgrany dokument PDF',
    ];

    public static array $paymentSchemes = [
        self::PAYMENT_SCHEME_LUMP_SUM => 'Jednorazowa',
        self::PAYMENT_SCHEME_INSTALLMENTS => 'W transzach',
        self::PAYMENT_SCHEME_INDIVIDUAL => 'Płatności indywidualne',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $agreement): void {
            $agreement->public_token ??= Str::random(48);
            $agreement->agreement_date ??= now()->toDateString();
            $agreement->status ??= 'draft';
            $agreement->payment_status ??= 'pending';
            $agreement->agreement_type ??= self::TYPE_GROUP;
            $agreement->currency = strtoupper((string) ($agreement->currency ?: 'PLN'));

            if (blank($agreement->title)) {
                $agreement->title = 'Umowa imprezy';
            }

            if (blank($agreement->event_name) && $agreement->event) {
                $agreement->event_name = $agreement->event->name;
            }

            if ($agreement->agreement_type === self::TYPE_INDIVIDUAL && blank($agreement->participant_count)) {
                $agreement->participant_count = 1;
            }

            $agreement->body_edit_mode ??= $agreement->agreement_type === self::TYPE_CUSTOM
                ? self::BODY_EDIT_UPLOAD
                : self::BODY_EDIT_TEMPLATE;
        });

        static::created(function (self $agreement): void {
            $updates = [];

            if (blank($agreement->agreement_number)) {
                $updates['agreement_number'] = sprintf('UM/%s/%05d', now()->format('Y'), $agreement->id);
            }

            if (blank($agreement->agreement_body) && $agreement->shouldAutoGenerateAgreementBody()) {
                $agreement->fill($updates);
                $updates['agreement_body'] = $agreement->renderAgreementBody();
            }

            if (! empty($updates)) {
                $agreement->forceFill($updates)->saveQuietly();
            }
        });

        static::saved(function (self $agreement): void {
            $syncRelevantFields = [
                'agreement_number',
                'agreement_type',
                'participant_payment_id',
                'participant_name',
                'customer_name',
                'signer_name',
                'amount_due',
                'amount_paid',
                'status',
                'payment_status',
                'payment_method',
                'paid_at',
                'meta',
            ];

            if (! $agreement->wasRecentlyCreated && ! $agreement->wasChanged($syncRelevantFields)) {
                return;
            }

            app(AgreementPaymentSyncService::class)->sync($agreement->fresh());
        });

        static::deleted(function (self $agreement): void {
            app(AgreementPaymentSyncService::class)->remove($agreement);
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }

    public function orderingParties(): HasMany
    {
        return $this->hasMany(EventAgreementOrderingParty::class)->orderBy('sort_order');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(EventAgreementPaymentSchedule::class)->orderBy('sort_order');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::$statuses[$this->status] ?? $this->status;
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::$paymentStatuses[$this->payment_status] ?? $this->payment_status;
    }

    public function getAgreementTypeLabelAttribute(): string
    {
        return self::$types[$this->agreement_type] ?? $this->agreement_type;
    }

    public function getPublicLinkAttribute(): string
    {
        return route('agreement.flow.show', ['token' => $this->public_token]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isIndividual(): bool
    {
        return $this->agreement_type === self::TYPE_INDIVIDUAL;
    }

    public function isGroup(): bool
    {
        return $this->agreement_type === self::TYPE_GROUP;
    }

    public function resolveIndividualAmountDue(?int $payingParticipantsCount = null): float
    {
        if (! $this->isIndividual()) {
            return (float) $this->amount_due;
        }

        if ($this->participantPayment && (float) $this->participantPayment->due_amount_pln > 0) {
            return (float) $this->participantPayment->due_amount_pln;
        }

        if ($this->event) {
            $count = max(1, (int) ($payingParticipantsCount ?? $this->event->participant_count ?? 1));
            $resolvedPrice = $this->event->resolvedPricePerPerson($count);
            if ($resolvedPrice > 0) {
                return $resolvedPrice;
            }

            $eventTotalCost = (float) ($this->event->total_cost ?? 0);
            if ($eventTotalCost > 0) {
                return round($eventTotalCost / $count, 2);
            }
        }

        return (float) $this->amount_due;
    }

    public function renderAgreementBody(): string
    {
        return app(AgreementTemplateRenderer::class)
            ->render($this->contractTemplate, $this->buildTemplatePayload());
    }

    public function regenerateAgreementBody(): void
    {
        if (! $this->shouldAutoGenerateAgreementBody()) {
            return;
        }

        $this->agreement_body = $this->renderAgreementBody();
        $this->saveQuietly();
    }

    public function buildTemplatePayload(): array
    {
        $eventName = $this->event_name ?: $this->event?->name;
        $startDate = $this->event_start_date ?: $this->event?->start_date;
        $endDate = $this->event_end_date ?: $this->event?->end_date;
        $flow = (array) data_get($this->meta ?? [], 'flow', []);
        $signerAddress = (array) data_get($flow, 'signer_address', []);
        $travelInsurance = (string) data_get($flow, 'travel_insurance', '');

        $participantName = $this->participant_name
            ?: $this->participantPayment?->participant_name
            ?: ($this->isIndividual() ? ($this->signer_name ?: $this->customer_name) : null);
        $orderingInstitution = (string) ($this->customer_name ?: $this->event?->client_name ?: '—');
        $orderingPerson = (string) ($this->signer_name ?: $this->customer_name ?: '—');
        $orderingEmail = (string) ($this->signer_email ?: $this->customer_email ?: $this->event?->client_email ?: '—');
        $orderingPhone = (string) ($this->signer_phone ?: $this->customer_phone ?: $this->event?->client_phone ?: '—');

        $bookingReference = $this->participantPayment?->booking_reference ?: ($this->agreement_number ?: ('UMOWA-'.$this->id));
        $participantCount = max(1, (int) ($this->participant_count ?: $this->event?->participant_count ?: 1));
        $amountDue = (float) $this->amount_due;
        $formattedAmount = number_format($amountDue, 2, ',', ' ');
        $formattedAmountPerPerson = number_format($participantCount > 0 ? ($amountDue / $participantCount) : $amountDue, 2, ',', ' ');

        $fullAddress = trim(implode(', ', array_filter([
            trim((string) data_get($signerAddress, 'street', '')).' '.trim((string) data_get($signerAddress, 'number', '')),
            trim((string) data_get($signerAddress, 'postal_code', '')).' '.trim((string) data_get($signerAddress, 'city', '')),
            (string) data_get($signerAddress, 'province', ''),
        ], fn ($value) => trim((string) $value) !== '')));

        $travelInsuranceLabel = match ($travelInsurance) {
            'yes' => 'Tak',
            'no' => 'Nie',
            default => 'Nie wybrano',
        };

        $orderingPartyService = app(ContractOrderingPartyService::class);
        $groupPricingService = app(ContractGroupPricingService::class);
        $orderingParties = $orderingPartyService->partiesForTemplatePayload($this);
        $orderingPartiesNames = $orderingPartyService->formattedPartyNames($this);
        $orderingPartiesList = collect($orderingParties)
            ->map(function (array $party): string {
                $details = collect([
                    filled($party['email'] ?? null) ? 'e-mail: '.$party['email'] : null,
                    filled($party['phone'] ?? null) ? 'tel: '.$party['phone'] : null,
                    filled($party['nip'] ?? null) ? 'NIP: '.$party['nip'] : null,
                    ($party['address'] ?? '—') !== '—' ? $party['address'] : null,
                ])->filter()->implode(', ');

                return trim($party['name'].($details !== '' ? ' ('.$details.')' : ''));
            })
            ->implode("\n");

        if ($orderingPartiesNames !== '—') {
            $orderingInstitution = $orderingPartiesNames;
        }

        return [
            'agreement_number' => $this->agreement_number ?: ('UMOWA-'.$this->id),
            'agreement_date' => optional($this->agreement_date)->format('d.m.Y') ?: now()->format('d.m.Y'),
            'agreement_type' => (string) ($this->agreement_type ?: self::TYPE_GROUP),
            'agreement_type_label' => $this->agreement_type_label,
            'event_name' => (string) ($eventName ?: '—'),
            'event_start_date' => $startDate ? $startDate->format('d.m.Y') : '—',
            'event_end_date' => $endDate ? $endDate->format('d.m.Y') : '—',
            'customer_name' => $orderingInstitution,
            'customer_email' => (string) ($this->customer_email ?: $this->event?->client_email ?: '—'),
            'customer_phone' => (string) ($this->customer_phone ?: $this->event?->client_phone ?: '—'),
            'ordering_institution' => $orderingInstitution,
            'ordering_person' => $orderingPerson,
            'ordering_email' => $orderingEmail,
            'ordering_phone' => $orderingPhone,
            'ordering_parties_names' => $orderingPartiesNames,
            'ordering_parties_list' => $orderingPartiesList !== '' ? $orderingPartiesList : $orderingPartiesNames,
            'ordering_party_notes' => (string) ($this->ordering_party_notes ?: '—'),
            'signer_name' => (string) ($this->signer_name ?: $this->customer_name ?: '—'),
            'signer_email' => (string) ($this->signer_email ?: $this->customer_email ?: '—'),
            'signer_phone' => (string) ($this->signer_phone ?: $this->customer_phone ?: '—'),
            'signer_address_street' => (string) (data_get($signerAddress, 'street') ?: '—'),
            'signer_address_number' => (string) (data_get($signerAddress, 'number') ?: '—'),
            'signer_postal_code' => (string) (data_get($signerAddress, 'postal_code') ?: '—'),
            'signer_city' => (string) (data_get($signerAddress, 'city') ?: '—'),
            'signer_province' => (string) (data_get($signerAddress, 'province') ?: '—'),
            'signer_address_full' => $fullAddress !== '' ? $fullAddress : '—',
            'participant_name' => (string) ($participantName ?: '—'),
            'participant_birth_date' => optional($this->participant_birth_date)->format('d.m.Y') ?: '—',
            'participant_email' => (string) ($this->participant_email ?: '—'),
            'participant_phone' => (string) ($this->participant_phone ?: '—'),
            'participant_count' => (string) $participantCount,
            'amount_due' => $formattedAmount,
            'amount_per_person' => $formattedAmountPerPerson,
            'currency' => strtoupper((string) ($this->currency ?: 'PLN')),
            'travel_insurance' => $travelInsurance,
            'travel_insurance_label' => $travelInsuranceLabel,
            'departure_place' => '—',
            'departure_date' => $startDate ? $startDate->format('d.m.Y') : '—',
            'departure_time' => '—',
            'return_place' => '—',
            'return_date' => $endDate ? $endDate->format('d.m.Y') : '—',
            'return_time' => '—',
            'organizer_name' => (string) config('company.name', config('app.name', 'Organizator')),
            'organizer_address_line_1' => (string) config('company.address_line_1', '—'),
            'organizer_address_line_2' => (string) config('company.address_line_2', '—'),
            'organizer_email' => (string) config('company.email', '—'),
            'organizer_phone' => (string) config('company.phone', '—'),
            'booking_reference' => (string) $bookingReference,
            'public_link' => $this->public_link,
            'unit_price' => number_format($groupPricingService->resolvedUnitPrice($this), 2, ',', ' '),
            'payment_scheme_label' => $groupPricingService->paymentSchemeLabel($this),
            'payment_schedule_text' => $groupPricingService->formatPaymentSchedulesText($this),
        ];
    }
}

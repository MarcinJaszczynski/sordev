<?php

namespace App\Models;

use App\Models\Concerns\HasCustomAgreementContent;
use App\Services\AgreementTemplateRenderer;
use App\Services\AgreementTransportPayloadResolver;
use App\Services\ContractAnnexService;
use App\Services\ContractGroupPricingService;
use App\Services\ContractOrderingPartyService;
use App\Services\ContractPaymentSyncService;
use App\Services\Contracts\ContractNumberAllocator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Contract extends Model
{
    use HasCustomAgreementContent;
    use HasFactory;

    public const TYPE_GROUP = 'group';

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_TEMPLATE = 'template';

    public const TYPE_CUSTOM = 'custom';

    public const BODY_EDIT_TEMPLATE = 'template';

    public const BODY_EDIT_MANUAL = 'manual';

    public const BODY_EDIT_UPLOAD = 'upload';

    public const ANNEX_CHANGE_PRICE = 'price';

    public const ANNEX_CHANGE_PARTICIPANTS = 'participants';

    public const ANNEX_CHANGE_DATES = 'dates';

    public const ANNEX_CHANGE_PROGRAM = 'program';

    public const ANNEX_CHANGE_OTHER = 'other';

    public const PAYMENT_SCHEME_LUMP_SUM = 'lump_sum';

    public const PAYMENT_SCHEME_INSTALLMENTS = 'installments';

    public const PAYMENT_SCHEME_INDIVIDUAL = 'individual';

    public const CUSTOM_PAYMENT_TOTAL_LUMP = 'total_lump';

    public const CUSTOM_PAYMENT_TOTAL_INSTALLMENTS = 'total_installments';

    public const CUSTOM_PAYMENT_PER_PARTICIPANT = 'per_participant';

    public const CUSTOM_PAYMENT_MANUAL = 'manual';

    public static array $customPaymentModes = [
        self::CUSTOM_PAYMENT_TOTAL_LUMP => 'Kwota całkowita (jednorazowo)',
        self::CUSTOM_PAYMENT_TOTAL_INSTALLMENTS => 'Kwota całkowita (w transzach)',
        self::CUSTOM_PAYMENT_PER_PARTICIPANT => 'Wpłaty per uczestnik',
        self::CUSTOM_PAYMENT_MANUAL => 'Ręczne powiązanie wpłaty',
    ];

    public const OP_NOWEDANE = 'NOWEDANE';

    public const OP_KOREKTA = 'KOREKTA';

    public const OP_ROZWIAZANIE = 'ROZWIAZANIE';

    public const OP_USUNIECIE = 'USUNIECIE';

    protected $fillable = [
        'event_id',
        'contract_template_id',
        'payment_schedule_template_id',
        'legacy_event_agreement_id',
        'contract_type',
        'agreement_type',
        'participant_payment_id',
        'title',
        'contract_number',
        'operational_number',
        'agreement_number',
        'reservation_number',
        'contract_date',
        'agreement_date',
        'subject_code',
        'payment_method_code',
        'event_name',
        'event_start_date',
        'event_end_date',
        'customer_name',
        'customer_email',
        'customer_phone',
        'ordering_party_notes',
        'participant_name',
        'gender',
        'identification_type',
        'requires_diet',
        'diet_type',
        'diet_daily_pln',
        'participant_birth_date',
        'participant_email',
        'participant_phone',
        'participant_count',
        'unit_price',
        'payment_scheme',
        'total_price',
        'amount_due',
        'amount_paid',
        'currency',
        'status',
        'payment_status',
        'client_payment_method',
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
        'annex_change_types',
        'annex_program_change_notes',
        'annex_program_snapshot',
        'tfg_status',
        'pending_operation',
        'correction_reason',
        'tfg_change_date',
        'tfg_termination_date',
        'last_feed_log_id',
        'tfg_synced_at',
        'tfg_update_deadline_at',
        'created_by',
    ];

    protected $casts = [
        'requires_diet' => 'boolean',
        'diet_daily_pln' => 'decimal:2',
        'contract_date' => 'date',
        'event_start_date' => 'date',
        'event_end_date' => 'date',
        'participant_birth_date' => 'date',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'signed_at' => 'datetime',
        'paid_at' => 'datetime',
        'public_token_expires_at' => 'datetime',
        'tfg_synced_at' => 'datetime',
        'tfg_update_deadline_at' => 'datetime',
        'tfg_change_date' => 'date',
        'tfg_termination_date' => 'date',
        'attachments' => 'array',
        'meta' => 'array',
        'annex_change_types' => 'array',
        'annex_program_snapshot' => 'array',
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
        'partial' => 'Częściowo opłacona',
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
        self::TYPE_TEMPLATE => 'Szablon',
        self::TYPE_CUSTOM => 'Umowa własna',
    ];

    public static array $bodyEditModes = [
        self::BODY_EDIT_TEMPLATE => 'Z szablonu',
        self::BODY_EDIT_MANUAL => 'Ręczna edycja',
        self::BODY_EDIT_UPLOAD => 'Wgrany dokument PDF',
    ];

    public static array $annexChangeTypes = [
        self::ANNEX_CHANGE_PRICE => 'Zmiana ceny',
        self::ANNEX_CHANGE_PARTICIPANTS => 'Zmiana liczby uczestników',
        self::ANNEX_CHANGE_DATES => 'Zmiana terminu',
        self::ANNEX_CHANGE_PROGRAM => 'Zmiana programu imprezy',
        self::ANNEX_CHANGE_OTHER => 'Inne postanowienia',
    ];

    public static array $paymentSchemes = [
        self::PAYMENT_SCHEME_LUMP_SUM => 'Jednorazowa',
        self::PAYMENT_SCHEME_INSTALLMENTS => 'W transzach',
        self::PAYMENT_SCHEME_INDIVIDUAL => 'Płatności indywidualne',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            $contract->public_token ??= Str::random(48);
            $contract->contract_date ??= now()->toDateString();
            $contract->status ??= 'draft';
            $contract->payment_status ??= 'pending';
            $contract->contract_type ??= self::TYPE_GROUP;
            $contract->currency = strtoupper((string) ($contract->currency ?: 'PLN'));

            if (blank($contract->title)) {
                $contract->title = 'Umowa imprezy';
            }

            if (blank($contract->event_name) && $contract->event) {
                $contract->event_name = $contract->event->name;
            }

            if ($contract->contract_type === self::TYPE_INDIVIDUAL && blank($contract->participant_count)) {
                $contract->participant_count = 1;
            }

            $contract->body_edit_mode ??= $contract->contract_type === self::TYPE_CUSTOM
                ? self::BODY_EDIT_UPLOAD
                : self::BODY_EDIT_TEMPLATE;
        });

        static::created(function (self $contract): void {
            $updates = [];

            if (blank($contract->contract_number)) {
                $updates['contract_number'] = sprintf('UM/%s/%05d', now()->format('Y'), $contract->id);
            }

            if (blank($contract->operational_number)) {
                $updates['operational_number'] = app(ContractNumberAllocator::class)->allocate(
                    $contract->fresh(['event'])
                );
            }

            if (blank($contract->agreement_body) && $contract->shouldAutoGenerateAgreementBody()) {
                $contract->fill($updates);
                $updates['agreement_body'] = $contract->renderAgreementBody();
            }

            if (! empty($updates)) {
                $contract->forceFill($updates)->saveQuietly();
            }
        });

        static::saved(function (self $contract): void {
            $syncRelevantFields = [
                'contract_number',
                'operational_number',
                'contract_type',
                'payment_scheme',
                'participant_payment_id',
                'participant_name',
                'customer_name',
                'signer_name',
                'total_price',
                'amount_paid',
                'status',
                'payment_status',
                'client_payment_method',
                'paid_at',
                'meta',
            ];

            if (! $contract->wasRecentlyCreated && ! $contract->wasChanged($syncRelevantFields)) {
                return;
            }

            app(ContractPaymentSyncService::class)->sync($contract->fresh());
        });

        static::deleted(function (self $contract): void {
            app(ContractPaymentSyncService::class)->remove($contract);
        });

        static::updated(function (self $contract): void {
            if ($contract->tfg_status === 'Zawarta'
                && $contract->wasChanged(['total_price', 'subject_code', 'payment_method_code', 'contract_date'])
                && ! $contract->wasChanged('tfg_update_deadline_at')) {
                $contract->updateQuietly(['tfg_update_deadline_at' => now()->addDays(14)]);
            }
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

    public function paymentScheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(PaymentScheduleTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }

    public function participantPayments(): BelongsToMany
    {
        return $this->belongsToMany(
            EventSettlementParticipantPayment::class,
            'contract_participant_payments',
            'contract_id',
            'participant_payment_id',
        )->withPivot('is_primary')->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function linkedParticipantPaymentIds(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('contract_participant_payments')) {
            $fromPivot = $this->participantPayments()
                ->pluck('event_settlement_participant_payments.id')
                ->all();
            if ($fromPivot !== []) {
                return array_map('intval', $fromPivot);
            }
        }

        $ids = collect($this->meta['linked_participant_payment_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        if ($this->participant_payment_id) {
            return [(int) $this->participant_payment_id];
        }

        return [];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ContractVariant::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractPayment::class)->orderBy('sort_order');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(ContractRefund::class)->orderBy('sort_order');
    }

    public function orderingParties(): HasMany
    {
        return $this->hasMany(ContractOrderingParty::class)->orderBy('sort_order');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(ContractPaymentSchedule::class)->orderBy('sort_order');
    }

    public function lastFeedLog(): BelongsTo
    {
        return $this->belongsTo(TfgFeedLog::class, 'last_feed_log_id');
    }

    public function feedLogs(): BelongsToMany
    {
        return $this->belongsToMany(TfgFeedLog::class, 'tfg_feed_log_contract')
            ->withPivot(['operation', 'correction_reason'])
            ->withTimestamps();
    }

    public function setAttribute($key, $value)
    {
        $aliases = [
            'agreement_number' => 'operational_number',
            'agreement_date' => 'contract_date',
            'agreement_type' => 'contract_type',
            'amount_due' => 'total_price',
            'payment_method' => 'client_payment_method',
        ];

        if (isset($aliases[$key])) {
            $key = $aliases[$key];
        }

        return parent::setAttribute($key, $value);
    }

    public function getAgreementNumberAttribute(): ?string
    {
        return $this->operational_number ?: $this->contract_number;
    }

    public function setAgreementNumberAttribute(?string $value): void
    {
        $this->attributes['operational_number'] = $value;
    }

    public function getDisplayNumberAttribute(): ?string
    {
        return $this->operational_number ?: $this->contract_number;
    }

    public function usesIndividualParticipantPayments(): bool
    {
        return $this->isGroup()
            && $this->payment_scheme === self::PAYMENT_SCHEME_INDIVIDUAL;
    }

    public function getAgreementDateAttribute()
    {
        return $this->contract_date;
    }

    public function setAgreementDateAttribute($value): void
    {
        $this->attributes['contract_date'] = $value;
    }

    public function getAgreementTypeAttribute(): ?string
    {
        return $this->contract_type;
    }

    public function setAgreementTypeAttribute(?string $value): void
    {
        $this->attributes['contract_type'] = $value;
    }

    public function getAmountDueAttribute(): float
    {
        return (float) $this->total_price;
    }

    public function setAmountDueAttribute($value): void
    {
        $this->attributes['total_price'] = $value;
    }

    public function getPaymentMethodAttribute(): ?string
    {
        return $this->client_payment_method;
    }

    public function setPaymentMethodAttribute(?string $value): void
    {
        $this->attributes['client_payment_method'] = $value;
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
        return self::$types[$this->contract_type] ?? $this->contract_type;
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
        return $this->contract_type === self::TYPE_INDIVIDUAL;
    }

    public function isGroup(): bool
    {
        return $this->contract_type === self::TYPE_GROUP;
    }

    public function isAnnex(): bool
    {
        return (bool) data_get($this->meta, 'is_annex', false);
    }

    public function hasAnnexProgramChange(): bool
    {
        return in_array(self::ANNEX_CHANGE_PROGRAM, Arr::wrap($this->annex_change_types ?? []), true);
    }

    public function getParentContractAttribute(): ?self
    {
        $parentId = data_get($this->meta, 'parent_contract_id');

        return $parentId ? self::query()->find($parentId) : null;
    }

    public function isTfgSynced(): bool
    {
        return $this->tfg_status === 'Zawarta';
    }

    public function canSubmitNewData(): bool
    {
        return ! $this->isTfgSynced() || blank($this->tfg_status);
    }

    public function canCorrect(): bool
    {
        return $this->isTfgSynced() && blank($this->pending_operation);
    }

    public function canTerminateOrDelete(): bool
    {
        return $this->canCorrect();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function queueTfgOperation(string $operation, ?string $correctionReason = null, array $extra = []): void
    {
        $this->forceFill(array_merge([
            'pending_operation' => $operation,
            'correction_reason' => $correctionReason,
        ], $extra))->save();
    }

    public function resolveIndividualAmountDue(?int $payingParticipantsCount = null): float
    {
        if (! $this->isIndividual()) {
            return (float) $this->total_price;
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

        return (float) $this->total_price;
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

        $awaitingParticipant = $this->isIndividual()
            && $this->status === 'template'
            && blank($this->signer_name)
            && blank($this->customer_name)
            && (bool) data_get($this->meta ?? [], 'awaiting_participant_details', true);

        $pendingLabel = '[dane uzupełni uczestnik]';

        if ($awaitingParticipant) {
            $orderingInstitution = $pendingLabel;
            $orderingPerson = $pendingLabel;
            $orderingEmail = $pendingLabel;
            $orderingPhone = $pendingLabel;
        } else {
            $orderingInstitution = (string) ($this->customer_name ?: ($this->isIndividual() ? '—' : ($this->event?->client_name ?: '—')));
            $orderingPerson = (string) ($this->signer_name ?: $this->customer_name ?: '—');
            $orderingEmail = (string) ($this->signer_email ?: $this->customer_email ?: ($this->isIndividual() ? '—' : ($this->event?->client_email ?: '—')));
            $orderingPhone = (string) ($this->signer_phone ?: $this->customer_phone ?: ($this->isIndividual() ? '—' : ($this->event?->client_phone ?: '—')));
        }

        $bookingReference = $this->participantPayment?->booking_reference ?: ($this->contract_number ?: ('UMOWA-'.$this->id));
        $participantCount = max(1, (int) ($this->participant_count ?: $this->event?->participant_count ?: 1));
        $amountDue = (float) $this->total_price;
        $formattedAmount = number_format($amountDue, 2, ',', ' ');
        $formattedAmountPerPerson = number_format($participantCount > 0 ? ($amountDue / $participantCount) : $amountDue, 2, ',', ' ');

        $foreignSuffix = '';
        $foreignMeta = data_get($this->meta ?? [], 'foreign_prices_per_person');
        if ((bool) data_get($this->meta ?? [], 'include_foreign', false) && is_array($foreignMeta) && $foreignMeta !== []) {
            $parts = [];
            foreach ($foreignMeta as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper((string) ($row['currency'] ?? ''));
                $fx = round((float) ($row['price_per_person'] ?? 0) * $participantCount, 2);
                if ($code !== '' && $fx > 0) {
                    $parts[] = number_format($fx, 2, ',', ' ').' '.$code;
                }
            }
            if ($parts !== []) {
                $foreignSuffix = ' + '.implode(' + ', $parts);
                $place = data_get($this->meta, 'foreign_paid_by') === 'office' ? 'biuro' : 'pilot/autokar';
                $foreignSuffix .= ' ('.$place.')';
            }
        }
        $formattedAmountWithFx = $formattedAmount.' PLN'.$foreignSuffix;
        $unitWithFx = $formattedAmountPerPerson.' PLN';
        if ($foreignSuffix !== '' && $participantCount > 0) {
            $unitParts = [];
            foreach ((array) $foreignMeta as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper((string) ($row['currency'] ?? ''));
                $fx = round((float) ($row['price_per_person'] ?? 0), 2);
                if ($code !== '' && $fx > 0) {
                    $unitParts[] = number_format($fx, 2, ',', ' ').' '.$code;
                }
            }
            if ($unitParts !== []) {
                $unitWithFx .= ' + '.implode(' + ', $unitParts);
            }
        }
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

        if (! $awaitingParticipant && $orderingPartiesNames !== '—') {
            $orderingInstitution = $orderingPartiesNames;
        }

        $annexService = app(ContractAnnexService::class);
        $groupPricingService = app(ContractGroupPricingService::class);
        $changeTypes = Arr::wrap($this->annex_change_types ?? []);
        $parentNumber = $this->parentContract?->contract_number;
        $transport = app(AgreementTransportPayloadResolver::class)->forEvent(
            $this->event,
            $startDate,
            $endDate,
        );

        return [
            'agreement_number' => $this->contract_number ?: ('UMOWA-'.$this->id),
            'agreement_date' => optional($this->contract_date)->format('d.m.Y') ?: now()->format('d.m.Y'),
            'agreement_type' => (string) ($this->contract_type ?: self::TYPE_GROUP),
            'agreement_type_label' => $this->agreement_type_label,
            'event_name' => (string) ($eventName ?: '—'),
            'event_start_date' => $startDate ? $startDate->format('d.m.Y') : '—',
            'event_end_date' => $endDate ? $endDate->format('d.m.Y') : '—',
            'customer_name' => $awaitingParticipant ? $pendingLabel : $orderingInstitution,
            'customer_email' => $awaitingParticipant ? $pendingLabel : (string) ($this->customer_email ?: ($this->isIndividual() ? '—' : ($this->event?->client_email ?: '—'))),
            'customer_phone' => $awaitingParticipant ? $pendingLabel : (string) ($this->customer_phone ?: ($this->isIndividual() ? '—' : ($this->event?->client_phone ?: '—'))),
            'ordering_institution' => $orderingInstitution,
            'ordering_person' => $orderingPerson,
            'ordering_email' => $orderingEmail,
            'ordering_phone' => $orderingPhone,
            'ordering_parties_names' => $awaitingParticipant ? $pendingLabel : $orderingPartiesNames,
            'ordering_parties_list' => $awaitingParticipant
                ? $pendingLabel
                : ($orderingPartiesList !== '' ? $orderingPartiesList : $orderingPartiesNames),
            'ordering_party_notes' => (string) ($this->ordering_party_notes ?: '—'),
            'signer_name' => $awaitingParticipant ? $pendingLabel : (string) ($this->signer_name ?: $this->customer_name ?: '—'),
            'signer_email' => $awaitingParticipant ? $pendingLabel : (string) ($this->signer_email ?: $this->customer_email ?: '—'),
            'signer_phone' => $awaitingParticipant ? $pendingLabel : (string) ($this->signer_phone ?: $this->customer_phone ?: '—'),
            'signer_address_street' => $awaitingParticipant ? $pendingLabel : (string) (data_get($signerAddress, 'street') ?: '—'),
            'signer_address_number' => $awaitingParticipant ? $pendingLabel : (string) (data_get($signerAddress, 'number') ?: '—'),
            'signer_postal_code' => $awaitingParticipant ? $pendingLabel : (string) (data_get($signerAddress, 'postal_code') ?: '—'),
            'signer_city' => $awaitingParticipant ? $pendingLabel : (string) (data_get($signerAddress, 'city') ?: '—'),
            'signer_province' => $awaitingParticipant ? $pendingLabel : (string) (data_get($signerAddress, 'province') ?: '—'),
            'signer_address_full' => $awaitingParticipant ? $pendingLabel : ($fullAddress !== '' ? $fullAddress : '—'),
            'participant_name' => $awaitingParticipant ? $pendingLabel : (string) ($participantName ?: '—'),
            'participant_birth_date' => optional($this->participant_birth_date)->format('d.m.Y') ?: ($awaitingParticipant ? $pendingLabel : '—'),
            'participant_email' => $awaitingParticipant ? $pendingLabel : (string) ($this->participant_email ?: '—'),
            'participant_phone' => $awaitingParticipant ? $pendingLabel : (string) ($this->participant_phone ?: '—'),
            'participant_count' => (string) $participantCount,
            'amount_due' => $formattedAmountWithFx,
            'amount_per_person' => $unitWithFx,
            'currency' => strtoupper((string) ($this->currency ?: 'PLN')),            'travel_insurance' => $travelInsurance,
            'travel_insurance_label' => $travelInsuranceLabel,
            'departure_place' => $transport['departure_place'],
            'departure_date' => $transport['departure_date'],
            'departure_time' => $transport['departure_time'],
            'return_place' => $transport['return_place'],
            'return_date' => $transport['return_date'],
            'return_time' => $transport['return_time'],
            'organizer_name' => (string) config('company.name', config('app.name', 'Organizator')),
            'organizer_address_line_1' => (string) config('company.address_line_1', '—'),
            'organizer_address_line_2' => (string) config('company.address_line_2', '—'),
            'organizer_email' => (string) config('company.email', '—'),
            'organizer_phone' => (string) config('company.phone', '—'),
            'booking_reference' => (string) $bookingReference,
            'public_link' => $this->public_link,
            'is_annex' => $this->isAnnex() ? 'tak' : 'nie',
            'parent_agreement_number' => (string) ($parentNumber ?: '—'),
            'annex_change_types' => $annexService->formatChangeTypesLabels($changeTypes),
            'annex_program_change_notes' => (string) ($this->annex_program_change_notes ?: '—'),
            'annex_program_text' => $annexService->formatProgramSnapshotText($this->annex_program_snapshot),
            'annex_program_html' => $annexService->formatProgramSnapshotHtml($this->annex_program_snapshot),
            'unit_price' => number_format($groupPricingService->resolvedUnitPrice($this), 2, ',', ' '),
            'payment_scheme_label' => $groupPricingService->paymentSchemeLabel($this),
            'payment_schedule_text' => $groupPricingService->formatPaymentSchedulesText($this),
            'custom_placeholder_values' => (array) data_get($this->meta ?? [], 'custom_placeholder_values', []),
        ];
    }
}

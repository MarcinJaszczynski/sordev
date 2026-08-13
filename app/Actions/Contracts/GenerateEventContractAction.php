<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Actions\Finance\ApplyEventPaymentScheduleTemplateAction;
use App\Actions\Finance\ApplyPaymentScheduleTemplateAction;
use App\Models\Contract;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Services\ContractDietSurchargeService;
use App\Services\ContractExtrasSurchargeService;
use App\Services\ContractGroupPricingService;
use App\Services\ContractOrderingPartyService;
use App\Services\ContractPaymentScheduleService;
use App\Services\ContractTfgSetupService;
use App\Services\IndividualPaymentSchedulePolicy;
use App\Support\ContractGenerationPriceHints;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Kanoniczne generowanie umowy z imprezy (indywidualna / grupowa).
 *
 * @phpstan-type Result array{
 *     primary: Contract,
 *     companion: ?Contract,
 *     mode: 'individual_template'|'group_ordering'|'group_participants'
 * }
 */
final class GenerateEventContractAction
{
    public const MODE_INDIVIDUAL = 'individual';

    public const MODE_GROUP_ORDERING = 'group_ordering';

    public const MODE_GROUP_PARTICIPANTS = 'group_participants';

    public function __construct(
        private ContractTfgSetupService $tfg,
        private ContractOrderingPartyService $orderingParties,
        private ContractPaymentScheduleService $schedules,
        private ContractGroupPricingService $groupPricing,
        private ApplyEventPaymentScheduleTemplateAction $applyEventScheduleTemplate,
        private ApplyPaymentScheduleTemplateAction $applyGlobalScheduleTemplate,
        private IndividualPaymentSchedulePolicy $individualPaymentPolicy,
        private ContractDietSurchargeService $dietSurcharge,
        private ContractExtrasSurchargeService $extrasSurcharge,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return Result
     */
    public function __invoke(Event $event, array $data, ?int $createdBy = null): array
    {
        $mode = (string) ($data['generation_mode'] ?? self::MODE_GROUP_ORDERING);

        return match ($mode) {
            self::MODE_INDIVIDUAL, self::MODE_GROUP_PARTICIPANTS => [
                'primary' => $this->createIndividualTemplate($event, $data, $createdBy, $mode),
                'companion' => null,
                'mode' => $mode === self::MODE_GROUP_PARTICIPANTS
                    ? 'group_participants'
                    : 'individual_template',
            ],
            self::MODE_GROUP_ORDERING => $this->createGroupOrdering($event, $data, $createdBy),
            default => throw new InvalidArgumentException('Nieznany tryb generowania umowy.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Result
     */
    private function createGroupOrdering(Event $event, array $data, ?int $createdBy): array
    {
        $participantsFillSeparately = (bool) ($data['participants_fill_separately'] ?? false);
        $useEventSchedule = (bool) ($data['use_event_payment_template'] ?? false);

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $meta['participants_fill_separately'] = $participantsFillSeparately;
        $meta['generation_mode'] = self::MODE_GROUP_ORDERING;

        $priced = $this->groupPricing->applyGroupPricingToFormData(array_merge($data, [
            'agreement_type' => Contract::TYPE_GROUP,
        ]), $event);

        $amountDue = (float) ($priced['amount_due'] ?? $data['amount_due'] ?? 0);
        [$meta, $dietAttrs] = $this->applyDietCatalogToMeta($meta, $data, $amountDue);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_template_id' => $data['contract_template_id'] ?? null,
            'agreement_type' => Contract::TYPE_GROUP,
            'title' => $data['title'] ?? 'Umowa imprezy',
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => $event->client_name,
            'customer_email' => $event->client_email,
            'customer_phone' => $event->client_phone,
            'participant_count' => (int) ($priced['participant_count'] ?? $event->participant_count ?? 1),
            'unit_price' => $priced['unit_price'] ?? null,
            'amount_due' => $amountDue,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'payment_scheme' => $priced['payment_scheme'] ?? Contract::PAYMENT_SCHEME_LUMP_SUM,
            'attachments' => $data['attachments'] ?? [],
            'meta' => $meta,
            'created_by' => $createdBy,
            'subject_code' => $data['subject_code'] ?? null,
            'payment_method_code' => $data['payment_method_code'] ?? null,
            'reservation_number' => $data['reservation_number'] ?? null,
            ...$dietAttrs,
        ]);

        $this->tfg->applyToContract($contract, array_merge(
            $this->tfg->defaultsFromEvent($event),
            $priced,
            $data,
        ));

        $this->orderingParties->syncForContract(
            $contract,
            $this->orderingParties->partiesFromEvent($event),
        );

        if ($this->applySchedulesFromWizard($contract, $event, $data, $priced, $useEventSchedule)) {
            // harmonogram z szablonu globalnego / imprezy / formularza
        }

        $contract->regenerateAgreementBody();

        $companion = null;
        if ($participantsFillSeparately) {
            $companion = $this->createIndividualTemplate(
                $event,
                array_merge($data, [
                    'title' => ($data['participant_form_title'] ?? 'Ankieta uczestnika').' (dane)',
                    'paying_participants_count' => (int) ($priced['participant_count'] ?? $event->participant_count ?? 1),
                    'meta' => array_merge($meta, [
                        'data_collection_for_group' => true,
                        'linked_group_contract_id' => $contract->id,
                        'skip_payment' => true,
                    ]),
                ]),
                $createdBy,
                self::MODE_GROUP_ORDERING,
                allowExisting: true,
            );

            $groupMeta = is_array($contract->meta) ? $contract->meta : [];
            $groupMeta['linked_participant_template_id'] = $companion->id;
            $contract->update(['meta' => $groupMeta]);
        }

        return [
            'primary' => $contract->fresh(),
            'companion' => $companion,
            'mode' => 'group_ordering',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createIndividualTemplate(
        Event $event,
        array $data,
        ?int $createdBy,
        string $sourceMode,
        bool $allowExisting = false,
    ): Contract {
        $paying = max(1, (int) ($data['paying_participants_count']
            ?? $data['participant_count']
            ?? $event->participant_count
            ?? 1));

        $slots = max(1, (int) ($data['participants_on_contract'] ?? 1));
        $unitPrice = round((float) ($data['unit_price'] ?? 0), 2);
        if ($unitPrice <= 0) {
            $unitPrice = round((float) $event->resolvedPricePerPerson(max(1, (int) ($event->participant_count ?? 1))), 2);
        }

        // Kanonicznie: PLN na umowę = cena/os. × osoby na umowę (nie suma grupy).
        $canonicalAmount = round($unitPrice * $slots, 2);
        $overrideAmount = (bool) ($data['override_amount_due'] ?? false);
        $amountDue = $overrideAmount
            ? round((float) ($data['amount_due'] ?? 0), 2)
            : $canonicalAmount;
        if ($amountDue <= 0) {
            $amountDue = $canonicalAmount;
        }

        if ($amountDue <= 0 && ! ($data['meta']['skip_payment'] ?? false)) {
            throw new InvalidArgumentException(
                'Aby policzyć cenę za osobę, ustaw koszt imprezy większy od 0.'
            );
        }

        if (! $allowExisting) {
            // Kolumna w DB to contract_type (agreement_type to tylko alias Eloquent).
            $existingTemplate = (int) $event->agreements()
                ->where('contract_type', Contract::TYPE_INDIVIDUAL)
                ->where('status', 'template')
                ->count();

            if ($existingTemplate > 0) {
                throw new InvalidArgumentException(
                    'Szablon umowy indywidualnej już istnieje. Wysyłaj jego link do uczestników.'
                );
            }
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $meta['is_individual_template'] = true;
        $meta['expected_participants'] = $paying;
        $meta['generation_mode'] = $sourceMode;
        $meta['participants_on_contract'] = $slots;
        $meta['unit_price_pln'] = $unitPrice;
        $meta['awaiting_participant_details'] = true;

        $hints = ContractGenerationPriceHints::forEvent($event);
        $includeForeign = (bool) ($data['include_foreign_currency'] ?? false);
        $foreignPaidBy = in_array(($data['foreign_paid_by'] ?? ''), ['office', 'pilot'], true)
            ? (string) $data['foreign_paid_by']
            : 'pilot';
        $meta['include_foreign'] = $includeForeign;
        $meta['foreign_excluded'] = ! $includeForeign;
        $meta['foreign_paid_by'] = $includeForeign ? $foreignPaidBy : null;
        if ($includeForeign && $hints['foreign'] !== []) {
            $meta['foreign_prices_per_person'] = $hints['foreign'];
            $meta['price_label'] = $hints['price_label'];
        } else {
            unset($meta['foreign_prices_per_person']);
            $meta['price_label'] = MoneyFormatter::format($unitPrice, 'PLN');
        }

        $title = (string) ($data['title'] ?? 'Umowa uczestnika');
        if (! str_contains(mb_strtolower($title), 'szablon') && ! ($meta['data_collection_for_group'] ?? false)) {
            $title .= ' (szablon)';
        }

        $paymentScheme = (string) ($data['payment_scheme'] ?? Contract::PAYMENT_SCHEME_LUMP_SUM);
        $schedules = $this->groupPricing->extractSchedulesFromFormData($data);
        if ($schedules !== []) {
            $paymentScheme = Contract::PAYMENT_SCHEME_INSTALLMENTS;
        } elseif ((bool) ($data['use_event_payment_template'] ?? false)) {
            $schedules = ContractGenerationPriceHints::scaleSchedules(
                ContractGenerationPriceHints::defaultSchedulesPerPerson(
                    $event,
                    $unitPrice > 0 ? $unitPrice : $hints['pln_per_person'],
                    $includeForeign ? $hints['foreign'] : [],
                    $includeForeign,
                    $foreignPaidBy,
                ),
                $slots,
            );
            if ($schedules !== []) {
                $paymentScheme = Contract::PAYMENT_SCHEME_INSTALLMENTS;
            }
        }

        $schedules = ContractGenerationPriceHints::applyForeignCurrencyToSchedules(
            $schedules,
            $includeForeign,
            $foreignPaidBy,
            $includeForeign ? $hints['foreign'] : [],
            $slots,
            $event,
        );

        if (! ($meta['skip_payment'] ?? false)) {
            $enforced = $this->individualPaymentPolicy->enforceForIndividual(
                $event,
                $amountDue,
                $paymentScheme,
                $schedules,
                includeForeign: $includeForeign,
            );
            $paymentScheme = (string) $enforced['payment_scheme'];
            $schedules = $enforced['payment_schedules'];
            if ($enforced['policy_message']) {
                $meta['payment_policy_note'] = $enforced['policy_message'];
            }
        }

        [$meta, $dietAttrs] = $this->applyDietCatalogToMeta($meta, $data, $amountDue);

        // Zamawiający/płatnik = uczestnik z formularza publicznego — nie kopiuj szkoły z imprezy.
        $agreement = Contract::create([
            'event_id' => $event->id,
            'contract_template_id' => $data['contract_template_id'] ?? null,
            'agreement_type' => Contract::TYPE_INDIVIDUAL,
            'title' => $title,
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'signer_name' => null,
            'signer_email' => null,
            'signer_phone' => null,
            'participant_name' => null,
            'participant_count' => $slots,
            'unit_price' => $unitPrice,
            'amount_due' => $amountDue,
            'currency' => 'PLN',
            'status' => 'template',
            'payment_status' => 'pending',
            'payment_scheme' => $paymentScheme,
            'attachments' => $data['attachments'] ?? [],
            'created_by' => $createdBy,
            'meta' => $meta,
            'subject_code' => $data['subject_code'] ?? null,
            'payment_method_code' => $data['payment_method_code'] ?? null,
            'reservation_number' => $data['reservation_number'] ?? null,
            ...$dietAttrs,
        ]);

        $this->tfg->applyToContract($agreement, array_merge(
            $this->tfg->defaultsFromEvent($event),
            $data,
            ['tfg_travelers_count' => $slots],
        ));

        // Bez stron zamawiających z imprezy — uzupełni płatnik w formularzu.
        $this->orderingParties->syncForContract($agreement, []);

        if ($schedules !== []) {
            $this->schedules->syncForContract($agreement->fresh(), $schedules, $paymentScheme);
        } elseif (filled($data['payment_schedule_template_id'] ?? null) && Schema::hasTable('payment_schedule_templates')) {
            $template = PaymentScheduleTemplate::query()->find((int) $data['payment_schedule_template_id']);
            if ($template) {
                ($this->applyGlobalScheduleTemplate)($template, $agreement->fresh(), $event);
            }
        } elseif ((bool) ($data['use_event_payment_template'] ?? false)) {
            try {
                ($this->applyEventScheduleTemplate)($event, $agreement->fresh());
            } catch (InvalidArgumentException) {
                // brak szablonu na imprezie — zostaw bez rat
            }
        }

        $agreement->regenerateAgreementBody();

        return $agreement->fresh();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function applyDietCatalogToMeta(array $meta, array $data, float $baseAmountDue): array
    {
        $catalog = $this->extrasSurcharge->catalogFromWizardData($data, $baseAmountDue);
        $meta = $this->extrasSurcharge->mergeCatalogIntoMeta($meta, $catalog);

        $attrs = [];
        if (Schema::hasColumn('contracts', 'requires_diet')) {
            $attrs['requires_diet'] = $catalog['requires_diet'];
        }
        if (Schema::hasColumn('contracts', 'diet_daily_pln')) {
            $attrs['diet_daily_pln'] = $catalog['diet_daily_pln'];
        }
        if (Schema::hasColumn('contracts', 'diet_type') && ($catalog['diet_options'][0] ?? null)) {
            $attrs['diet_type'] = $catalog['diet_options'][0];
        }

        return [$meta, $attrs];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $priced
     */
    private function applySchedulesFromWizard(
        Contract $contract,
        Event $event,
        array $data,
        array $priced,
        bool $useEventSchedule,
    ): bool {
        $templateId = (int) ($data['payment_schedule_template_id'] ?? 0);
        if ($templateId > 0 && Schema::hasTable('payment_schedule_templates')) {
            $template = PaymentScheduleTemplate::query()->find($templateId);
            if ($template) {
                ($this->applyGlobalScheduleTemplate)($template, $contract->fresh(), $event);

                return true;
            }
        }

        if ($useEventSchedule && Schema::hasTable('event_payment_installment_templates')) {
            try {
                ($this->applyEventScheduleTemplate)($event, $contract->fresh());

                return true;
            } catch (InvalidArgumentException) {
                // fallback na formularz
            }
        }

        $this->schedules->syncForContract(
            $contract->fresh(),
            $this->groupPricing->extractSchedulesFromFormData($priced),
            $priced['payment_scheme'] ?? Contract::PAYMENT_SCHEME_LUMP_SUM,
        );

        return true;
    }
}

<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Models\EventSettlement;
use App\Services\ContractPaymentScheduleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PublicAgreementResolver
{
    public function usesContractsTable(): bool
    {
        return Schema::hasTable('contracts');
    }

    public function findByToken(string $token): Contract|EventAgreement
    {
        $agreement = null;

        if ($this->usesContractsTable()) {
            $agreement = $this->contractQuery()
                ->where('public_token', $token)
                ->first();
        }

        if (! $agreement) {
            $agreement = $this->legacyAgreementQuery()
                ->where('public_token', $token)
                ->first();
        }

        if (! $agreement) {
            abort(404);
        }

        if ($agreement->public_token_expires_at && $agreement->public_token_expires_at->isPast()) {
            abort(410, 'Link do umowy wygasł.');
        }

        return $agreement;
    }

    public function createFromTemplate(Contract|EventAgreement $template): Contract|EventAgreement
    {
        $model = $template instanceof Contract || $this->usesContractsTable()
            ? Contract::class
            : EventAgreement::class;

        $payload = [
            'event_id' => $template->event_id,
            'contract_template_id' => $template->contract_template_id,
            'agreement_type' => $template->agreement_type,
            'title' => $template->title,
            'agreement_date' => now()->toDateString(),
            'event_name' => $template->event_name,
            'event_start_date' => $template->event_start_date,
            'event_end_date' => $template->event_end_date,
            'customer_name' => $template->customer_name,
            'customer_email' => $template->customer_email,
            'customer_phone' => $template->customer_phone,
            'participant_count' => $template->participant_count,
            'amount_due' => $template->amount_due,
            'currency' => $template->currency,
            'status' => 'draft',
            'payment_status' => 'pending',
            'attachments' => $template->attachments,
            'admin_notes' => $template->admin_notes,
            'meta' => array_merge($template->meta ?? [], ['parent_template_id' => $template->id]),
        ];

        if ($model === Contract::class) {
            $payload['unit_price'] = $template->unit_price ?? null;
            $payload['payment_scheme'] = $template->payment_scheme ?? null;
            $payload['subject_code'] = $template->subject_code ?? null;
            $payload['payment_method_code'] = $template->payment_method_code ?? null;
            $payload['reservation_number'] = $template->reservation_number ?? null;
        }

        $agreement = $model::create($payload);

        if ($agreement instanceof Contract && $template instanceof Contract) {
            $tfg = app(ContractTfgSetupService::class);
            $tfg->cloneTfgStructureFromContract($template, $agreement);
            $eventDefaults = $template->event
                ? $tfg->defaultsFromEvent($template->event)
                : [];
            $tfg->applyToContract($agreement, array_merge(
                $eventDefaults,
                $tfg->defaultsFromContract($template),
                [
                    'tfg_travelers_count' => max(1, (int) ($agreement->participant_count ?? 1)),
                ],
            ));
        }

        if ($agreement instanceof Contract && Schema::hasTable('contract_payment_schedules')) {
            $template->loadMissing('paymentSchedules');
            $rows = $template->paymentSchedules
                ->map(fn ($schedule): array => [
                    'label' => $schedule->label,
                    'amount' => (float) $schedule->amount,
                    'amount_foreign' => isset($schedule->amount_foreign) ? (float) $schedule->amount_foreign : null,
                    'currency_code' => $schedule->currency_code,
                    'paid_by' => $schedule->paid_by,
                    'due_date' => optional($schedule->due_date)?->toDateString(),
                    'due_from' => optional($schedule->due_from ?? null)?->toDateString(),
                    'due_to' => optional($schedule->due_to ?? null)?->toDateString()
                        ?: optional($schedule->due_date)?->toDateString(),
                    'notes' => $schedule->notes,
                ])
                ->all();

            if ($rows !== []) {
                app(ContractPaymentScheduleService::class)->syncForContract(
                    $agreement,
                    $rows,
                    $agreement->payment_scheme ?? Contract::PAYMENT_SCHEME_INSTALLMENTS,
                );
            }
        }

        $agreement->regenerateAgreementBody();

        return $agreement->fresh();
    }

    public function syncPayment(Contract|EventAgreement $agreement, ?EventSettlement $targetSettlement = null): void
    {
        if ($agreement instanceof Contract) {
            app(ContractPaymentSyncService::class)->sync($agreement->fresh(), $targetSettlement);

            return;
        }

        app(AgreementPaymentSyncService::class)->sync($agreement->fresh(), $targetSettlement);
    }

    /**
     * @return Builder<Contract>
     */
    public function contractQuery(): Builder
    {
        return Contract::query()
            ->with(['event', 'contractTemplate', 'participantPayment']);
    }

    /**
     * @return Builder<EventAgreement>
     */
    public function legacyAgreementQuery(): Builder
    {
        return EventAgreement::query()
            ->with(['event', 'contractTemplate', 'participantPayment']);
    }
}

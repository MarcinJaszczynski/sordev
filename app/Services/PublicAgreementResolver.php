<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Models\EventSettlement;
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

        $agreement = $model::create([
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
        ]);

        $agreement->regenerateAgreementBody();

        return $agreement;
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

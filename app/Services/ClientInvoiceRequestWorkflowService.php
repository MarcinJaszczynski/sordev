<?php

namespace App\Services;

use App\Actions\Finance\CreateVatMarginInvoiceDraftAction;
use App\Data\CreateVatMarginInvoiceDraftData;
use App\Models\ClientInvoiceRequest;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ClientInvoiceRequestWorkflowService
{
    public function __construct(
        private readonly CreateVatMarginInvoiceDraftAction $createVatMarginInvoiceDraft,
    ) {}

    public function markProcessed(ClientInvoiceRequest $request, User $actor, ?string $adminNotes = null): ClientInvoiceRequest
    {
        return $this->transition($request, ClientInvoiceRequest::STATUS_PROCESSED, $actor, $adminNotes, createDraft: true);
    }

    public function markRejected(ClientInvoiceRequest $request, User $actor, ?string $adminNotes = null): ClientInvoiceRequest
    {
        if (! filled(trim((string) $adminNotes))) {
            throw new InvalidArgumentException('Przy odrzuceniu wniosku wymagana jest notatka.');
        }

        return $this->transition($request, ClientInvoiceRequest::STATUS_REJECTED, $actor, $adminNotes, createDraft: false);
    }

    public function reopen(ClientInvoiceRequest $request): ClientInvoiceRequest
    {
        if ($request->status === ClientInvoiceRequest::STATUS_PENDING) {
            return $request;
        }

        return DB::transaction(function () use ($request): ClientInvoiceRequest {
            $request->update([
                'status' => ClientInvoiceRequest::STATUS_PENDING,
                'processed_by' => null,
                'processed_at' => null,
            ]);

            return $request->fresh(['event', 'contract', 'user', 'processedByUser']);
        });
    }

    private function transition(
        ClientInvoiceRequest $request,
        string $status,
        User $actor,
        ?string $adminNotes = null,
        bool $createDraft = false,
    ): ClientInvoiceRequest {
        if ($request->status !== ClientInvoiceRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Można zmienić status tylko wniosku oczekującego.');
        }

        return DB::transaction(function () use ($request, $status, $actor, $adminNotes, $createDraft): ClientInvoiceRequest {
            $payload = [
                'status' => $status,
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ];

            if ($adminNotes !== null) {
                $payload['admin_notes'] = $adminNotes;
            }

            $request->update($payload);

            if ($createDraft && Schema::hasTable('sales_invoices') && $request->event) {
                ($this->createVatMarginInvoiceDraft)(new CreateVatMarginInvoiceDraftData(
                    event: $request->event,
                    type: SalesInvoice::TYPE_FINAL,
                    request: $request->fresh(),
                    createdBy: $actor->id,
                ));
            }

            return $request->fresh(['event', 'contract', 'user', 'processedByUser']);
        });
    }
}

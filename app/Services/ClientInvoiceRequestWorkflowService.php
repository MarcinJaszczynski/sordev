<?php

namespace App\Services;

use App\Models\ClientInvoiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClientInvoiceRequestWorkflowService
{
    public function markProcessed(ClientInvoiceRequest $request, User $actor, ?string $adminNotes = null): ClientInvoiceRequest
    {
        return $this->transition($request, ClientInvoiceRequest::STATUS_PROCESSED, $actor, $adminNotes);
    }

    public function markRejected(ClientInvoiceRequest $request, User $actor, ?string $adminNotes = null): ClientInvoiceRequest
    {
        if (! filled(trim((string) $adminNotes))) {
            throw new InvalidArgumentException('Przy odrzuceniu wniosku wymagana jest notatka.');
        }

        return $this->transition($request, ClientInvoiceRequest::STATUS_REJECTED, $actor, $adminNotes);
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
    ): ClientInvoiceRequest {
        if ($request->status !== ClientInvoiceRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Można zmienić status tylko wniosku oczekującego.');
        }

        return DB::transaction(function () use ($request, $status, $actor, $adminNotes): ClientInvoiceRequest {
            $payload = [
                'status' => $status,
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ];

            if ($adminNotes !== null) {
                $payload['admin_notes'] = $adminNotes;
            }

            $request->update($payload);

            return $request->fresh(['event', 'contract', 'user', 'processedByUser']);
        });
    }
}

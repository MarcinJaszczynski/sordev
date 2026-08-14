<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Http\Controllers\Client\ClientContractPdfController;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);

        $user = $request->user();
        $role = $access->isGuardian($user, $event)
            ? EventPortalAccess::ROLE_GUARDIAN
            : EventPortalAccess::ROLE_PARTICIPANT;
        $contract = $access->accessibleContract($user, $event, $role);

        if (! $contract) {
            return $this->success([
                'event_id' => $event->id,
                'contract' => null,
                'pdf_url' => null,
            ]);
        }

        return $this->success([
            'event_id' => $event->id,
            'role' => $role,
            'contract' => [
                'id' => $contract->id,
                'status' => $contract->status,
                'contract_type' => $contract->contract_type,
                'title' => $contract->title,
                'contract_number' => $contract->contract_number ?? null,
                'agreement_number' => $contract->agreement_number ?? null,
                'signer_name' => $contract->signer_name,
                'amount_due' => $contract->amount_due,
                'amount_paid' => $contract->amount_paid ?? null,
                'payment_status' => $contract->payment_status,
                'currency' => $contract->currency,
                'contract_date' => method_exists($contract->contract_date, 'toDateString')
                    ? $contract->contract_date->toDateString()
                    : $contract->contract_date,
            ],
            'pdf_url' => url('/api/v1/client/trips/'.$event->id.'/agreement/pdf'),
        ]);
    }

    public function pdf(Request $request, Event $event)
    {
        $this->authorizeClientDetails($event);

        return app(ClientContractPdfController::class)($event, app(\App\Services\AgreementDocumentService::class), app(ClientAccessService::class));
    }
}

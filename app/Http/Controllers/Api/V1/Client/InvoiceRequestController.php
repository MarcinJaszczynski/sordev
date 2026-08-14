<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Finance\CreateClientInvoiceRequestAction;
use App\Data\CreateClientInvoiceRequestData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InvoiceRequestController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function index(Request $request, Event $event, ClientAccessService $access): JsonResponse
    {
        $this->authorizeClientDetails($event);
        $user = $request->user();
        abort_unless($access->isParticipant($user, $event) || $access->isGuardian($user, $event), 403);

        if (! Schema::hasTable('client_invoice_requests')) {
            return $this->success(['event_id' => $event->id, 'items' => []]);
        }

        $items = ClientInvoiceRequest::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(fn (ClientInvoiceRequest $row) => [
                'id' => $row->id,
                'status' => $row->status,
                'buyer_type' => $row->buyer_type,
                'company_name' => $row->company_name,
                'invoice_email' => $row->invoice_email,
                'amount' => $row->amount,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->values();

        return $this->success(['event_id' => $event->id, 'items' => $items]);
    }

    public function store(
        Request $request,
        Event $event,
        ClientAccessService $access,
        CreateClientInvoiceRequestAction $action,
    ): JsonResponse {
        $this->authorizeClientDetails($event);
        $this->assertClientCanMutate();
        $user = $request->user();
        abort_unless($access->isParticipant($user, $event) || $access->isGuardian($user, $event), 403);

        $validated = $request->validate([
            'buyer_type' => ['required', Rule::in([ClientInvoiceRequest::BUYER_PERSON, ClientInvoiceRequest::BUYER_COMPANY])],
            'company_name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'invoice_email' => ['required', 'email', 'max:255'],
            'applicant_phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contract = $access->accessibleContract($user, $event, EventPortalAccess::ROLE_PARTICIPANT)
            ?? $access->accessibleContract($user, $event, EventPortalAccess::ROLE_GUARDIAN);

        $invoice = $action(new CreateClientInvoiceRequestData(
            buyerType: $validated['buyer_type'],
            companyName: $validated['company_name'],
            invoiceEmail: $validated['invoice_email'],
            source: ClientInvoiceRequest::SOURCE_PORTAL,
            nip: $validated['nip'] ?? null,
            street: $validated['street'] ?? null,
            houseNumber: $validated['house_number'] ?? null,
            postalCode: $validated['postal_code'] ?? null,
            city: $validated['city'] ?? null,
            applicantPhone: $validated['applicant_phone'] ?? null,
            amount: isset($validated['amount']) ? (float) $validated['amount'] : null,
            paymentReference: $validated['payment_reference'] ?? null,
            notes: $validated['notes'] ?? null,
            event: $event,
            contract: $contract,
            user: $user,
        ));

        return $this->success([
            'id' => $invoice->id,
            'status' => $invoice->status,
        ], 'Wysłano wniosek o fakturę.', 201);
    }
}

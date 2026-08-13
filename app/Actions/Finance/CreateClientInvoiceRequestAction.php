<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\CreateClientInvoiceRequestData;
use App\Mail\ClientInvoiceRequestAdminMail;
use App\Mail\ClientInvoiceRequestConfirmationMail;
use App\Models\ClientInvoiceRequest;
use App\Support\OfficeMailRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

final class CreateClientInvoiceRequestAction
{
    public function __invoke(CreateClientInvoiceRequestData $data): ClientInvoiceRequest
    {
        if (! in_array($data->buyerType, [
            ClientInvoiceRequest::BUYER_PERSON,
            ClientInvoiceRequest::BUYER_COMPANY,
        ], true)) {
            throw new InvalidArgumentException('Nieprawidłowy typ nabywcy.');
        }

        if (! in_array($data->source, [
            ClientInvoiceRequest::SOURCE_PORTAL,
            ClientInvoiceRequest::SOURCE_WEB,
            ClientInvoiceRequest::SOURCE_ADMIN,
        ], true)) {
            throw new InvalidArgumentException('Nieprawidłowe źródło wniosku.');
        }

        if ($data->buyerType === ClientInvoiceRequest::BUYER_COMPANY && ! filled(trim((string) $data->nip))) {
            throw new InvalidArgumentException('Dla firmy / instytucji wymagany jest NIP.');
        }

        $request = DB::transaction(function () use ($data): ClientInvoiceRequest {
            return ClientInvoiceRequest::query()->create([
                'event_id' => $data->event?->id,
                'event_code_entered' => $data->eventCodeEntered,
                'contract_id' => $data->contract?->id,
                'user_id' => $data->user?->id,
                'buyer_type' => $data->buyerType,
                'source' => $data->source,
                'company_name' => trim($data->companyName),
                'nip' => $data->buyerType === ClientInvoiceRequest::BUYER_COMPANY
                    ? preg_replace('/\s+/', '', (string) $data->nip)
                    : (filled($data->nip) ? preg_replace('/\s+/', '', (string) $data->nip) : null),
                'street' => $data->street,
                'house_number' => $data->houseNumber,
                'postal_code' => $data->postalCode,
                'city' => $data->city,
                'invoice_email' => $data->invoiceEmail,
                'applicant_phone' => $data->applicantPhone,
                'amount' => $data->amount,
                'payment_reference' => $data->paymentReference,
                'notes' => $data->notes,
                'status' => ClientInvoiceRequest::STATUS_PENDING,
            ]);
        });

        $this->sendMails($request->fresh(['event']));

        return $request;
    }

    private function sendMails(ClientInvoiceRequest $request): void
    {
        // Ręczne wnioski z biura — bez maila potwierdzenia do klienta (biuro już wie).
        if ($request->source === ClientInvoiceRequest::SOURCE_ADMIN) {
            return;
        }

        try {
            Mail::to($request->invoice_email)
                ->send(new ClientInvoiceRequestConfirmationMail($request));
        } catch (\Throwable $e) {
            Log::warning('Client invoice request confirmation mail failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $office = OfficeMailRecipients::inquiries();
            if ($office === []) {
                return;
            }

            Mail::to($office)
                ->send(new ClientInvoiceRequestAdminMail($request));
        } catch (\Throwable $e) {
            Log::warning('Client invoice request admin mail failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

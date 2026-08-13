<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Data\CreateClientTripInquiryData;
use App\Mail\ClientTripInquiryAdminMail;
use App\Models\ClientTripInquiry;
use App\Support\OfficeMailRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class CreateClientTripInquiryAction
{
    public function __invoke(CreateClientTripInquiryData $data): ClientTripInquiry
    {
        $subject = trim($data->subject);
        $body = trim($data->body);

        if ($subject === '' || $body === '') {
            throw new InvalidArgumentException('Podaj temat i treść zapytania.');
        }

        $inquiry = DB::transaction(function () use ($data, $subject, $body): ClientTripInquiry {
            $payload = [
                'event_id' => $data->event->id,
                'user_id' => $data->user->id,
                'contract_id' => $data->contract?->id,
                'event_portal_access_id' => $data->portalAccess?->id,
                'subject' => mb_substr($subject, 0, 255),
                'body' => $body,
                'status' => ClientTripInquiry::STATUS_OPEN,
            ];

            if (Schema::hasColumn('client_trip_inquiries', 'source')) {
                $payload['source'] = $data->source === ClientTripInquiry::SOURCE_PILOT
                    ? ClientTripInquiry::SOURCE_PILOT
                    : ClientTripInquiry::SOURCE_CLIENT;
            }

            return ClientTripInquiry::query()->create($payload);
        });

        $this->notifyOffice($inquiry->fresh(['event', 'user']));

        return $inquiry;
    }

    private function notifyOffice(ClientTripInquiry $inquiry): void
    {
        try {
            $office = OfficeMailRecipients::inquiries();
            if ($office === []) {
                return;
            }

            Mail::to($office)->send(new ClientTripInquiryAdminMail($inquiry));
        } catch (\Throwable $e) {
            Log::warning('Client trip inquiry admin mail failed', [
                'inquiry_id' => $inquiry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

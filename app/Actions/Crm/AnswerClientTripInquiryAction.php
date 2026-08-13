<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Mail\ClientTripInquiryReplyMail;
use App\Models\ClientTripInquiry;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

final class AnswerClientTripInquiryAction
{
    public function __invoke(ClientTripInquiry $inquiry, string $reply, User $answeredBy): ClientTripInquiry
    {
        $reply = trim($reply);
        if ($reply === '') {
            throw new InvalidArgumentException('Podaj treść odpowiedzi.');
        }

        $inquiry->forceFill([
            'office_reply' => $reply,
            'status' => ClientTripInquiry::STATUS_ANSWERED,
            'answered_by' => $answeredBy->id,
            'answered_at' => now(),
        ])->save();

        $inquiry = $inquiry->fresh(['event', 'user']) ?? $inquiry;

        try {
            $email = $inquiry->user?->email;
            if (filled($email)) {
                Mail::to($email)->send(new ClientTripInquiryReplyMail($inquiry));
            }
        } catch (\Throwable $e) {
            Log::warning('Client trip inquiry reply mail failed', [
                'inquiry_id' => $inquiry->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $inquiry;
    }
}

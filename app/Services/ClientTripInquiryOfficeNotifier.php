<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ClientTripInquiryAdminMail;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use App\Models\User;
use App\Support\OfficeMailRecipients;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Routing powiadomień biura dla zapytań z portalu klienta/pilota.
 *
 * Opiekun imprezy (office_caretaker_id) dostaje mail pierwszy;
 * po 24h od created_at (bez resetu przy zmianie opiekuna) — całe biuro.
 */
final class ClientTripInquiryOfficeNotifier
{
    public const ESCALATION_HOURS = 24;

    public function notifyOnCreate(ClientTripInquiry $inquiry): void
    {
        $inquiry->loadMissing(['event.officeCaretaker', 'user']);

        $caretaker = $this->resolveActiveCaretaker($inquiry->event);

        if ($caretaker) {
            $this->sendToEmails([$caretaker->email], $inquiry);

            return;
        }

        $this->markEscalated($inquiry);
        $this->sendToEmails(OfficeMailRecipients::inquiries(), $inquiry);
    }

    /**
     * Eskalacja otwartych zapytań starszych niż 24h (oryginalny created_at).
     *
     * @return int liczba zeskalowanych rekordów
     */
    public function escalateDue(): int
    {
        if (! Schema::hasTable('client_trip_inquiries') || ! Schema::hasColumn('client_trip_inquiries', 'escalated_at')) {
            return 0;
        }

        $dueBefore = now()->subHours(self::ESCALATION_HOURS);
        $count = 0;

        ClientTripInquiry::query()
            ->with(['event.officeCaretaker', 'user'])
            ->where('status', ClientTripInquiry::STATUS_OPEN)
            ->whereNull('escalated_at')
            ->where('created_at', '<=', $dueBefore)
            ->whereHas('event', fn ($q) => $q->whereNotNull('office_caretaker_id'))
            ->orderBy('id')
            ->chunkById(50, function ($inquiries) use (&$count): void {
                foreach ($inquiries as $inquiry) {
                    $this->escalate($inquiry);
                    $count++;
                }
            });

        return $count;
    }

    public function escalate(ClientTripInquiry $inquiry): void
    {
        if ($inquiry->escalated_at !== null) {
            return;
        }

        $this->markEscalated($inquiry);
        $inquiry->loadMissing(['event', 'user']);
        $this->sendToEmails(OfficeMailRecipients::inquiries(), $inquiry);
    }

    /**
     * Po zmianie opiekuna: mail do nowej osoby o otwartych, jeszcze niezeskalowanych zapytaniach.
     * Zegar 24h (created_at) pozostaje bez zmian.
     */
    public function notifyNewCaretakerAboutOpenInquiries(Event $event, User $newCaretaker): void
    {
        if (! Schema::hasTable('client_trip_inquiries')) {
            return;
        }

        if (! filled($newCaretaker->email)) {
            return;
        }

        $query = ClientTripInquiry::query()
            ->with(['event', 'user'])
            ->where('event_id', $event->id)
            ->where('status', ClientTripInquiry::STATUS_OPEN);

        if (Schema::hasColumn('client_trip_inquiries', 'escalated_at')) {
            $query->whereNull('escalated_at');
        }

        $query->orderBy('id')->each(function (ClientTripInquiry $inquiry) use ($newCaretaker): void {
            $this->sendToEmails([(string) $newCaretaker->email], $inquiry);
        });
    }

    public function resolveActiveCaretaker(?Event $event): ?User
    {
        if (! $event?->office_caretaker_id) {
            return null;
        }

        $caretaker = $event->relationLoaded('officeCaretaker')
            ? $event->officeCaretaker
            : $event->officeCaretaker()->first();

        if (! $caretaker instanceof User || ! filled($caretaker->email)) {
            return null;
        }

        if (! $caretaker->hasRole(['admin', 'super_admin', 'biuro'])) {
            return null;
        }

        return $caretaker;
    }

    /**
     * @param  list<string>  $emails
     */
    private function sendToEmails(array $emails, ClientTripInquiry $inquiry): void
    {
        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($e) => is_string($e) ? strtolower(trim($e)) : '',
            $emails,
        ), static fn (string $e): bool => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))));

        if ($emails === []) {
            return;
        }

        try {
            Mail::to($emails)->send(new ClientTripInquiryAdminMail($inquiry));
        } catch (\Throwable $e) {
            Log::warning('Client trip inquiry admin mail failed', [
                'inquiry_id' => $inquiry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markEscalated(ClientTripInquiry $inquiry): void
    {
        if (! Schema::hasColumn('client_trip_inquiries', 'escalated_at')) {
            return;
        }

        if ($inquiry->escalated_at !== null) {
            return;
        }

        $inquiry->forceFill(['escalated_at' => now()])->save();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EventBulkNoticeMail;
use App\Models\Event;
use App\Models\EventMessageLog;
use App\Models\EventParticipant;
use App\Models\MailTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class EventBulkMessageService
{
    public function __construct(
        private readonly MailTemplateService $templates,
    ) {}

    /**
     * @return Collection<int, EventParticipant>
     */
    public function recipients(Event $event, string $segment): Collection
    {
        $query = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->with('participantPayment');

        $participants = $query->get();

        return $participants->filter(function (EventParticipant $participant) use ($segment): bool {
            return match ($segment) {
                EventMessageLog::SEGMENT_ALL => true,
                EventMessageLog::SEGMENT_OVERDUE => $this->isOverdue($participant),
                EventMessageLog::SEGMENT_MISSING_CONSENTS => ! $participant->hasParentConsent(),
                default => false,
            };
        })->values();
    }

    /**
     * @return array{log: EventMessageLog|null, sent: int, failed: int, recipients: int}
     */
    public function send(Event $event, string $segment): array
    {
        $this->templates->ensureDefaults();
        $recipients = $this->recipients($event, $segment);
        $sent = 0;
        $failed = 0;
        $failures = [];

        foreach ($recipients as $participant) {
            $email = trim((string) $participant->email);
            if ($email === '') {
                continue;
            }

            $remaining = $this->remainingPln($participant);
            $rendered = $this->templates->render(MailTemplate::KEY_EVENT_BULK_NOTICE, [
                'recipient_name' => $participant->fullName() ?: 'Uczestniku',
                'event_name' => $event->name,
                'event_code' => $event->code ?: ('#'.$event->id),
                'remaining_pln' => number_format($remaining, 2, ',', ' '),
                'segment' => $segment,
            ]);

            if ($rendered === null) {
                $failed++;
                $failures[] = $email.': brak szablonu';

                continue;
            }

            try {
                Mail::to($email)->send(new EventBulkNoticeMail(
                    subjectLine: $rendered['subject'],
                    bodyHtml: $rendered['body_html'],
                ));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $failures[] = $email.': '.$e->getMessage();
            }
        }

        $log = null;
        if (Schema::hasTable('event_message_logs')) {
            $log = EventMessageLog::query()->create([
                'event_id' => $event->id,
                'segment' => $segment,
                'mail_template_key' => MailTemplate::KEY_EVENT_BULK_NOTICE,
                'recipients_count' => $recipients->count(),
                'sent_count' => $sent,
                'failed_count' => $failed,
                'created_by' => Auth::id(),
                'meta' => ['failures' => array_slice($failures, 0, 20)],
            ]);
        }

        return [
            'log' => $log,
            'sent' => $sent,
            'failed' => $failed,
            'recipients' => $recipients->count(),
        ];
    }

    private function isOverdue(EventParticipant $participant): bool
    {
        if ($this->remainingPln($participant) <= 0.01) {
            return false;
        }

        // Semantyka: zaległość = pozostało do zapłaty I minął termin raty (nie samo paid < due).
        $participant->loadMissing(['contract.paymentSchedules', 'eventAgreement.paymentSchedules']);

        if ($participant->contract && $this->hasPastDueSchedule($participant->contract->paymentSchedules)) {
            return true;
        }

        if ($participant->eventAgreement && $this->hasPastDueSchedule($participant->eventAgreement->paymentSchedules)) {
            return true;
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|\Illuminate\Database\Eloquent\Collection<int, mixed>  $schedules
     */
    private function hasPastDueSchedule($schedules): bool
    {
        foreach ($schedules as $schedule) {
            $dueDate = $schedule->due_date ?? null;
            if (! $dueDate) {
                continue;
            }

            $amount = round((float) ($schedule->amount ?? 0), 2);
            $paid = round((float) ($schedule->paid_amount ?? 0), 2);
            if ($amount > 0 && $paid < $amount - 0.01 && $dueDate->isPast()) {
                return true;
            }
        }

        return false;
    }

    private function remainingPln(EventParticipant $participant): float
    {
        $payment = $participant->participantPayment;
        if (! $payment) {
            return 0.0;
        }

        return max(0, round((float) $payment->due_amount_pln - (float) $payment->paid_amount_pln, 2));
    }
}

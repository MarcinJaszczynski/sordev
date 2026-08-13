<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class ParticipantPaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array{subject: string, body_html: string}|null */
    private ?array $renderedTemplate = null;

    /**
     * @param  array{due_pln: float, paid_pln: float, remaining_pln: float}  $amounts
     */
    public function __construct(
        public EventSettlementParticipantPayment $payment,
        public Event $event,
        public string $recipientName,
        public array $amounts,
        public ?string $portalUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $rendered = $this->template();
        if ($rendered) {
            return new Envelope(subject: $rendered['subject']);
        }

        $eventName = trim((string) ($this->event->name ?: $this->event->code ?: 'impreza'));

        return new Envelope(
            subject: 'Przypomnienie o dopłacie — '.$eventName,
        );
    }

    public function content(): Content
    {
        $rendered = $this->template();
        if ($rendered) {
            return new Content(
                htmlString: $rendered['body_html'],
            );
        }

        return new Content(
            markdown: 'mail.participant-payment-reminder',
        );
    }

    /**
     * @return array{subject: string, body_html: string}|null
     */
    private function template(): ?array
    {
        if ($this->renderedTemplate !== null) {
            return $this->renderedTemplate;
        }

        if (! Schema::hasTable('mail_templates')) {
            return $this->renderedTemplate = null;
        }

        $this->renderedTemplate = app(MailTemplateService::class)->render(
            \App\Models\MailTemplate::KEY_PAYMENT_REMINDER,
            [
                'recipient_name' => $this->recipientName,
                'event_name' => (string) ($this->event->name ?: ''),
                'event_code' => (string) ($this->event->code ?: '#'.$this->event->id),
                'remaining_pln' => number_format((float) ($this->amounts['remaining_pln'] ?? 0), 2, ',', ' '),
                'due_pln' => number_format((float) ($this->amounts['due_pln'] ?? 0), 2, ',', ' '),
                'paid_pln' => number_format((float) ($this->amounts['paid_pln'] ?? 0), 2, ',', ' '),
                'portal_url' => $this->portalUrl,
            ],
        );

        return $this->renderedTemplate;
    }
}

<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParticipantPaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

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
        $eventName = trim((string) ($this->event->name ?: $this->event->code ?: 'impreza'));

        return new Envelope(
            subject: 'Przypomnienie o dopłacie — '.$eventName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.participant-payment-reminder',
        );
    }
}

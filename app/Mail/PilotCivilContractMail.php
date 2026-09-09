<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Event;
use App\Models\EventPilotAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotCivilContractMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public EventPilotAgreement $agreement,
        public string $pilotName,
        public string $settlementFormLabel,
        public string $pdfBytes,
        public string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Umowa pilota — '.($this->event->code ?: $this->event->name),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pilot-civil-contract',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBytes, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}

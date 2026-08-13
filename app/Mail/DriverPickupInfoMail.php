<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Informacja o podstawieniu — wysyłana do kierowcy (kontrahent). */
class DriverPickupInfoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Event $event,
        public string $messageHtml,
    ) {}

    public function envelope(): Envelope
    {
        $code = $this->event->code ?: '#'.$this->event->id;

        return new Envelope(
            subject: sprintf('[%s] Informacja o podstawieniu — %s', $code, $this->event->name ?: 'Impreza'),
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->messageHtml);
    }
}

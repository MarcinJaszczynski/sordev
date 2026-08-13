<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Krótki mail do klienta przy zmianie statusu oferty / potwierdzenia.
 */
class EventStatusChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Event $event,
        public string $newStatus,
        public string $statusLabel,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        $code = $this->event->code ?: '#'.$this->event->id;

        return new Envelope(
            subject: sprintf('[%s] %s — %s', $code, $this->event->name ?: 'Wycieczka', $this->statusLabel),
        );
    }

    public function content(): Content
    {
        $client = trim((string) ($this->event->client_name ?: 'Państwo'));
        $html = '<p>Dzień dobry, '.e($client).',</p>'
            .'<p>'.e($this->messageBody).'</p>'
            .'<p>Pozdrawiamy,<br>Biuro podróży</p>';

        return new Content(htmlString: $html);
    }
}

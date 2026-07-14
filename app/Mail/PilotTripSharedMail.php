<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotTripSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $pilot,
        public Event $event,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowa wycieczka w panelu pilota — '.($this->event->code ?: $this->event->name),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pilot-trip-shared',
        );
    }
}

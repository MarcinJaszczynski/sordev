<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClientTripInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientTripInquiryReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ClientTripInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Odpowiedź biura: '.$this->inquiry->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Otrzymałeś odpowiedź na zapytanie dotyczące wycieczki '
                .e($this->inquiry->event?->name ?: '')
                .'.</p><p><strong>Twoje pytanie:</strong> '.e($this->inquiry->subject)
                .'</p><p><strong>Odpowiedź biura:</strong></p><p>'
                .nl2br(e((string) $this->inquiry->office_reply)).'</p>',
        );
    }
}

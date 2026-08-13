<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClientTripInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientTripInquiryAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ClientTripInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        $eventName = $this->inquiry->event?->name ?: 'impreza';

        return new Envelope(
            subject: 'Zapytanie z portalu: '.$eventName,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p><strong>Od:</strong> '
                .e($this->inquiry->user?->name.' <'.$this->inquiry->user?->email.'>')
                .'</p><p><strong>Impreza:</strong> '.e($this->inquiry->event?->name ?: '—')
                .'</p><p><strong>Temat:</strong> '.e($this->inquiry->subject)
                .'</p><p>'.nl2br(e($this->inquiry->body)).'</p>',
        );
    }
}

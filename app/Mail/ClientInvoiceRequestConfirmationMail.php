<?php

namespace App\Mail;

use App\Models\ClientInvoiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInvoiceRequestConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientInvoiceRequest $invoiceRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Potwierdzenie wniosku o fakturę — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.client-invoice-request-confirmation',
        );
    }
}

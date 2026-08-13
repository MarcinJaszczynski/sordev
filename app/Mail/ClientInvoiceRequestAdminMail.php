<?php

namespace App\Mail;

use App\Models\ClientInvoiceRequest;
use App\Support\AdminPanelUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInvoiceRequestAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inboxUrl;

    public function __construct(
        public ClientInvoiceRequest $invoiceRequest,
    ) {
        $this->inboxUrl = AdminPanelUrls::clientInvoiceRequestsInbox();
    }

    public function envelope(): Envelope
    {
        $buyer = $this->invoiceRequest->company_name;

        return new Envelope(
            subject: 'Nowy wniosek o fakturę: '.$buyer,
            replyTo: [$this->invoiceRequest->invoice_email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.client-invoice-request-admin',
        );
    }
}

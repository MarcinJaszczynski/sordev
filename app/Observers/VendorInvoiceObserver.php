<?php

namespace App\Observers;

use App\Models\VendorInvoice;
use App\Services\EventPaymentReminderSyncService;

class VendorInvoiceObserver
{
    public function created(VendorInvoice $invoice): void
    {
        app(EventPaymentReminderSyncService::class)->syncVendorInvoice($invoice);
    }

    public function updated(VendorInvoice $invoice): void
    {
        if ($invoice->wasChanged(['due_date', 'payment_status', 'gross_amount', 'paid_amount'])) {
            app(EventPaymentReminderSyncService::class)->syncVendorInvoice($invoice);
        }
    }

    public function deleted(VendorInvoice $invoice): void
    {
        app(EventPaymentReminderSyncService::class)->syncVendorInvoice($invoice);
    }
}

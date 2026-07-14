<?php

namespace App\Observers;

use App\Models\ClientInvoiceRequest;
use App\Services\NotificationService;

class ClientInvoiceRequestObserver
{
    public function created(ClientInvoiceRequest $request): void
    {
        NotificationService::clearCacheForFinanceUsers();
    }

    public function updated(ClientInvoiceRequest $request): void
    {
        if ($request->wasChanged(['status', 'company_name', 'nip'])) {
            NotificationService::clearCacheForFinanceUsers();
        }
    }
}

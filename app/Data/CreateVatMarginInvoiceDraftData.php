<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClientInvoiceRequest;
use App\Models\Event;

readonly class CreateVatMarginInvoiceDraftData
{
    public function __construct(
        public Event $event,
        public string $type = 'final',
        public ?ClientInvoiceRequest $request = null,
        public ?string $buyerName = null,
        public ?string $buyerNip = null,
        public ?string $buyerAddress = null,
        public ?int $createdBy = null,
    ) {}
}

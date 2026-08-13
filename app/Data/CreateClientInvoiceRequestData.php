<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Contract;
use App\Models\Event;
use App\Models\User;

readonly class CreateClientInvoiceRequestData
{
    public function __construct(
        public string $buyerType,
        public string $companyName,
        public string $invoiceEmail,
        public string $source,
        public ?string $nip = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $applicantPhone = null,
        public ?float $amount = null,
        public ?string $paymentReference = null,
        public ?string $notes = null,
        public ?string $eventCodeEntered = null,
        public ?Event $event = null,
        public ?Contract $contract = null,
        public ?User $user = null,
    ) {}
}

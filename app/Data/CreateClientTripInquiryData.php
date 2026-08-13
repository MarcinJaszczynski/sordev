<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClientTripInquiry;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\User;

readonly class CreateClientTripInquiryData
{
    public function __construct(
        public Event $event,
        public User $user,
        public string $subject,
        public string $body,
        public ?Contract $contract = null,
        public ?EventPortalAccess $portalAccess = null,
        public string $source = ClientTripInquiry::SOURCE_CLIENT,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;

/**
 * DTO przeliczenia kosztu bazowego imprezy.
 */
readonly class RecalculateEventTotalsData
{
    public function __construct(
        public Event $event,
        public ?int $participantCount = null,
        public ?int $gratisCount = null,
        public ?int $staffCount = null,
        public ?int $driverCount = null,
        public ?int $startPlaceId = null,
        public bool $persist = false,
    ) {}
}

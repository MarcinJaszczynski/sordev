<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;

/**
 * DTO szybkiego przypisania pilota do imprezy.
 */
readonly class AssignEventPilotData
{
    public function __construct(
        public Event $event,
        public ?int $assignedTo,
        public ?bool $sharedWithPilot = null,
        public ?int $pilotContractorId = null,
    ) {}
}

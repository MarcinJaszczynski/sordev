<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Zmiana statusu imprezy — listener: HandleEventStatusChanged
 * (zadania biurowe + SMS log przez EventStatusAutomationService).
 */
class EventStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Event $event,
        public string $previousStatus,
        public string $newStatus,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Hook pod przyszłe automatyzacje (umowy, SMS, zadania pilota).
 * Na razie bez listenerów — świadomie puste, żeby nie fake'ować procesów.
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

<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;

/**
 * DTO zmiany statusu imprezy (native readonly — bez Spatie Laravel Data).
 */
readonly class ChangeEventStatusData
{
    public function __construct(
        public Event $event,
        public string $status,
        public ?string $reason = null,
    ) {}
}

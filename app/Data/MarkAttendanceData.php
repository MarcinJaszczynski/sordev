<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;

readonly class MarkAttendanceData
{
    /**
     * @param  array<int, string>  $statuses  participantId => present|absent|unknown
     */
    public function __construct(
        public Event $event,
        public int $day,
        public array $statuses,
        public ?int $markedBy = null,
    ) {}
}

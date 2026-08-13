<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EventStatusChanged;
use App\Services\EventStatusAutomationService;

final class HandleEventStatusChanged
{
    public function __construct(
        private readonly EventStatusAutomationService $automations,
    ) {}

    public function handle(EventStatusChanged $event): void
    {
        $this->automations->handle($event);
    }
}

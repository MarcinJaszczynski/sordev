<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Contract;
use App\Models\Event;
use App\Services\EventPaymentInstallmentTemplateService;

final class ApplyEventPaymentScheduleTemplateAction
{
    public function __construct(
        private EventPaymentInstallmentTemplateService $templates,
    ) {}

    public function __invoke(Event $event, ?Contract $only = null): int
    {
        return $this->templates->applyToEventContracts($event, $only);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Contract;
use App\Models\Event;
use App\Services\EventPaymentInstallmentTemplateService;
use Illuminate\Support\Collection;

final class CaptureContractScheduleAsEventTemplateAction
{
    public function __construct(
        private EventPaymentInstallmentTemplateService $templates,
    ) {}

    public function __invoke(Event $event, Contract $contract): Collection
    {
        return $this->templates->captureFromContract($event, $contract);
    }
}

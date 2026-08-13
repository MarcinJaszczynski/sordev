<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Contract;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Services\PaymentScheduleTemplateService;

final class ApplyPaymentScheduleTemplateAction
{
    public function __construct(
        private PaymentScheduleTemplateService $templates,
    ) {}

    public function __invoke(PaymentScheduleTemplate|int $template, Contract $contract, ?Event $event = null): void
    {
        if (is_int($template)) {
            $template = PaymentScheduleTemplate::query()->findOrFail($template);
        }

        $event ??= $contract->event;
        if (! $event) {
            throw new \InvalidArgumentException('Umowa nie ma przypisanej imprezy.');
        }

        $this->templates->applyToContract($template, $contract, $event);
    }
}

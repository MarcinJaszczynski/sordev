<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Contract;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Services\PaymentScheduleTemplateService;

/**
 * Zastosuj globalny szablon harmonogramu na umowy imprezy
 * (opcjonalnie kopiując go też jako szablon imprezy).
 */
final class ApplyPaymentScheduleTemplateToEventAction
{
    public function __construct(
        private PaymentScheduleTemplateService $templates,
    ) {}

    public function __invoke(
        PaymentScheduleTemplate|int $template,
        Event $event,
        bool $copyToEventTemplate = true,
        ?Contract $only = null,
    ): int {
        if (is_int($template)) {
            $template = PaymentScheduleTemplate::query()->findOrFail($template);
        }

        return $this->templates->applyToEventContracts(
            $template,
            $event,
            $copyToEventTemplate,
            $only,
        );
    }
}

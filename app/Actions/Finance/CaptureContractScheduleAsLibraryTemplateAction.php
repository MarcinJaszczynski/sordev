<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Contract;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Services\PaymentScheduleTemplateService;

/**
 * Zapisz raty umowy jako szablon w bibliotece globalnej.
 */
final class CaptureContractScheduleAsLibraryTemplateAction
{
    public function __construct(
        private PaymentScheduleTemplateService $templates,
    ) {}

    public function __invoke(
        Contract $contract,
        string $name,
        ?array $appliesTo = null,
        ?PaymentScheduleTemplate $into = null,
        ?Event $event = null,
    ): PaymentScheduleTemplate {
        $event ??= $contract->event;
        if (! $event) {
            throw new \InvalidArgumentException('Umowa nie ma przypisanej imprezy.');
        }

        return $this->templates->captureFromContract(
            $contract,
            $event,
            $name,
            $appliesTo,
            $into,
        );
    }
}

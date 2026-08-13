<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Event;
use App\Services\EventPaymentInstallmentTemplateService;
use Illuminate\Support\Collection;

final class UpsertEventPaymentInstallmentTemplateAction
{
    public function __construct(
        private EventPaymentInstallmentTemplateService $templates,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __invoke(Event $event, array $rows): Collection
    {
        return $this->templates->syncTemplate($event, $rows);
    }
}

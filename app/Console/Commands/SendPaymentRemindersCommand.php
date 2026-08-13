<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MailTemplateService;
use App\Services\PaymentReminderCadenceService;
use Illuminate\Console\Command;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'payments:send-reminders';

    protected $description = 'Wysyła przypomnienia o zaległych wpłatach wg cadence (config/payments.php)';

    public function handle(PaymentReminderCadenceService $service, MailTemplateService $templates): int
    {
        $templates->ensureDefaults();
        $stats = $service->run();
        $this->info(sprintf(
            'Przeskanowano=%d, wysłano=%d, pominięto=%d, błędy=%d',
            $stats['scanned'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}

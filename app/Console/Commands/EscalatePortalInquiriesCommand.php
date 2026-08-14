<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ClientTripInquiryOfficeNotifier;
use Illuminate\Console\Command;

class EscalatePortalInquiriesCommand extends Command
{
    protected $signature = 'inquiries:escalate-portal';

    protected $description = 'Eskaluj nieobsłużone zapytania z portalu (po 24h) do całego biura';

    public function handle(ClientTripInquiryOfficeNotifier $notifier): int
    {
        $count = $notifier->escalateDue();
        $this->info("Zeskalowano {$count} zapytań z portalu.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs\Tfg;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildMonthlyTfgFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $start = now()->subMonth()->startOfMonth();
        $end = now()->subMonth()->endOfMonth();

        Contract::query()
            ->whereNull('tfg_status')
            ->whereBetween('contract_date', [$start, $end])
            ->whereNull('pending_operation')
            ->each(function (Contract $contract) {
                $contract->queueTfgOperation(Contract::OP_NOWEDANE);
            });

        SubmitTfgFeedJob::dispatch();
    }
}

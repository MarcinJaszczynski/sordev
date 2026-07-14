<?php

namespace App\Jobs\Tfg;

use App\Models\Contract;
use App\Models\TfgFeedLog;
use App\Services\Tfg\TfgFeedClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollTfgFeedStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $feedLogId,
    ) {}

    public function handle(TfgFeedClientInterface $feedClient): void
    {
        $log = TfgFeedLog::query()->find($this->feedLogId);

        if (! $log || blank($log->feed_identifier)) {
            return;
        }

        if ($log->isCompleted() || $log->isFailed()) {
            return;
        }

        $maxAttempts = (int) config('tfg.poll.max_attempts', 72);
        $attempts = ((int) $log->poll_attempts) + 1;
        $log->forceFill(['poll_attempts' => $attempts])->save();

        if ($attempts > $maxAttempts) {
            $log->forceFill([
                'async_status' => 'FAILED',
                'async_errors' => [['message' => 'Przekroczono limit prób pollingu']],
                'completed_at' => now(),
            ])->save();

            return;
        }

        try {
            $result = $feedClient->getFeedStatus($log->feed_identifier);

            $log->forceFill([
                'async_status' => $result['async_status'],
                'async_errors' => $result['async_errors'],
            ])->save();

            if ($result['async_status'] === 'PROCESSING' || $result['async_status'] === 'PENDING') {
                self::dispatch($log->id)->delay(now()->addMinutes((int) config('tfg.poll.interval_minutes', 5)));

                return;
            }

            $log->forceFill(['completed_at' => now()])->save();

            if ($result['async_status'] === 'COMPLETED') {
                $this->markContractsSynced($log);
            } else {
                Log::channel('tfg')->warning('TFG feed async failed', ['log_id' => $log->id, 'errors' => $result['async_errors']]);
            }
        } catch (\Throwable $e) {
            Log::channel('tfg')->error('TFG poll failed', ['log_id' => $log->id, 'exception' => $e->getMessage()]);
            self::dispatch($log->id)->delay(now()->addMinutes((int) config('tfg.poll.interval_minutes', 5)));
        }
    }

    protected function markContractsSynced(TfgFeedLog $log): void
    {
        $log->load('contracts');

        foreach ($log->contracts as $contract) {
            $operation = (string) ($contract->pivot->operation ?? $contract->pending_operation ?? Contract::OP_NOWEDANE);

            $updates = [
                'last_feed_log_id' => $log->id,
                'tfg_synced_at' => now(),
                'pending_operation' => null,
                'correction_reason' => null,
            ];

            if ($operation === Contract::OP_USUNIECIE) {
                $updates['tfg_status'] = 'Usunieta';
            } elseif ($operation === Contract::OP_ROZWIAZANIE) {
                $updates['tfg_status'] = 'Rozwiazana';
            } else {
                $updates['tfg_status'] = 'Zawarta';
                $updates['tfg_update_deadline_at'] = now()->addDays(14);
            }

            $contract->forceFill($updates)->saveQuietly();
        }
    }
}

<?php

namespace App\Jobs\Tfg;

use App\Models\Contract;
use App\Models\TfgFeedLog;
use App\Services\Tfg\TfgContractPayloadBuilder;
use App\Services\Tfg\TfgFeedBundler;
use App\Services\Tfg\TfgFeedClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SubmitTfgFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int>|null  $contractIds
     */
    public function __construct(
        public ?array $contractIds = null,
    ) {}

    public function handle(
        TfgContractPayloadBuilder $payloadBuilder,
        TfgFeedBundler $bundler,
        TfgFeedClientInterface $feedClient,
    ): void {
        $query = Contract::query()->whereNotNull('pending_operation');

        if ($this->contractIds !== null) {
            $query->whereIn('id', $this->contractIds);
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            return;
        }

        foreach ($bundler->bundle($contracts) as $chunk) {
            $operation = (string) $chunk->first()->pending_operation;

            $payload = $payloadBuilder->buildForContracts($chunk, $operation);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $path = 'tfg/feeds/'.now()->format('Ymd_His').'_'.uniqid().'.json';
            Storage::disk('local')->put($path, $json);
            $fullPath = Storage::disk('local')->path($path);

            $log = TfgFeedLog::create([
                'operation_type' => $operation,
                'contracts_count' => $chunk->count(),
                'payload_path' => $path,
                'payload_hash' => hash('sha256', (string) $json),
                'payload_version' => TfgContractPayloadBuilder::PAYLOAD_VERSION,
            ]);

            foreach ($chunk as $contract) {
                $log->contracts()->attach($contract->id, [
                    'operation' => $contract->pending_operation,
                    'correction_reason' => $contract->correction_reason,
                ]);
            }

            try {
                $result = $feedClient->submitFeed($fullPath);

                $log->forceFill([
                    'feed_identifier' => $result['feed_identifier'],
                    'sync_status' => $result['sync_status'],
                    'sync_errors' => $result['sync_errors'],
                    'submitted_at' => now(),
                ])->save();

                if ($result['sync_status'] === 'REJECTED' || blank($result['feed_identifier'])) {
                    Log::channel('tfg')->warning('TFG feed rejected at sync stage', ['log_id' => $log->id, 'errors' => $result['sync_errors']]);

                    continue;
                }

                PollTfgFeedStatusJob::dispatch($log->id)
                    ->delay(now()->addMinutes((int) config('tfg.poll.interval_minutes', 5)));
            } catch (\Throwable $e) {
                $log->forceFill([
                    'sync_status' => 'ERROR',
                    'sync_errors' => [['message' => $e->getMessage()]],
                ])->save();

                Log::channel('tfg')->error('TFG feed submit failed', ['log_id' => $log->id, 'exception' => $e->getMessage()]);
            }
        }
    }
}

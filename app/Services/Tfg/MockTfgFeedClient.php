<?php

namespace App\Services\Tfg;

use App\Services\Tfg\Exceptions\TfgValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MockTfgFeedClient implements TfgFeedClientInterface
{
    protected const CACHE_PREFIX = 'tfg.mock.feed.';

    public function submitFeed(string $jsonPath): array
    {
        $payload = json_decode((string) file_get_contents($jsonPath), true);

        if (! is_array($payload)) {
            throw new TfgValidationException('Invalid JSON payload.', ['payload' => 'Invalid JSON']);
        }

        $contracts = (array) data_get($payload, 'contracts', []);
        $errors = [];

        $seenNumbers = [];
        foreach ($contracts as $index => $contract) {
            $number = (string) data_get($contract, 'contract_number', '');
            if ($number === '') {
                $errors[] = ['contract_index' => $index, 'field' => 'contract_number', 'message' => 'Required'];
            } elseif (isset($seenNumbers[$number])) {
                $errors[] = ['contract_index' => $index, 'field' => 'contract_number', 'message' => 'Duplicate in feed'];
            } else {
                $seenNumbers[$number] = true;
                if (Cache::get(self::CACHE_PREFIX.'synced.'.$number)) {
                    $errors[] = ['contract_index' => $index, 'field' => 'contract_number', 'message' => 'Already synced (mock)'];
                }
            }
        }

        if ($errors !== []) {
            return [
                'feed_identifier' => null,
                'sync_status' => 'REJECTED',
                'sync_errors' => $errors,
            ];
        }

        $feedId = 'N-P-'.now()->format('Y').'-'.Str::upper(Str::random(6));

        Cache::put(self::CACHE_PREFIX.$feedId, [
            'status' => 'PROCESSING',
            'contracts' => collect($contracts)->pluck('contract_number')->all(),
            'submitted_at' => now()->toIso8601String(),
            'poll_count' => 0,
        ], now()->addDays(7));

        Storage::disk('local')->put('tfg/mock/'.$feedId.'.json', json_encode($payload, JSON_UNESCAPED_UNICODE));

        return [
            'feed_identifier' => $feedId,
            'sync_status' => 'ACCEPTED',
            'sync_errors' => [],
        ];
    }

    public function getFeedStatus(string $feedIdentifier): array
    {
        $key = self::CACHE_PREFIX.$feedIdentifier;
        $state = Cache::get($key);

        if (! is_array($state)) {
            return [
                'async_status' => 'FAILED',
                'async_errors' => [['message' => 'Unknown feed identifier']],
            ];
        }

        $state['poll_count'] = ((int) ($state['poll_count'] ?? 0)) + 1;

        if ($state['poll_count'] < 2) {
            Cache::put($key, $state, now()->addDays(7));

            return [
                'async_status' => 'PROCESSING',
                'async_errors' => [],
            ];
        }

        foreach ((array) ($state['contracts'] ?? []) as $contractNumber) {
            Cache::put(self::CACHE_PREFIX.'synced.'.$contractNumber, true, now()->addDays(30));
        }

        Cache::put($key, array_merge($state, ['status' => 'COMPLETED']), now()->addDays(7));

        return [
            'async_status' => 'COMPLETED',
            'async_errors' => [],
        ];
    }
}

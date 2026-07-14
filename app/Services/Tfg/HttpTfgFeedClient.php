<?php

namespace App\Services\Tfg;

use App\Services\Tfg\Exceptions\TfgTransportException;
use Illuminate\Support\Facades\Http;

class HttpTfgFeedClient implements TfgFeedClientInterface
{
    public function __construct(
        protected TfgOAuthClient $oauth,
    ) {}

    public function submitFeed(string $jsonPath): array
    {
        $token = $this->oauth->getAccessToken();
        $url = rtrim((string) config('tfg.base_url'), '/').'/'.ltrim((string) config('tfg.feed.submit_path'), '/');

        $response = Http::withToken($token)
            ->attach('file', file_get_contents($jsonPath), basename($jsonPath))
            ->timeout(120)
            ->post($url);

        if ($response->status() === 401) {
            $this->oauth->invalidateToken();
            throw new TfgTransportException('TFG feed submit unauthorized.');
        }

        if (! $response->successful()) {
            throw new TfgTransportException('TFG feed submit failed: '.$response->body());
        }

        return [
            'feed_identifier' => (string) $response->json('feed_identifier', $response->json('id', '')),
            'sync_status' => (string) $response->json('status', 'ACCEPTED'),
            'sync_errors' => (array) $response->json('errors', []),
        ];
    }

    public function getFeedStatus(string $feedIdentifier): array
    {
        $token = $this->oauth->getAccessToken();
        $base = rtrim((string) config('tfg.base_url'), '/').'/'.ltrim((string) config('tfg.feed.submit_path'), '/');

        $response = Http::withToken($token)
            ->timeout(60)
            ->get($base.'/'.$feedIdentifier);

        if ($response->status() === 401) {
            $this->oauth->invalidateToken();
            throw new TfgTransportException('TFG feed status unauthorized.');
        }

        if (! $response->successful()) {
            throw new TfgTransportException('TFG feed status failed: '.$response->body());
        }

        return [
            'async_status' => (string) $response->json('async_status', $response->json('status', 'PROCESSING')),
            'async_errors' => (array) $response->json('errors', []),
        ];
    }
}

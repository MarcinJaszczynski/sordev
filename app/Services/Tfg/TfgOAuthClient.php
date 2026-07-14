<?php

namespace App\Services\Tfg;

use App\Services\Tfg\Exceptions\TfgAuthException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TfgOAuthClient
{
    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (! config('tfg.enabled')) {
            return 'mock-token-disabled';
        }

        $cacheKey = 'tfg.oauth.access_token';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, config('tfg.oauth.token_ttl_seconds', 840), function (): string {
            if (config('tfg.driver') === 'mock') {
                return 'mock-token-'.Str::random(16);
            }

            $response = Http::asForm()
                ->timeout(30)
                ->post($this->tokenUrl(), [
                    'grant_type' => 'password',
                    'username' => config('tfg.oauth.username'),
                    'password' => config('tfg.oauth.password'),
                    'client_id' => config('tfg.oauth.client_id'),
                    'client_secret' => config('tfg.oauth.client_secret'),
                ]);

            if (! $response->successful()) {
                throw new TfgAuthException('TFG OAuth failed: '.$response->body());
            }

            $token = (string) $response->json('access_token', '');

            if ($token === '') {
                throw new TfgAuthException('TFG OAuth response missing access_token.');
            }

            return $token;
        });
    }

    public function invalidateToken(): void
    {
        Cache::forget('tfg.oauth.access_token');
    }

    protected function tokenUrl(): string
    {
        return rtrim((string) config('tfg.base_url'), '/').'/'.ltrim((string) config('tfg.oauth.token_url'), '/');
    }
}

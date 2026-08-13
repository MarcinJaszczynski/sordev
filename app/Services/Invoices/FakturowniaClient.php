<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Klient HTTP API Fakturownia.pl (POST /invoices.json itd.).
 */
final class FakturowniaClient
{
    public function enabled(): bool
    {
        return filled(config('services.fakturownia.token'))
            && filled(config('services.fakturownia.domain'));
    }

    public function baseUrl(): string
    {
        $domain = trim((string) config('services.fakturownia.domain'), '/');
        if ($domain === '') {
            throw new RuntimeException('Brak FAKTUROWNIA_DOMAIN.');
        }

        if (! str_contains($domain, '.')) {
            $domain .= '.fakturownia.pl';
        }

        return 'https://'.$domain;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    public function createInvoice(array $invoice): array
    {
        return $this->request('POST', '/invoices.json', ['invoice' => $invoice]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvoice(int $id): array
    {
        return $this->request('GET', '/invoices/'.$id.'.json');
    }

    public function invoiceViewUrl(int $id): string
    {
        return $this->baseUrl().'/invoices/'.$id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Integracja Fakturownia nie jest skonfigurowana (FAKTUROWNIA_DOMAIN / FAKTUROWNIA_API_TOKEN).');
        }

        $token = (string) config('services.fakturownia.token');
        $url = $this->baseUrl().$path;
        $body = array_merge(['api_token' => $token], $payload);

        try {
            $pending = Http::acceptJson()
                ->asJson()
                ->timeout(20);

            $response = strtoupper($method) === 'GET'
                ? $pending->get($url, ['api_token' => $token])
                : $pending->send($method, $url, ['json' => $body]);

            if (! $response->successful()) {
                Log::warning('Fakturownia API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'path' => $path,
                ]);

                throw new RuntimeException('Fakturownia HTTP '.$response->status().': '.Str::limit($response->body(), 300));
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];

            return $json;
        } catch (RequestException $e) {
            Log::warning('Fakturownia request failed', ['message' => $e->getMessage()]);

            throw new RuntimeException('Nie udało się połączyć z Fakturownią: '.$e->getMessage(), 0, $e);
        }
    }
}

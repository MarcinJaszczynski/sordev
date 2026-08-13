<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wspólna ochrona publicznych formularzy WWW (honeypot / czas / Turnstile / referer).
 *
 * Cloudflare: Turnstile fail ≠ silent success (to gubiło prawdziwe wnioski).
 * Referer za CF jest łagodniejszy — pusty referer nie blokuje w produkcji.
 */
final class FrontFormProtection
{
    public static function isHoneypotFilled(Request $request): bool
    {
        return filled($request->input('website'));
    }

    public static function isSubmittedTooFast(Request $request, int $minSeconds = 3): bool
    {
        $formTs = $request->input('form_ts');
        if (! is_string($formTs) || $formTs === '') {
            return false;
        }

        $ts = (int) $formTs;
        if ($ts <= 0) {
            return false;
        }

        // form_ts jest w ms (Date.now())
        $elapsedMs = (int) (microtime(true) * 1000) - $ts;

        return $elapsedMs >= 0 && $elapsedMs < ($minSeconds * 1000);
    }

    public static function hasInvalidReferer(Request $request): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        $referer = (string) $request->headers->get('referer', '');
        // Za Cloudflare / prywatność — brak referera nie oznacza spamu.
        if ($referer === '') {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $refHost = parse_url($referer, PHP_URL_HOST);

        if (! $appHost || ! $refHost) {
            return false;
        }

        // Akceptuj też hosty Cloudflare / aliasy skonfigurowane w APP_URL.
        return strcasecmp((string) $appHost, (string) $refHost) !== 0;
    }

    public static function verifyTurnstile(Request $request, string $context, bool $requireToken = true): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $secretKey = config('services.turnstile.secret_key');
        $siteKey = config('services.turnstile.site_key');
        if (! $secretKey || ! $siteKey) {
            return true;
        }

        $token = $request->input('cf-turnstile-response');
        if (empty($token)) {
            if (! $requireToken) {
                return true;
            }
            Log::notice('Turnstile token missing', [
                'context' => $context,
                'ip' => $request->ip(),
                'route' => optional($request->route())->getName(),
                'cf_ray' => $request->headers->get('CF-Ray'),
            ]);

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                    'sitekey' => $siteKey,
                ]);

            if (! $response->ok()) {
                Log::warning('Turnstile verification HTTP failure', [
                    'context' => $context,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $payload = $response->json();
            $success = (bool) ($payload['success'] ?? false);
            $reportedAction = $payload['action'] ?? null;

            // CF czasem nie zwraca action — mismatch tylko gdy obie strony podają wartość.
            if (is_string($reportedAction) && $reportedAction !== '' && $reportedAction !== $context) {
                Log::info('Turnstile verification action mismatch', [
                    'context' => $context,
                    'reported_action' => $reportedAction,
                ]);

                return false;
            }

            if (! $success) {
                Log::info('Turnstile verification denied', [
                    'context' => $context,
                    'errors' => $payload['error-codes'] ?? [],
                    'cf_ray' => $request->headers->get('CF-Ray'),
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification exception', [
                'context' => $context,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Czy żądanie wygląda na spam — przy true kontroler powinien zwrócić cichy sukces.
     * Nie obejmuje Turnstile: przy fail Turnstile kontroler ma zwrócić błąd użytkownikowi.
     */
    public static function shouldSilentlyAccept(Request $request, string $turnstileContext): bool
    {
        if (self::isHoneypotFilled($request)) {
            Log::info('Front form silent accept: honeypot', [
                'context' => $turnstileContext,
                'ip' => $request->ip(),
            ]);

            return true;
        }

        if (self::isSubmittedTooFast($request)) {
            Log::info('Front form silent accept: too fast', [
                'context' => $turnstileContext,
                'ip' => $request->ip(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Czy Turnstile zablokował realne złożenie (pokazać błąd, nie silent success).
     */
    public static function turnstileBlocksSubmission(Request $request, string $turnstileContext): bool
    {
        return ! self::verifyTurnstile($request, $turnstileContext);
    }
}

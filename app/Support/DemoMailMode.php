<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * Tryb demo poczty: przekierowanie WSZYSTKICH maili na jeden adres (Mail::alwaysTo).
 *
 * Źródło prawdy: ustawienie w panelu (System → Tryb demo).
 * Fallback: MAIL_DEMO_TO z .env (gdy UI wyłączone / brak wpisu w DB).
 */
final class DemoMailMode
{
    public const SETTING_KEY = 'mail.demo_mode';

    public const DEFAULT_EMAIL = 'm.jaszczynski@gmail.com';

    public static function isEnabled(): bool
    {
        $stored = AppSetting::getValue(self::SETTING_KEY);

        if (is_array($stored) && array_key_exists('enabled', $stored)) {
            return (bool) $stored['enabled'] && filled(self::emailFromStored($stored));
        }

        return filled(self::envEmail());
    }

    public static function email(): ?string
    {
        $stored = AppSetting::getValue(self::SETTING_KEY);

        if (is_array($stored) && array_key_exists('enabled', $stored)) {
            if (! (bool) $stored['enabled']) {
                return null;
            }

            return self::emailFromStored($stored);
        }

        return self::envEmail();
    }

    /**
     * Adres na który Laravel przekieruje całą pocztę, albo null gdy tryb wyłączony.
     */
    public static function redirectTo(): ?string
    {
        $email = self::email();

        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return array{enabled: bool, email: string, source: string}
     */
    public static function status(): array
    {
        $stored = AppSetting::getValue(self::SETTING_KEY);
        $fromUi = is_array($stored) && array_key_exists('enabled', $stored);

        if ($fromUi) {
            return [
                'enabled' => self::isEnabled(),
                'email' => self::emailFromStored($stored) ?? self::DEFAULT_EMAIL,
                'source' => 'panel',
            ];
        }

        $env = self::envEmail();

        return [
            'enabled' => filled($env),
            'email' => $env ?? self::DEFAULT_EMAIL,
            'source' => $env ? 'env' : 'off',
        ];
    }

    public static function save(bool $enabled, ?string $email): void
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            $normalized = self::DEFAULT_EMAIL;
        }

        AppSetting::setValue(self::SETTING_KEY, [
            'enabled' => $enabled,
            'email' => $normalized,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private static function emailFromStored(array $stored): ?string
    {
        $email = strtolower(trim((string) ($stored['email'] ?? '')));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function envEmail(): ?string
    {
        $raw = config('mail.demo_to');
        if (! is_string($raw)) {
            return null;
        }

        $email = strtolower(trim($raw));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}

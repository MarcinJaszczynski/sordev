<?php

declare(strict_types=1);

namespace App\Support;

final class OfficeMailRecipients
{
    /**
     * Adresy biura / właściciela do zapytań i wniosków WWW.
     * Produkcja: MAIL_INQUIRIES_TO (domyślnie rafa@bprafa.pl).
     * Demo: MAIL_DEMO_TO przekierowuje całą pocztę przez Mail::alwaysTo.
     *
     * @return list<string>
     */
    public static function inquiries(): array
    {
        $raw = config('mail.inquiries_to') ?: 'rafa@bprafa.pl';

        return self::parse($raw);
    }

    /**
     * @return list<string>
     */
    public static function parse(string $raw): array
    {
        $emails = preg_split('/[\s,;]+/', $raw) ?: [];

        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}

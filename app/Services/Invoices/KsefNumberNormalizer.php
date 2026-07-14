<?php

namespace App\Services\Invoices;

final class KsefNumberNormalizer
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtoupper(preg_replace('/\s+/', '', trim($value)));

        if (preg_match('/^(\d{10})-(\d{8})-([A-F0-9]+)-([A-Z0-9]{2})$/', $value, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}-{$matches[4]}";
        }

        if (preg_match('/^(\d{10})-(\d{8})([A-F0-9]+)-([A-Z0-9]{2})$/', $value, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}-{$matches[4]}";
        }

        return $value;
    }

    public static function extractFromText(string $text): ?string
    {
        if (preg_match('/(\d{10}-\d{8}-?[A-F0-9]+-[A-Z0-9]{2})/i', $text, $matches)) {
            return self::normalize($matches[1]);
        }

        if (preg_match('/(\d{10}-\d{8}[A-F0-9]+-[A-Z0-9]{2})/i', $text, $matches)) {
            return self::normalize($matches[1]);
        }

        return null;
    }
}

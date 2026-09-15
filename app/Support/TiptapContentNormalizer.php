<?php

namespace App\Support;

/**
 * Tiptap PHP (`setContent`) najpierw robi json_decode na stringu.
 * Wartości skalarne JSON (np. "570") dekodują się do int/bool/null,
 * a serializer Tiptapa oczekuje dokumentu (array) — TypeError / HTTP 500.
 */
final class TiptapContentNormalizer
{
    public static function normalize(string|array|null $content): string|array|null
    {
        if (! is_string($content) || $content === '') {
            return $content;
        }

        $decoded = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $content;
        }

        if (is_array($decoded) || is_object($decoded)) {
            return $content;
        }

        return '<p>'.e($content).'</p>';
    }
}

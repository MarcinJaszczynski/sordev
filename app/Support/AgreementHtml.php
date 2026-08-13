<?php

namespace App\Support;

use FilamentTiptapEditor\Facades\TiptapConverter;

/**
 * Bezpieczne renderowanie treści umowy (TipTap HTML) do PDF / podglądu.
 */
final class AgreementHtml
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><th><td><a><span><blockquote><hr><div>';

    public static function looksLikeHtml(string $content): bool
    {
        return $content !== strip_tags($content);
    }

    /**
     * TipTap w formularzu często trzyma JSON (array) — normalizujemy do HTML string.
     */
    public static function normalizeContent(mixed $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        if (is_array($content)) {
            return (string) TiptapConverter::asHTML($content);
        }

        if (! is_string($content)) {
            return '';
        }

        $trimmed = trim($content);

        if ($trimmed === '') {
            return '';
        }

        // JSON TipTap zapisany jako string
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return (string) TiptapConverter::asHTML($decoded);
            }
        }

        return $content;
    }

    /**
     * Plain text ze starych szablonów → bloki HTML czytelne dla TipTap.
     */
    public static function plainTextToEditorHtml(string $content): string
    {
        $content = trim($content);

        if ($content === '' || self::looksLikeHtml($content)) {
            return $content;
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [$content];

        return collect($lines)
            ->map(function (string $line): string {
                $line = e($line);

                return $line === '' ? '<p></p>' : '<p>'.$line.'</p>';
            })
            ->implode('');
    }

    public static function sanitize(string $html): string
    {
        $cleaned = strip_tags($html, self::ALLOWED_TAGS);

        $cleaned = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\shref\s*=\s*([\'"])\s*javascript:[^\'"]*\1/iu', ' href="#"', $cleaned) ?? $cleaned;

        return $cleaned;
    }

    public static function toPreviewHtml(string $content): string
    {
        $content = self::normalizeContent($content);

        if (self::looksLikeHtml($content)) {
            return self::sanitize($content);
        }

        return nl2br(e($content));
    }

    /**
     * HTML (np. z TipTap) → plain text do edycji w Textarea.
     */
    public static function toEditablePlainText(string $content): string
    {
        $content = self::normalizeContent($content);

        if (! self::looksLikeHtml($content)) {
            return $content;
        }

        $withBreaks = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $content) ?? $content;
        $withBreaks = preg_replace('/<\/\s*p\s*>/iu', "\n", $withBreaks) ?? $withBreaks;
        $withBreaks = preg_replace('/<\/\s*div\s*>/iu', "\n", $withBreaks) ?? $withBreaks;
        $withBreaks = preg_replace('/<\/\s*h[1-6]\s*>/iu', "\n", $withBreaks) ?? $withBreaks;
        $withBreaks = preg_replace('/<\/\s*li\s*>/iu', "\n", $withBreaks) ?? $withBreaks;

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}

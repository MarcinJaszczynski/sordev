<?php

namespace App\Support\Tasks;

use App\Models\TaskComment;

/**
 * Prezentacja komentarzy w wątku zadania (Blade).
 * Bez pola type w DB — warianty z heurystyki treści + relacji autor/viewer.
 */
final class TaskCommentPresentation
{
    public const VARIANT_OWN = 'own';

    public const VARIANT_OTHER = 'other';

    public const VARIANT_SYSTEM = 'system';

    public const VARIANT_RESERVATION = 'reservation';

    /**
     * @return array{
     *     variant: string,
     *     author: string,
     *     created_at: string,
     *     body: string,
     *     reservation: null|array{
     *         title: string,
     *         fields: list<array{label: string, value: string}>,
     *         remainder: string
     *     }
     * }
     */
    public static function for(TaskComment $comment, ?int $viewerId = null): array
    {
        $comment->loadMissing('author');

        $body = trim((string) ($comment->content ?? ''));
        $author = trim((string) ($comment->author?->name ?? ''));
        $authorLabel = $author !== '' ? $author : '—';
        $createdAt = $comment->created_at?->format('d.m.Y H:i') ?? '—';

        if (self::isReservationLike($body)) {
            return [
                'variant' => self::VARIANT_RESERVATION,
                'author' => $authorLabel,
                'created_at' => $createdAt,
                'body' => $body,
                'reservation' => self::parseReservationCard($body),
            ];
        }

        if (self::isSystemLike($comment, $body, $author)) {
            return [
                'variant' => self::VARIANT_SYSTEM,
                'author' => $author !== '' ? $author : 'System',
                'created_at' => $createdAt,
                'body' => $body,
                'reservation' => null,
            ];
        }

        $isOwn = $viewerId !== null && (int) $comment->user_id === $viewerId;

        return [
            'variant' => $isOwn ? self::VARIANT_OWN : self::VARIANT_OTHER,
            'author' => $authorLabel,
            'created_at' => $createdAt,
            'body' => $body,
            'reservation' => null,
        ];
    }

    /**
     * Dymek na liście zadań — own/other/system (bez karty hotelowej).
     *
     * @return array{id: int, variant: string, author: string, content: string, created_at: string}
     */
    public static function forListBubble(TaskComment $comment, ?int $viewerId = null): array
    {
        $presentation = self::for($comment, $viewerId);
        $variant = $presentation['variant'];

        if ($variant === self::VARIANT_RESERVATION) {
            $variant = $viewerId !== null && (int) $comment->user_id === $viewerId
                ? self::VARIANT_OWN
                : self::VARIANT_OTHER;
        }

        return [
            'id' => (int) $comment->id,
            'variant' => $variant,
            'author' => $presentation['author'],
            'content' => TaskListColumn::sanitizeTaskText($presentation['body'], 512),
            'created_at' => $presentation['created_at'],
        ];
    }

    public static function isReservationLike(string $body): bool
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^rezerwacj/iu', $trimmed) === 1) {
            return true;
        }

        $labelHits = 0;
        foreach (self::fieldPatterns() as $pattern) {
            if (preg_match($pattern, $trimmed) === 1) {
                $labelHits++;
            }
        }

        // Kilka etykiet + wieloliniowość ≈ karta oferty, nie zwykły chat.
        return $labelHits >= 2 && substr_count($trimmed, "\n") >= 1;
    }

    public static function isSystemLike(TaskComment $comment, string $body, string $author): bool
    {
        if ($comment->user_id === null) {
            return true;
        }

        if ($author !== '' && preg_match('/^(system|automat)$/iu', $author) === 1) {
            return true;
        }

        if (str_contains($body, '[payment-reminder:')) {
            return true;
        }

        return preg_match('/^(status|system|automatycznie)\s*:/iu', trim($body)) === 1;
    }

    /**
     * @return array{title: string, fields: list<array{label: string, value: string}>, remainder: string}
     */
    public static function parseReservationCard(string $body): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($body)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));

        $title = 'Rezerwacja';
        $fields = [];
        $remainder = [];
        $used = [];

        if ($lines !== [] && preg_match('/^rezerwacj(?:a|i)?\s*[:\-]?\s*(.*)$/iu', $lines[0], $m) === 1) {
            $suffix = trim((string) ($m[1] ?? ''));
            $title = $suffix !== '' ? 'Rezerwacja: '.$suffix : 'Rezerwacja';
            $used[0] = true;
        }

        foreach ($lines as $index => $line) {
            if (isset($used[$index])) {
                continue;
            }

            $matched = false;
            foreach (self::fieldLabelMap() as $label => $pattern) {
                if (preg_match($pattern, $line, $m) !== 1) {
                    continue;
                }

                $value = trim((string) ($m[1] ?? ''));
                if ($value === '') {
                    break;
                }

                $fields[] = ['label' => $label, 'value' => $value];
                $used[$index] = true;
                $matched = true;
                break;
            }

            if (! $matched) {
                $remainder[] = $line;
            }
        }

        return [
            'title' => $title,
            'fields' => $fields,
            'remainder' => implode("\n", $remainder),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function fieldLabelMap(): array
    {
        return [
            'Hotel' => '/^(?:hotel|obiekt)\s*[:\-]\s*(.+)$/iu',
            'Cena' => '/^(?:cena|koszt|kwota)\s*[:\-]\s*(.+)$/iu',
            'Warunki' => '/^(?:warunki|wyżywienie|board)\s*[:\-]\s*(.+)$/iu',
            'Świadczenia' => '/^(?:świadczenia|swiadczenia|w cenie|oferta)\s*[:\-]\s*(.+)$/iu',
            'Referencja' => '/^(?:referencja|nr(?:\s*rezerwacji)?|booking)\s*[:\-]\s*(.+)$/iu',
            'HB / wyżywienie' => '/^((?:HB|BB|FB|AI|MAP)\b.*)$/u',
        ];
    }

    /**
     * @return list<string>
     */
    private static function fieldPatterns(): array
    {
        return array_values(self::fieldLabelMap());
    }
}

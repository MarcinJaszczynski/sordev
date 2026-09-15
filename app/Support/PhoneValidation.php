<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Walidacja numerów telefonu — format międzynarodowy (+48, +44 itd.), ze spacjami i myślnikami.
 * Dodatkowo normalizacja do wyszukiwania (ignorowanie separatorów)
 * oraz sanityzacja typowych artefaktów wklejania (NBSP, en/em-dash).
 */
final class PhoneValidation
{
    public const MAX_LENGTH = 50;

    /** Minimalna liczba cyfr, by uznać frazę za numer telefonu (całość). */
    public const MIN_PHONE_DIGITS = 6;

    /** Minimalna liczba cyfr w tokenie, by dorzucić porównanie znormalizowane. */
    public const MIN_FRAGMENT_DIGITS = 3;

    public const INVALID_MESSAGE = 'Niepoprawny numer telefonu. Użyj formatu międzynarodowego, np. +44 7700 900123.';

    /**
     * Prefiks +, cyfry, spacje, nawiasy, myślniki, kropki, ukośniki.
     * Po sanitize() wartość jest już trimnięta — + musi być na początku albo wcale.
     */
    public const REGEX = '/^[+]?[\d\s().\/-]{6,50}$/u';

    /**
     * Separatory usuwane przy porównaniu w SQL (MySQL + SQLite: REPLACE).
     * Zawiera też NBSP (U+00A0), częsty przy kopiowaniu z dokumentów.
     *
     * @var list<string>
     */
    private const SEARCH_STRIP_CHARS = [' ', '-', '(', ')', '+', '.', '/', "\u{00A0}"];

    /**
     * @return list<string|Closure>
     */
    public static function optionalRules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, self::formatRule()];
    }

    /**
     * @return list<string|Closure>
     */
    public static function requiredRules(): array
    {
        return ['required', 'string', 'max:'.self::MAX_LENGTH, self::formatRule()];
    }

    /**
     * Czyści typowe śmieci z wklejania: NBSP, en/em-dash, zero-width, nadmiar spacji.
     */
    public static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(
            [
                "\u{00A0}", // NBSP
                "\u{202F}", // narrow NBSP
                "\u{2007}", // figure space
                "\u{2013}", // en-dash
                "\u{2014}", // em-dash
                "\u{2212}", // minus sign
            ],
            [
                ' ',
                ' ',
                ' ',
                '-',
                '-',
                '-',
            ],
            $value,
        );

        // Zero-width / soft hyphen — częste przy kopiowaniu z PDF/Word.
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $value) ?? $value;
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }

    /**
     * Zostawia wyłącznie cyfry (do porównań / LIKE po stronie aplikacji).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return filled($digits) ? $digits : null;
    }

    /**
     * Czy cała fraza wygląda jak numer (same znaki telefonu + wystarczająco cyfr).
     * „Kowalski 603846062” → false; „123 456 789” / „+48 606 102 243” → true.
     */
    public static function looksLikePhone(string $value): bool
    {
        $sanitized = self::sanitize($value);

        if ($sanitized === null) {
            return false;
        }

        if (! preg_match('/^[+]?[\d\s().\/-]+$/u', $sanitized)) {
            return false;
        }

        $digits = self::normalize($sanitized);

        return $digits !== null && strlen($digits) >= self::MIN_PHONE_DIGITS;
    }

    /**
     * Wyrażenie SQL usuwające separatory z kolumny (bez REGEXP — działa na MySQL i SQLite).
     *
     * @param  string  $column  Bezpieczny identyfikator: `phone` lub `contractors.phone`
     */
    public static function digitsSql(string $column): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException('Invalid SQL column for phone digits expression.');
        }

        $expr = $column;

        foreach (self::SEARCH_STRIP_CHARS as $char) {
            $escaped = str_replace("'", "''", $char);
            $expr = "REPLACE({$expr}, '{$escaped}', '')";
        }

        return $expr;
    }

    /**
     * WHERE (kolumna LIKE %search% OR znormalizowane_cyfry LIKE %digits%).
     *
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    public static function constrainDigitsLike(EloquentBuilder|QueryBuilder $query, string $column, string $search): void
    {
        $digits = self::normalize($search);

        $query->where(function (EloquentBuilder|QueryBuilder $inner) use ($column, $search, $digits): void {
            $inner->where($column, 'like', '%'.$search.'%');

            if ($digits !== null && strlen($digits) >= self::MIN_FRAGMENT_DIGITS) {
                $inner->orWhereRaw(self::digitsSql($column).' LIKE ?', ['%'.$digits.'%']);
            }
        });
    }

    /**
     * OR WHERE (kolumna LIKE %search% OR znormalizowane_cyfry LIKE %digits%).
     *
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    public static function orWhereDigitsLike(EloquentBuilder|QueryBuilder $query, string $column, string $search): void
    {
        $digits = self::normalize($search);

        $query->orWhere(function (EloquentBuilder|QueryBuilder $inner) use ($column, $search, $digits): void {
            $inner->where($column, 'like', '%'.$search.'%');

            if ($digits !== null && strlen($digits) >= self::MIN_FRAGMENT_DIGITS) {
                $inner->orWhereRaw(self::digitsSql($column).' LIKE ?', ['%'.$digits.'%']);
            }
        });
    }

    /**
     * Reguła formatu: najpierw sanitize (wklejki), potem regex + min. liczba cyfr.
     */
    private static function formatRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value)) {
                $fail(self::INVALID_MESSAGE);

                return;
            }

            $sanitized = self::sanitize($value);

            if ($sanitized === null) {
                return;
            }

            if (mb_strlen($sanitized) > self::MAX_LENGTH) {
                $fail(self::INVALID_MESSAGE);

                return;
            }

            if (! preg_match(self::REGEX, $sanitized)) {
                $fail(self::INVALID_MESSAGE);

                return;
            }

            $digits = self::normalize($sanitized);

            if ($digits === null || strlen($digits) < self::MIN_PHONE_DIGITS) {
                $fail(self::INVALID_MESSAGE);
            }
        };
    }
}

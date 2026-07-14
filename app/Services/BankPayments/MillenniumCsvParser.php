<?php

namespace App\Services\BankPayments;

use Carbon\Carbon;
use InvalidArgumentException;

final class MillenniumCsvParser
{
    /**
     * @return array<int, array{
     *     operation_date: ?string,
     *     title: string,
     *     counterparty: ?string,
     *     account_number: ?string,
     *     amount_pln: float
     * }>
     */
    public function parse(string $content): array
    {
        $content = $this->normalizeContent($content);
        $rows = $this->readRows($content);

        if ($rows === []) {
            throw new InvalidArgumentException('Plik CSV jest pusty lub nieczytelny.');
        }

        $header = array_shift($rows);
        $map = $this->mapHeaders($header);

        $transactions = [];

        foreach ($rows as $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $amount = $this->parseAmount($this->cell($row, $map['amount']));

            if ($amount === null || $amount <= 0) {
                continue;
            }

            $transactions[] = [
                'operation_date' => $this->parseDate($this->cell($row, $map['date'])),
                'title' => trim((string) $this->cell($row, $map['title'])),
                'counterparty' => $this->nullableCell($row, $map['counterparty']),
                'account_number' => $this->nullableCell($row, $map['account']),
                'amount_pln' => round($amount, 2),
            ];
        }

        if ($transactions === []) {
            throw new InvalidArgumentException('Nie znaleziono wpływów (dodatnich kwot) w pliku CSV.');
        }

        return $transactions;
    }

    private function normalizeContent(string $content): string
    {
        $content = preg_replace("/^\xEF\xBB\xBF/", '', $content) ?? $content;

        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(string $content): array
    {
        $delimiter = str_contains($content, ';') ? ';' : ',';
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(
                fn ($value) => is_string($value) ? trim($value) : (string) $value,
                $row
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string>  $header
     * @return array{date: int, title: int, counterparty: ?int, account: ?int, amount: int}
     */
    private function mapHeaders(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $label) {
            $normalized[$index] = $this->normalizeHeaderLabel($label);
        }

        $date = $this->findColumn($normalized, [
            'data operacji',
            'data',
            'data ksiegowania',
            'data księgowania',
            'data transakcji',
        ]);

        $title = $this->findColumn($normalized, [
            'tytul',
            'tytuł',
            'opis',
            'opis operacji',
            'tytul operacji',
            'tytuł operacji',
        ]);

        $counterparty = $this->findColumn($normalized, [
            'nadawca odbiorca',
            'nadawca/odbiorca',
            'kontrahent',
            'nazwa kontrahenta',
            'odbiorca nadawca',
        ]);

        $account = $this->findColumn($normalized, [
            'numer rachunku',
            'rachunek',
            'nr rachunku',
            'konto',
        ]);

        $amount = $this->findColumn($normalized, [
            'kwota',
            'kwota pln',
            'wartosc',
            'wartość',
            'obrot',
            'obrót',
        ]);

        if ($date === null || $title === null || $amount === null) {
            throw new InvalidArgumentException(
                'Nie rozpoznano nagłówków Millennium CSV. Wymagane kolumny: data operacji, tytuł, kwota.'
            );
        }

        return [
            'date' => $date,
            'title' => $title,
            'counterparty' => $counterparty,
            'account' => $account,
            'amount' => $amount,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($headers as $index => $label) {
            foreach ($candidates as $candidate) {
                if ($label === $candidate || str_contains($label, $candidate)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function normalizeHeaderLabel(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = str_replace(['"', "'"], '', $label);
        $label = str_replace(['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż'], ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'], $label);

        return preg_replace('/\s+/', ' ', $label) ?? $label;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function cell(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return (string) ($row[$index] ?? '');
    }

    /**
     * @param  array<int, string>  $row
     */
    private function nullableCell(array $row, ?int $index): ?string
    {
        $value = trim($this->cell($row, $index));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function parseAmount(string $value): ?float
    {
        $value = trim(str_replace([' ', "\u{00A0}", 'PLN', 'zł', 'zl'], '', $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }

        $value = str_replace(',', '.', $value);
        $value = str_replace('+', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd.m.Y', 'd-m-Y', 'd/m/Y', 'Y.m.d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Services\Tfg;

/**
 * Low-level writer producing CSV bytes that match the official TFG "Wykaz umów"
 * format exactly: UTF-8 with BOM, CRLF line endings, ";" delimiter, no quoting.
 */
class TfgCsvWriter
{
    private const BOM = "\xEF\xBB\xBF";

    public function __construct(
        private readonly string $delimiter = ';',
        private readonly string $eol = "\r\n",
        private readonly bool $bom = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            delimiter: (string) config('tfg.csv.delimiter', ';'),
            eol: (string) config('tfg.csv.eol', "\r\n"),
            bom: (bool) config('tfg.csv.bom', true),
        );
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $rows
     */
    public function build(array $header, array $rows): string
    {
        $lines = [];
        $lines[] = $this->buildLine($header);

        foreach ($rows as $row) {
            $lines[] = $this->buildLine($row);
        }

        $content = implode($this->eol, $lines).$this->eol;

        return ($this->bom ? self::BOM : '').$content;
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function buildLine(array $fields): string
    {
        return implode($this->delimiter, array_map([$this, 'sanitize'], $fields));
    }

    /**
     * The official files contain no quoting even for values with spaces, so any
     * delimiter / newline characters inside a value must be neutralised.
     */
    private function sanitize(mixed $value): string
    {
        $value = (string) $value;
        $value = str_replace([$this->delimiter, "\r\n", "\r", "\n"], [' ', ' ', ' ', ' '], $value);

        return trim($value);
    }

    public function formatDate(mixed $date): string
    {
        if (blank($date)) {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format((string) config('tfg.csv.date_format', 'Y-m-d'));
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $date)
                ->format((string) config('tfg.csv.date_format', 'Y-m-d'));
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    /**
     * Amounts use a plain number with no thousands separator, dropping a
     * pointless ".00" while keeping genuine fractional parts (e.g. 1234.50).
     */
    public function formatAmount(mixed $amount): string
    {
        if (blank($amount) && $amount !== 0 && $amount !== '0') {
            return '';
        }

        $float = (float) $amount;

        if (floor($float) === $float) {
            return (string) (int) round($float);
        }

        return rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
    }
}

<?php

namespace App\Services\Invoices;

use Carbon\Carbon;

class KsefCsvImporter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $rows = $this->parseCsvRows($content);
        if ($rows === []) {
            return [];
        }

        $header = array_map('trim', array_shift($rows));
        $groups = [];
        $currentKey = null;

        foreach ($rows as $row) {
            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = $row[$i] ?? '';
            }

            $ksef = trim($data['Numer KSeF'] ?? '');
            $numer = trim($data['numer'] ?? '');

            if ($numer !== '') {
                $currentKey = $numer;
            } elseif ($ksef !== '') {
                $currentKey = $ksef;
            }

            if ($currentKey === null) {
                continue;
            }

            if (! isset($groups[$currentKey])) {
                $groups[$currentKey] = [
                    'header' => $data,
                    'lines' => [],
                    'info' => [],
                ];
            } else {
                $groups[$currentKey]['header'] = $this->mergeHeader($groups[$currentKey]['header'], $data);
            }

            $product = trim($data['Produkt/usługa'] ?? '');
            if ($product !== '') {
                $groups[$currentKey]['lines'][] = [
                    'name' => $product,
                    'quantity' => $this->decimal($data['Ilość'] ?? '1'),
                    'unit' => trim($data['Jednostka'] ?? '') ?: null,
                    'vat_rate' => trim($data['Stawka VAT'] ?? '') ?: null,
                    'net_amount' => $this->decimal($data['Wartość netto'] ?? '0'),
                    'vat_amount' => $this->decimal($data['Wartość VAT'] ?? '0'),
                    'gross_amount' => $this->decimal($data['Wartość brutto'] ?? '0'),
                    'description' => trim($data['Wartość dodatkowego pola na pozycjach faktury'] ?? '') ?: null,
                ];
            }

            $infoType = trim($data['Rodzaj informacji'] ?? '');
            $infoContent = trim($data['Treść informacji'] ?? '');
            if ($infoContent !== '' && ($infoType === '' || stripos($infoType, 'Opis') !== false)) {
                $groups[$currentKey]['info'][] = $infoContent;
            }
        }

        return array_values(array_map(fn ($g) => $this->normalizeGroup($g), $groups));
    }

    /**
     * @param  array{header: array<string, string>, lines: array<int, array<string, mixed>>, info: array<int, string>}  $group
     * @return array<string, mixed>
     */
    private function normalizeGroup(array $group): array
    {
        $h = $group['header'];
        $status = trim($h['Status'] ?? '');
        $paymentStatus = match (mb_strtolower($status)) {
            'opłacona', 'oplacona' => 'paid',
            default => 'due',
        };

        $paidAmount = $this->decimal($h['Kwota opłacona'] ?? '0');
        if ($paymentStatus === 'paid' && $paidAmount <= 0) {
            $paidAmount = $this->decimal($h['Wartość brutto PLN'] ?? $h['Wartość brutto'] ?? '0');
        }

        return [
            'ksef_number' => trim($h['Numer KSeF'] ?? '') ?: null,
            'invoice_number' => trim($h['numer'] ?? '') ?: null,
            'issue_date' => $this->date($h['Data wystawienia'] ?? null),
            'sale_date' => $this->date($h['Data sprzedaży'] ?? null),
            'due_date' => $this->date($h['Termin płatności'] ?? null),
            'received_date' => $this->date($h['Data wpływu dokumentu'] ?? null),
            'payment_date' => $this->date($h['Data płatności'] ?? null),
            'currency' => trim($h['Waluta'] ?? 'PLN') ?: 'PLN',
            'net_amount' => $this->decimal($h['Wartość netto PLN'] ?? $h['Wartość netto'] ?? '0'),
            'vat_amount' => $this->decimal($h['VAT PLN'] ?? $h['VAT'] ?? '0'),
            'gross_amount' => $this->decimal($h['Wartość brutto PLN'] ?? $h['Wartość brutto'] ?? '0'),
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
            'payment_method' => trim($h['Płatność'] ?? '') ?: null,
            'seller_nip' => ContractorResolver::normalizeNip($h['NIP'] ?? null),
            'seller_name' => trim($h['Sprzedający'] ?? '') ?: null,
            'seller_street' => trim($h['Ulica i nr'] ?? '') ?: null,
            'seller_post_code' => trim($h['Kod pocztowy'] ?? '') ?: null,
            'seller_city' => trim($h['Miejscowość'] ?? '') ?: null,
            'seller_country' => trim($h['Kraj'] ?? 'PL') ?: 'PL',
            'seller_email' => trim($h['E-mail klienta'] ?? '') ?: null,
            'buyer_nip' => ContractorResolver::normalizeNip($h['NIP nabywcy'] ?? null),
            'buyer_name' => trim($h['Nabywca'] ?? '') ?: null,
            'notes' => $group['info'] !== [] ? implode("\n", $group['info']) : null,
            'lines' => $group['lines'],
            'raw_payload' => $h,
        ];
    }

    /**
     * @param  array<string, string>  $existing
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function mergeHeader(array $existing, array $row): array
    {
        foreach ($row as $key => $value) {
            if (trim((string) $value) !== '' && trim((string) ($existing[$key] ?? '')) === '') {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsvRows(string $content): array
    {
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function decimal(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', trim($value));
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

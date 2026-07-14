<?php

namespace App\Services\Invoices;

use App\Models\VendorInvoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class BulkPdfSplitter
{
    /**
     * @return array{attached: int, errors: array<int, string>}
     */
    public function splitAndAttach(string $pdfPath): array
    {
        if (! class_exists(Fpdi::class)) {
            throw new \RuntimeException('Biblioteka setasign/fpdi nie jest zainstalowana.');
        }

        $fullPath = Storage::disk(config('invoices.storage_disk', 'public'))->path($pdfPath);
        if (! is_file($fullPath)) {
            throw new \RuntimeException("Plik PDF nie istnieje: {$pdfPath}");
        }

        $fpdi = new Fpdi;
        $pageCount = $fpdi->setSourceFile($fullPath);
        $groups = $this->buildInvoiceGroups($fullPath, $pageCount);

        $attached = 0;
        $errors = [];
        $storagePath = config('invoices.pdf_storage_path', 'vendor-invoices');
        $disk = config('invoices.storage_disk', 'public');

        foreach ($groups as $index => $group) {
            try {
                $invoice = $this->findInvoice($group['ksef_number'], $group['invoice_number']);

                if (! $invoice) {
                    $errors[] = sprintf(
                        'Faktura %s: nie znaleziono rekordu (KSeF: %s)',
                        $group['invoice_number'] ?? ('grupa '.($index + 1)),
                        $group['ksef_number'] ?? '-',
                    );

                    continue;
                }

                $filename = ($invoice->ksef_number ?? Str::slug($invoice->invoice_number ?? 'faktura-'.$invoice->id)).'.pdf';
                $relativePath = $storagePath.'/'.$filename;
                $outputPath = Storage::disk($disk)->path($relativePath);

                if (! is_dir(dirname($outputPath))) {
                    mkdir(dirname($outputPath), 0755, true);
                }

                $this->writePagesToPdf($fullPath, $group['pages'], $outputPath);

                $invoice->update([
                    'pdf_path' => $relativePath,
                    'original_filename' => $filename,
                ]);
                $attached++;
            } catch (\Throwable $e) {
                $errors[] = ($group['invoice_number'] ?? 'grupa '.($index + 1)).': '.$e->getMessage();
            }
        }

        return ['attached' => $attached, 'errors' => $errors];
    }

    /**
     * @return array<int, array{pages: array<int, int>, invoice_number: ?string, ksef_number: ?string}>
     */
    private function buildInvoiceGroups(string $fullPath, int $pageCount): array
    {
        $groups = [];
        $current = null;

        for ($page = 1; $page <= $pageCount; $page++) {
            $text = $this->extractPageText($fullPath, $page);
            $invoiceNumber = $this->extractInvoiceNumberFromPage($text);
            $ksef = KsefNumberNormalizer::extractFromText($text);

            if ($invoiceNumber !== null) {
                if ($current !== null) {
                    $groups[] = $current;
                }

                $current = [
                    'pages' => [$page],
                    'invoice_number' => $invoiceNumber,
                    'ksef_number' => $ksef,
                ];

                continue;
            }

            if ($current === null) {
                $current = [
                    'pages' => [$page],
                    'invoice_number' => null,
                    'ksef_number' => $ksef,
                ];

                continue;
            }

            $current['pages'][] = $page;
            if ($ksef && ! $current['ksef_number']) {
                $current['ksef_number'] = $ksef;
            }
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param  array<int, int>  $pages
     */
    private function writePagesToPdf(string $sourcePath, array $pages, string $outputPath): void
    {
        $pdf = new Fpdi;
        $pdf->setSourceFile($sourcePath);

        foreach ($pages as $page) {
            $pdf->AddPage();
            $tpl = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
        }

        $pdf->Output('F', $outputPath);
    }

    private function findInvoice(?string $ksef, ?string $invoiceNumber): ?VendorInvoice
    {
        if ($ksef) {
            $normalized = KsefNumberNormalizer::normalize($ksef);
            $byKsef = VendorInvoice::query()->where('ksef_number', $normalized)->first();
            if ($byKsef) {
                return $byKsef;
            }

            $byKsef = VendorInvoice::query()
                ->whereNotNull('ksef_number')
                ->get()
                ->first(fn (VendorInvoice $invoice) => KsefNumberNormalizer::normalize($invoice->ksef_number) === $normalized);

            if ($byKsef) {
                return $byKsef;
            }
        }

        if ($invoiceNumber) {
            $normalizedNumber = $this->normalizeInvoiceNumber($invoiceNumber);

            $byNumber = VendorInvoice::query()
                ->where('invoice_number', $invoiceNumber)
                ->orWhere('invoice_number', $normalizedNumber)
                ->first();

            if ($byNumber) {
                return $byNumber;
            }

            return VendorInvoice::query()
                ->whereNotNull('invoice_number')
                ->get()
                ->first(fn (VendorInvoice $invoice) => $this->normalizeInvoiceNumber($invoice->invoice_number ?? '') === $normalizedNumber);
        }

        return null;
    }

    private function extractInvoiceNumberFromPage(string $text): ?string
    {
        if (preg_match('/Faktura\s+numer\s+([^\n\r]+)/iu', $text, $matches)) {
            $number = trim($matches[1]);
            $number = preg_replace('/\s{2,}.*/', '', $number);

            return $number !== '' ? $number : null;
        }

        return null;
    }

    private function normalizeInvoiceNumber(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function extractPageText(string $pdfPath, int $page): string
    {
        if (function_exists('shell_exec') && $this->commandExists('pdftotext')) {
            $cmd = sprintf(
                'pdftotext -f %d -l %d %s - 2>/dev/null',
                $page,
                $page,
                escapeshellarg($pdfPath)
            );
            $output = shell_exec($cmd);

            return $output ?? '';
        }

        return '';
    }

    private function commandExists(string $command): bool
    {
        $result = shell_exec(sprintf('which %s 2>/dev/null', escapeshellarg($command)));

        return $result !== null && trim($result) !== '';
    }
}

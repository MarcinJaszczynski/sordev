<?php

namespace App\Services\Invoices;

use Illuminate\Support\Facades\Storage;

class VendorInvoiceBatchImportService
{
    public function __construct(
        private readonly VendorInvoiceImportService $importService = new VendorInvoiceImportService,
        private readonly BulkPdfSplitter $pdfSplitter = new BulkPdfSplitter,
    ) {}

    /**
     * @return array{
     *     imported: int,
     *     matched: int,
     *     unmatched: int,
     *     pdf_attached: int,
     *     errors: array<int, string>
     * }
     */
    public function import(
        ?string $csvPath = null,
        ?string $xmlPath = null,
        ?string $pdfPath = null,
        ?string $csvName = null,
        ?string $xmlName = null,
        ?string $pdfName = null,
    ): array {
        $result = [
            'imported' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'pdf_attached' => 0,
            'errors' => [],
        ];

        if ($csvPath && is_file($csvPath)) {
            $this->merge($result, $this->importService->importFromContent(
                file_get_contents($csvPath),
                'csv',
                $csvName ?? basename($csvPath),
            ));
        }

        if ($xmlPath && is_file($xmlPath)) {
            $this->merge($result, $this->importService->importFromContent(
                file_get_contents($xmlPath),
                'xml',
                $xmlName ?? basename($xmlPath),
            ));
        }

        if ($pdfPath && is_file($pdfPath)) {
            $publicPath = config('invoices.pdf_storage_path', 'vendor-invoices').'/bulk-'.time().'-'.($pdfName ?? basename($pdfPath));
            Storage::disk(config('invoices.storage_disk', 'public'))->put(
                $publicPath,
                file_get_contents($pdfPath)
            );

            $pdfResult = $this->pdfSplitter->splitAndAttach($publicPath);
            $result['pdf_attached'] += $pdfResult['attached'];
            $result['errors'] = array_merge($result['errors'], $pdfResult['errors']);
        }

        return $result;
    }

    /**
     * @param  array{imported: int, matched: int, unmatched: int, errors: array<int, string>}  $source
     * @param  array{imported: int, matched: int, unmatched: int, pdf_attached: int, errors: array<int, string>}  $target
     */
    private function merge(array &$target, array $source): void
    {
        $target['imported'] += $source['imported'] ?? 0;
        $target['matched'] += $source['matched'] ?? 0;
        $target['unmatched'] += $source['unmatched'] ?? 0;
        $target['errors'] = array_merge($target['errors'], $source['errors'] ?? []);
    }
}

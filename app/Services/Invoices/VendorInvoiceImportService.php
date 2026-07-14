<?php

namespace App\Services\Invoices;

use App\Models\VendorInvoice;
use App\Models\VendorInvoiceImportBatch;
use App\Models\VendorInvoiceLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorInvoiceImportService
{
    public function __construct(
        private readonly KsefCsvImporter $csvImporter = new KsefCsvImporter,
        private readonly KsefXmlImporter $xmlImporter = new KsefXmlImporter,
        private readonly VendorInvoiceMatcher $matcher = new VendorInvoiceMatcher,
    ) {}

    /**
     * @return array{imported: int, matched: int, unmatched: int, errors: array<int, string>}
     */
    public function importFromContent(string $content, string $sourceType, ?string $filename = null): array
    {
        $parsed = match ($sourceType) {
            'csv' => $this->csvImporter->parse($content),
            'xml' => $this->xmlImporter->parse($content),
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };

        $batch = VendorInvoiceImportBatch::create([
            'user_id' => Auth::id(),
            'source_type' => $sourceType,
            'status' => 'processing',
            'source_filename' => $filename,
        ]);

        $imported = 0;
        $matched = 0;
        $unmatched = 0;
        $errors = [];

        foreach ($parsed as $index => $data) {
            try {
                $invoice = $this->upsertInvoice($data, $sourceType, $batch->id);
                if ($invoice->matching_status !== 'manual') {
                    $invoice = $this->matcher->match($invoice);
                }
                $imported++;
                if ($invoice->matching_status === 'auto_matched') {
                    $matched++;
                } else {
                    $unmatched++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Wiersz '.($index + 1).': '.$e->getMessage();
            }
        }

        $batch->update([
            'status' => $errors === [] ? 'completed' : ($imported > 0 ? 'completed' : 'failed'),
            'imported_count' => $imported,
            'matched_count' => $matched,
            'unmatched_count' => $unmatched,
            'error_count' => count($errors),
            'error_log' => $errors !== [] ? $errors : null,
            'completed_at' => now(),
        ]);

        return [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertInvoice(array $data, string $source, int $batchId): VendorInvoice
    {
        return DB::transaction(function () use ($data, $source, $batchId) {
            $agencyNip = ContractorResolver::normalizeNip(config('invoices.agency_nip'));
            $buyerNip = $data['buyer_nip'] ?? null;
            if ($buyerNip && $agencyNip && $buyerNip !== $agencyNip) {
                // CSV/XML may have buyer as agency — still import, flag for review
            }

            $attributes = [
                'import_batch_id' => $batchId,
                'source' => $source,
                'invoice_number' => $data['invoice_number'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'sale_date' => $data['sale_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'received_date' => $data['received_date'] ?? null,
                'payment_date' => $data['payment_date'] ?? null,
                'currency' => $data['currency'] ?? 'PLN',
                'net_amount' => $data['net_amount'] ?? 0,
                'vat_amount' => $data['vat_amount'] ?? 0,
                'gross_amount' => $data['gross_amount'] ?? 0,
                'paid_amount' => $data['paid_amount'] ?? 0,
                'payment_status' => $data['payment_status'] ?? 'due',
                'payment_method' => $data['payment_method'] ?? null,
                'seller_nip' => $data['seller_nip'] ?? null,
                'seller_name' => $data['seller_name'] ?? null,
                'seller_street' => $data['seller_street'] ?? null,
                'seller_post_code' => $data['seller_post_code'] ?? null,
                'seller_city' => $data['seller_city'] ?? null,
                'seller_country' => $data['seller_country'] ?? null,
                'seller_email' => $data['seller_email'] ?? null,
                'buyer_nip' => $data['buyer_nip'] ?? null,
                'buyer_name' => $data['buyer_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'raw_payload' => $data['raw_payload'] ?? null,
                'approval_status' => 'pending',
                'matching_status' => 'unmatched',
            ];

            $ksef = $data['ksef_number'] ?? null;
            $existing = null;

            if ($ksef) {
                $existing = VendorInvoice::query()->where('ksef_number', $ksef)->first();
            } elseif (! empty($data['invoice_number']) && ! empty($data['seller_nip'])) {
                $existing = VendorInvoice::query()
                    ->where('invoice_number', $data['invoice_number'])
                    ->where('seller_nip', $data['seller_nip'])
                    ->first();
            }

            if ($existing?->matching_status === 'manual') {
                unset($attributes['matching_status']);
            }

            if ($ksef) {
                $invoice = VendorInvoice::updateOrCreate(
                    ['ksef_number' => $ksef],
                    $attributes
                );
            } elseif (! empty($data['invoice_number']) && ! empty($data['seller_nip'])) {
                $invoice = VendorInvoice::updateOrCreate(
                    [
                        'invoice_number' => $data['invoice_number'],
                        'seller_nip' => $data['seller_nip'],
                    ],
                    array_merge($attributes, ['ksef_number' => null])
                );
            } else {
                $invoice = VendorInvoice::create(array_merge($attributes, ['ksef_number' => null]));
            }

            $invoice->lines()->delete();

            $lineOrder = 0;
            foreach ($data['lines'] ?? [] as $line) {
                VendorInvoiceLine::create([
                    'vendor_invoice_id' => $invoice->id,
                    'line_order' => $line['line_order'] ?? $lineOrder++,
                    'name' => $line['name'] ?? 'Pozycja',
                    'quantity' => $line['quantity'] ?? 1,
                    'unit' => $line['unit'] ?? null,
                    'vat_rate' => $line['vat_rate'] ?? null,
                    'net_amount' => $line['net_amount'] ?? 0,
                    'vat_amount' => $line['vat_amount'] ?? 0,
                    'gross_amount' => $line['gross_amount'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $invoice->fresh('lines');
        });
    }
}

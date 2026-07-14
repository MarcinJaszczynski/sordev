<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Models\VendorInvoice;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class EventAccountingExportService
{
    /**
     * @return array{path: string, filename: string}
     */
    public function buildArchive(Event $event): array
    {
        $event->loadMissing(['agreements']);

        $tempDir = storage_path('app/temp/accounting-'.$event->id.'-'.Str::random(8));
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $manifestRows = [];
        $this->exportVendorInvoices($event, $tempDir, $manifestRows);
        $this->exportAgreementsCsv($event, $tempDir, $manifestRows);
        $this->exportSettlementDocuments($event, $tempDir, $manifestRows);
        $this->writeManifest($tempDir, $manifestRows);

        $zipPath = $tempDir.'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Nie udało się utworzyć archiwum ZIP.');
        }

        foreach (glob($tempDir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
            }
        }

        $zip->close();

        $filename = sprintf('teczka-ksiegowa-%s-%s.zip', $event->code ?: $event->id, now()->format('Ymd'));

        return [
            'path' => $zipPath,
            'filename' => $filename,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $manifestRows
     */
    protected function exportVendorInvoices(Event $event, string $tempDir, array &$manifestRows): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return;
        }

        $invoices = VendorInvoice::query()
            ->where('event_id', $event->id)
            ->orderBy('issue_date')
            ->get();

        $disk = config('invoices.storage_disk', 'public');

        foreach ($invoices as $invoice) {
            $manifestRows[] = [
                'type' => 'Faktura kosztowa',
                'reference' => $invoice->invoice_number ?: $invoice->ksef_number,
                'counterparty' => $invoice->seller_name,
                'amount' => number_format((float) $invoice->gross_amount, 2, ',', ' '),
                'date' => $invoice->issue_date?->format('d.m.Y') ?? '',
            ];

            if (! $invoice->pdf_path || ! Storage::disk($disk)->exists($invoice->pdf_path)) {
                continue;
            }

            $safeName = Str::slug($invoice->invoice_number ?: ('faktura-'.$invoice->id)).'.pdf';
            copy(
                Storage::disk($disk)->path($invoice->pdf_path),
                $tempDir.'/faktura-'.$safeName,
            );
        }
    }

    /**
     * @param  array<int, array<string, string>>  $manifestRows
     */
    protected function exportAgreementsCsv(Event $event, string $tempDir, array &$manifestRows): void
    {
        $rows = [['Typ', 'Numer', 'Kontrahent', 'Kwota', 'Wpłacono', 'Status']];

        if (Schema::hasTable('contracts')) {
            foreach (Contract::query()->where('event_id', $event->id)->orderBy('id')->get() as $contract) {
                $rows[] = [
                    'Umowa',
                    $contract->contract_number ?: ('#'.$contract->id),
                    $contract->customer_name,
                    number_format((float) $contract->total_price, 2, ',', ' '),
                    number_format((float) $contract->amount_paid, 2, ',', ' '),
                    $contract->payment_status,
                ];
                $manifestRows[] = [
                    'type' => 'Umowa',
                    'reference' => $contract->contract_number ?: ('#'.$contract->id),
                    'counterparty' => $contract->customer_name,
                    'amount' => number_format((float) $contract->total_price, 2, ',', ' '),
                    'date' => $contract->contract_date?->format('d.m.Y') ?? '',
                ];
            }
        }

        if (Schema::hasTable('event_agreements')) {
            foreach ($event->agreements()->orderBy('id')->get() as $agreement) {
                $rows[] = [
                    'Umowa legacy',
                    $agreement->agreement_number ?: ('#'.$agreement->id),
                    $agreement->customer_name,
                    number_format((float) $agreement->amount_due, 2, ',', ' '),
                    number_format((float) $agreement->amount_paid, 2, ',', ' '),
                    $agreement->payment_status,
                ];
            }
        }

        $handle = fopen($tempDir.'/przychody-umowy.csv', 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    /**
     * @param  array<int, array<string, string>>  $manifestRows
     */
    protected function exportSettlementDocuments(Event $event, string $tempDir, array &$manifestRows): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return;
        }

        $settlement = EventSettlement::query()
            ->where('event_id', $event->id)
            ->latest('id')
            ->first();

        if (! $settlement) {
            return;
        }

        $documents = EventSettlementDocument::query()
            ->where('settlement_id', $settlement->id)
            ->orderBy('id')
            ->get();

        foreach ($documents as $document) {
            $manifestRows[] = [
                'type' => 'Dokument rozliczenia',
                'reference' => $document->document_number ?: ('#'.$document->id),
                'counterparty' => $document->vendor_name,
                'amount' => number_format((float) $document->total_amount, 2, ',', ' '),
                'date' => $document->document_date?->format('d.m.Y') ?? '',
            ];

            if (! $document->file_path || ! Storage::disk('public')->exists($document->file_path)) {
                continue;
            }

            $extension = pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'bin';
            copy(
                Storage::disk('public')->path($document->file_path),
                $tempDir.'/rozliczenie-'.$document->id.'.'.$extension,
            );
        }
    }

    /**
     * @param  array<int, array<string, string>>  $manifestRows
     */
    protected function writeManifest(string $tempDir, array $manifestRows): void
    {
        $handle = fopen($tempDir.'/manifest.csv', 'w');
        fputcsv($handle, ['Typ', 'Referencja', 'Kontrahent', 'Kwota', 'Data']);

        foreach ($manifestRows as $row) {
            fputcsv($handle, [
                $row['type'] ?? '',
                $row['reference'] ?? '',
                $row['counterparty'] ?? '',
                $row['amount'] ?? '',
                $row['date'] ?? '',
            ]);
        }

        fclose($handle);
    }
}

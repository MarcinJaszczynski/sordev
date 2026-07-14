<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Models\VendorInvoice;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class EventInvoicePdfMergeService
{
    /**
     * @return array{path: string, filename: string, count: int}
     */
    public function mergeToTempFile(Event $event): array
    {
        $entries = $this->collectInvoicePdfFiles($event);

        if ($entries === []) {
            throw new \RuntimeException('Brak plików PDF faktur dla tej imprezy.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'event_invoices_');

        if ($tempPath === false) {
            throw new \RuntimeException('Nie udało się utworzyć pliku tymczasowego.');
        }

        @unlink($tempPath);
        $outputPath = $tempPath.'.pdf';

        $this->mergePdfFiles(array_column($entries, 'path'), $outputPath);

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);

            throw new \RuntimeException('Nie udało się połączyć plików PDF faktur.');
        }

        return [
            'path' => $outputPath,
            'filename' => $this->buildFilename($event),
            'count' => count($entries),
        ];
    }

    /**
     * @return array<int, array{path: string, label: string, sort_key: string}>
     */
    public function collectInvoicePdfFiles(Event $event): array
    {
        $entries = [];
        $seenPaths = [];

        $this->collectVendorInvoicePdfs($event, $entries, $seenPaths);
        $this->collectSettlementInvoicePdfs($event, $entries, $seenPaths);
        $this->collectEventDocumentInvoicePdfs($event, $entries, $seenPaths);

        usort($entries, fn (array $left, array $right): int => strcmp($left['sort_key'], $right['sort_key']));

        return $entries;
    }

    /**
     * @param  array<int, string>  $absolutePaths
     */
    public function mergePdfFiles(array $absolutePaths, string $outputPath): void
    {
        if (! class_exists(Fpdi::class)) {
            throw new \RuntimeException('Biblioteka setasign/fpdi nie jest zainstalowana.');
        }

        $pdf = new Fpdi;
        $mergedPages = 0;

        foreach ($absolutePaths as $sourcePath) {
            if (! is_file($sourcePath) || ! $this->isPdfFile($sourcePath)) {
                continue;
            }

            try {
                $pageCount = $pdf->setSourceFile($sourcePath);
            } catch (\Throwable) {
                continue;
            }

            for ($page = 1; $page <= $pageCount; $page++) {
                $pdf->AddPage();
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
                $mergedPages++;
            }
        }

        if ($mergedPages === 0) {
            throw new \RuntimeException('Żaden z plików PDF nie mógł zostać dołączony do zbiorczego pliku.');
        }

        $pdf->Output('F', $outputPath);
    }

    /**
     * @param  array<int, array{path: string, label: string, sort_key: string}>  $entries
     * @param  array<string, true>  $seenPaths
     */
    private function collectVendorInvoicePdfs(Event $event, array &$entries, array &$seenPaths): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return;
        }

        $disk = config('invoices.storage_disk', 'public');

        VendorInvoice::query()
            ->where('event_id', $event->id)
            ->whereNotNull('pdf_path')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->each(function (VendorInvoice $invoice) use (&$entries, &$seenPaths, $disk): void {
                $relativePath = StoragePath::normalize($invoice->pdf_path);

                if (! $relativePath || ! Storage::disk($disk)->exists($relativePath)) {
                    return;
                }

                $absolutePath = Storage::disk($disk)->path($relativePath);

                if (! $this->registerPdfEntry(
                    $entries,
                    $seenPaths,
                    $absolutePath,
                    $invoice->invoice_number ?: $invoice->ksef_number ?: ('Faktura #'.$invoice->id),
                    ($invoice->issue_date?->format('Y-m-d') ?? '9999-12-31').'-ksef-'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT),
                )) {
                    return;
                }
            });
    }

    /**
     * @param  array<int, array{path: string, label: string, sort_key: string}>  $entries
     * @param  array<string, true>  $seenPaths
     */
    private function collectSettlementInvoicePdfs(Event $event, array &$entries, array &$seenPaths): void
    {
        if (! Schema::hasTable('event_settlement_documents') || ! Schema::hasTable('event_settlements')) {
            return;
        }

        $settlement = EventSettlement::query()
            ->where('event_id', $event->id)
            ->latest('id')
            ->first();

        if (! $settlement) {
            return;
        }

        EventSettlementDocument::query()
            ->where('settlement_id', $settlement->id)
            ->where('document_type', 'invoice')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->each(function (EventSettlementDocument $document) use (&$entries, &$seenPaths): void {
                foreach ((array) ($document->files ?? []) as $index => $file) {
                    $relativePath = StoragePath::normalize(is_string($file) ? $file : null);

                    if (! $relativePath || ! Storage::disk('public')->exists($relativePath)) {
                        continue;
                    }

                    $absolutePath = Storage::disk('public')->path($relativePath);
                    $label = $document->document_number ?: ('FV rozliczenie #'.$document->id);

                    $this->registerPdfEntry(
                        $entries,
                        $seenPaths,
                        $absolutePath,
                        $label,
                        ($document->issue_date?->format('Y-m-d') ?? '9999-12-31')
                            .'-settlement-'.str_pad((string) $document->id, 8, '0', STR_PAD_LEFT)
                            .'-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                    );
                }
            });
    }

    /**
     * @param  array<int, array{path: string, label: string, sort_key: string}>  $entries
     * @param  array<string, true>  $seenPaths
     */
    private function collectEventDocumentInvoicePdfs(Event $event, array &$entries, array &$seenPaths): void
    {
        if (! Schema::hasTable('event_documents') || ! Schema::hasColumn('event_documents', 'is_invoice')) {
            return;
        }

        EventDocument::query()
            ->where('event_id', $event->id)
            ->where('is_invoice', true)
            ->whereNotNull('file_path')
            ->orderBy('created_at')
            ->orderBy('id')
            ->each(function (EventDocument $document) use (&$entries, &$seenPaths): void {
                $relativePath = StoragePath::normalize($document->file_path);

                if (! $relativePath || ! Storage::disk('public')->exists($relativePath)) {
                    return;
                }

                $absolutePath = Storage::disk('public')->path($relativePath);

                $this->registerPdfEntry(
                    $entries,
                    $seenPaths,
                    $absolutePath,
                    $document->name ?: ('Dokument #'.$document->id),
                    ($document->created_at?->format('Y-m-d') ?? '9999-12-31')
                        .'-eventdoc-'.str_pad((string) $document->id, 8, '0', STR_PAD_LEFT),
                );
            });
    }

    /**
     * @param  array<int, array{path: string, label: string, sort_key: string}>  $entries
     * @param  array<string, true>  $seenPaths
     */
    private function registerPdfEntry(
        array &$entries,
        array &$seenPaths,
        string $absolutePath,
        string $label,
        string $sortKey,
    ): bool {
        $normalizedAbsolutePath = realpath($absolutePath) ?: $absolutePath;

        if (isset($seenPaths[$normalizedAbsolutePath])) {
            return false;
        }

        if (! $this->isPdfFile($normalizedAbsolutePath)) {
            return false;
        }

        $seenPaths[$normalizedAbsolutePath] = true;
        $entries[] = [
            'path' => $normalizedAbsolutePath,
            'label' => $label,
            'sort_key' => $sortKey,
        ];

        return true;
    }

    private function isPdfFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'pdf') {
            return true;
        }

        $mime = mime_content_type($absolutePath);

        return $mime === 'application/pdf';
    }

    private function buildFilename(Event $event): string
    {
        $slug = Str::slug($event->code ?: ('impreza-'.$event->id)) ?: 'impreza';

        return sprintf('faktury-%s-%s.pdf', $slug, now()->format('Ymd'));
    }
}

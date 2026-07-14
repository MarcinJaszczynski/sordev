<?php

namespace App\Support;

use App\Models\EventProgramPoint;
use Illuminate\Support\Collection;

final class EventProgramPointPaymentDueColumn
{
    /**
     * @param  Collection<int, array<string, mixed>>  $scheduleRows
     */
    public static function html(EventProgramPoint $point, Collection $scheduleRows): string
    {
        if ($scheduleRows->isEmpty()) {
            return '<span style="color:#9ca3af;font-size:0.78rem">—</span>';
        }

        $paymentRows = $scheduleRows->where('kind', '!=', 'vendor_invoice')->values();
        $invoiceRows = $scheduleRows->where('kind', 'vendor_invoice')->values();
        $sections = [];

        if ($paymentRows->isNotEmpty()) {
            $sections[] = self::renderPaymentRows($paymentRows);
        }

        if ($invoiceRows->isNotEmpty()) {
            $sections[] = self::renderInvoiceRows($invoiceRows);
        }

        return '<div style="display:flex;flex-direction:column;gap:4px">'.implode('', $sections).'</div>';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paymentRows
     */
    private static function renderPaymentRows(Collection $paymentRows): string
    {
        $visible = $paymentRows->take(3);
        $remaining = max(0, $paymentRows->count() - $visible->count());
        $lines = [];

        foreach ($visible as $index => $row) {
            $date = \Carbon\Carbon::parse($row['due_date'])->format('d.m.Y');
            $color = ($row['is_overdue'] ?? false) ? '#dc2626' : '#111827';
            $weight = $index === 0 ? '700' : '600';
            $kind = e($row['kind_label'] ?? 'Płatność');
            $title = e($row['title'] ?? '');
            $amount = e($row['amount_label'] ?? '');

            $lines[] = '<div style="font-size:0.76rem;line-height:1.35;color:'.$color.';font-weight:'.$weight.'">'
                .$date.' · '.$kind.' · '.$title.' · '.$amount
                .'</div>';
        }

        if ($remaining > 0) {
            $lines[] = '<div style="font-size:0.72rem;color:#6b7280;margin-top:2px">+'
                .$remaining.' kolejn'.($remaining === 1 ? 'y' : ($remaining < 5 ? 'e' : 'ych'))
                .'</div>';
        }

        return '<div style="display:flex;flex-direction:column;gap:2px">'.implode('', $lines).'</div>';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $invoiceRows
     */
    private static function renderInvoiceRows(Collection $invoiceRows): string
    {
        $lines = [];

        foreach ($invoiceRows->take(3) as $row) {
            $number = e($row['invoice_number'] ?? $row['title'] ?? 'Faktura');
            $dueDate = \Carbon\Carbon::parse($row['due_date'])->format('d.m.Y');
            $amount = e($row['amount_label'] ?? '');
            $color = ($row['is_overdue'] ?? false) ? '#dc2626' : '#92400e';
            $pdfLabel = ($row['has_pdf'] ?? false)
                ? '<a href="'.e((string) ($row['pdf_url'] ?? $row['url'] ?? '#')).'" style="color:#047857;text-decoration:underline" x-on:click.stop>PDF ✓</a>'
                : '<span style="color:#9ca3af">brak pliku</span>';

            $lines[] = '<div style="font-size:0.74rem;line-height:1.35;color:'.$color.'">'
                .'Faktura '.$number.' · termin '.$dueDate.' · '.$amount.' · '.$pdfLabel
                .'</div>';
        }

        $remaining = max(0, $invoiceRows->count() - 3);

        if ($remaining > 0) {
            $lines[] = '<div style="font-size:0.72rem;color:#6b7280">+'
                .$remaining.' faktur'.($remaining === 1 ? 'a' : '')
                .'</div>';
        }

        return '<div style="display:flex;flex-direction:column;gap:2px;margin-top:2px">'.implode('', $lines).'</div>';
    }
}

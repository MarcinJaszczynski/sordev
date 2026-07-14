<?php

namespace App\Services\Invoices;

use App\Models\Currency;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Models\VendorInvoice;
use Illuminate\Support\Facades\Auth;

class VendorInvoiceSettlementSync
{
    public function sync(VendorInvoice $invoice): ?EventSettlementDocument
    {
        if (! $invoice->event_id || ! $invoice->sync_to_settlement) {
            return null;
        }

        if ($invoice->settlement_document_id) {
            return EventSettlementDocument::query()->find($invoice->settlement_document_id);
        }

        $settlement = EventSettlement::query()
            ->where('event_id', $invoice->event_id)
            ->orderByDesc('id')
            ->first();

        if (! $settlement) {
            $settlement = EventSettlement::create([
                'event_id' => $invoice->event_id,
                'status' => 'draft',
            ]);
        }

        $currencyId = Currency::query()->where('code', $invoice->currency ?? 'PLN')->value('id');

        $files = [];
        if ($invoice->pdf_path) {
            $files[] = [
                'path' => $invoice->pdf_path,
                'name' => $invoice->original_filename ?? basename($invoice->pdf_path),
            ];
        }

        $linkedCostIds = [];
        if ($invoice->event_settlement_cost_id) {
            $linkedCostIds[] = $invoice->event_settlement_cost_id;
        }

        $document = EventSettlementDocument::create([
            'settlement_id' => $settlement->id,
            'document_type' => 'invoice',
            'document_number' => $invoice->invoice_number ?? $invoice->ksef_number,
            'vendor_name' => $invoice->seller_name,
            'total_amount' => $invoice->gross_amount,
            'currency_id' => $currencyId,
            'issue_date' => $invoice->issue_date,
            'payment_date' => $invoice->payment_date,
            'payment_method' => $invoice->payment_method,
            'linked_cost_ids' => $linkedCostIds !== [] ? $linkedCostIds : null,
            'files' => $files !== [] ? $files : null,
            'notes' => $invoice->internal_notes ?? $invoice->notes,
            'approval_status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'created_by' => Auth::id(),
        ]);

        $invoice->update(['settlement_document_id' => $document->id]);

        return $document;
    }
}

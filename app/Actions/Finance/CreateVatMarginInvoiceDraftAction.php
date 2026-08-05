<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\CreateVatMarginInvoiceDraftData;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Support\EventMarginPlanVsActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class CreateVatMarginInvoiceDraftAction
{
    public function __invoke(CreateVatMarginInvoiceDraftData $data): SalesInvoice
    {
        if (! Schema::hasTable('sales_invoices')) {
            throw new InvalidArgumentException('Tabela faktur sprzedażowych nie istnieje. Uruchom migracje.');
        }

        return DB::transaction(function () use ($data): SalesInvoice {
            $margin = EventMarginPlanVsActual::forEvent($data->event);
            $revenue = (float) ($margin['actual_revenue'] > 0 ? $margin['actual_revenue'] : $margin['planned_revenue']);
            $cost = (float) ($margin['actual_cost'] > 0 ? $margin['actual_cost'] : $margin['planned_cost']);
            $marginGross = round($revenue - $cost, 2);
            // Procedura VAT-Marża: VAT = marża brutto * 23/123, netto marży = brutto - VAT
            $vatOnMargin = $marginGross > 0 ? round($marginGross * 23 / 123, 2) : 0.0;
            $marginNet = round($marginGross - $vatOnMargin, 2);

            $request = $data->request;
            $buyerName = $data->buyerName
                ?? $request?->company_name
                ?? $data->event->client_name;
            $buyerNip = $data->buyerNip ?? $request?->nip;
            $buyerAddress = $data->buyerAddress
                ?? ($request ? $request->full_address : null);

            $invoice = SalesInvoice::query()->create([
                'event_id' => $data->event->id,
                'client_invoice_request_id' => $request?->id,
                'number' => null,
                'type' => in_array($data->type, array_keys(SalesInvoice::$types), true)
                    ? $data->type
                    : SalesInvoice::TYPE_FINAL,
                'procedure' => SalesInvoice::PROCEDURE_VAT_MARGIN,
                'status' => SalesInvoice::STATUS_DRAFT,
                'buyer_name' => $buyerName,
                'buyer_nip' => $buyerNip,
                'buyer_address' => $buyerAddress,
                'revenue_pln' => $revenue,
                'cost_pln' => $cost,
                'margin_gross_pln' => $marginGross,
                'margin_net_pln' => $marginNet,
                'vat_on_margin_pln' => $vatOnMargin,
                'currency' => 'PLN',
                'created_by' => $data->createdBy,
                'notes' => 'Szkic FV VAT-Marża (bez wysyłki KSeF).',
            ]);

            SalesInvoiceLine::query()->create([
                'sales_invoice_id' => $invoice->id,
                'name' => 'Usługa turystyczna — procedura VAT marża: '.($data->event->name ?? ''),
                'quantity' => 1,
                'unit_price_pln' => $revenue,
                'total_pln' => $revenue,
                'sort_order' => 0,
            ]);

            return $invoice->load('lines');
        });
    }
}

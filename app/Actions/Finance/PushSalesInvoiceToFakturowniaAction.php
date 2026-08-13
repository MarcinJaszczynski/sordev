<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\SalesInvoice;
use App\Services\Invoices\FakturowniaClient;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Wystawia fakturę VAT-marża w Fakturowni na podstawie lokalnego SalesInvoice.
 */
final class PushSalesInvoiceToFakturowniaAction
{
    public function __construct(
        private readonly FakturowniaClient $client,
    ) {}

    public function __invoke(SalesInvoice $invoice): SalesInvoice
    {
        if (! $this->client->enabled()) {
            return $invoice;
        }

        $invoice->loadMissing(['event', 'lines', 'clientInvoiceRequest']);

        if (filled($invoice->fakturownia_id ?? null)) {
            return $invoice;
        }

        $event = $invoice->event;
        $request = $invoice->clientInvoiceRequest;

        $buyerName = (string) ($invoice->buyer_name ?: $event?->client_name ?: 'Nabywca');
        $buyerTaxNo = filled($invoice->buyer_nip) ? preg_replace('/\s+/', '', (string) $invoice->buyer_nip) : null;

        $positions = [];
        foreach ($invoice->lines as $line) {
            $positions[] = [
                'name' => (string) ($line->name ?: 'Usługa turystyczna'),
                'tax' => 'disabled',
                'total_price_gross' => (float) ($line->total_pln ?? $invoice->revenue_pln ?? 0),
                'quantity' => (float) ($line->quantity ?? 1),
                'quantity_unit' => 'szt',
                'vat_margin_tax' => '23',
                'vat_margin_price_net' => (float) ($invoice->cost_pln ?? 0),
            ];
        }

        if ($positions === []) {
            $positions[] = [
                'name' => 'Usługa turystyczna — procedura VAT marża: '.($event?->name ?? ''),
                'tax' => 'disabled',
                'total_price_gross' => (float) ($invoice->revenue_pln ?? 0),
                'quantity' => 1,
                'quantity_unit' => 'szt',
                'vat_margin_tax' => '23',
                'vat_margin_price_net' => (float) ($invoice->cost_pln ?? 0),
            ];
        }

        $kind = match ($invoice->type) {
            SalesInvoice::TYPE_PROFORMA => 'proforma',
            SalesInvoice::TYPE_ADVANCE => 'advance',
            default => 'vat_margin',
        };

        $payload = [
            'kind' => $kind,
            'number' => null,
            'sell_date' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'payment_to' => now()->addDays(7)->toDateString(),
            'buyer_name' => $buyerName,
            'buyer_tax_no' => $buyerTaxNo,
            'buyer_email' => $request?->invoice_email,
            'buyer_street' => $request?->street,
            'buyer_street_no' => $request?->house_number,
            'buyer_post_code' => $request?->postal_code,
            'buyer_city' => $request?->city,
            'buyer_phone' => $request?->applicant_phone,
            'description' => 'Impreza '.($event?->code ?: '#'.($event?->id ?? '')).' — '.($event?->name ?? ''),
            'procedure_vat_margin' => 'procedura marży dla biur podróży',
            'positions' => $positions,
        ];

        $sellerTax = config('invoices.agency_nip') ?: config('services.fakturownia.seller_tax_no');
        if (filled($sellerTax)) {
            $payload['seller_tax_no'] = preg_replace('/\s+/', '', (string) $sellerTax);
        }

        $response = $this->client->createInvoice($payload);
        $remoteId = (int) ($response['id'] ?? 0);
        if ($remoteId <= 0) {
            throw new RuntimeException('Fakturownia nie zwróciła ID faktury.');
        }

        $number = $response['number'] ?? $response['full_number'] ?? null;
        $url = $this->client->invoiceViewUrl($remoteId);

        $update = [
            'number' => $number ?: $invoice->number,
            'status' => SalesInvoice::STATUS_ISSUED_LOCAL,
            'notes' => trim((string) ($invoice->notes ?? '')."\nFakturownia #{$remoteId}: {$url}"),
        ];

        if (Schema::hasColumn('sales_invoices', 'fakturownia_id')) {
            $update['fakturownia_id'] = $remoteId;
            $update['fakturownia_url'] = $url;
            $update['fakturownia_synced_at'] = now();
        }

        $invoice->update($update);

        return $invoice->fresh(['lines', 'event', 'clientInvoiceRequest']);
    }
}

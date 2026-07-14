<?php

namespace App\Services\Invoices;

use Carbon\Carbon;
use SimpleXMLElement;

class KsefXmlImporter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $xml = new SimpleXMLElement($content);
        $invoices = [];

        foreach ($xml->invoice as $invoiceNode) {
            $invoices[] = $this->parseInvoice($invoiceNode);
        }

        return $invoices;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseInvoice(SimpleXMLElement $node): array
    {
        $status = (string) ($node->status ?? '');
        $paymentStatus = match ($status) {
            'paid' => 'paid',
            default => 'due',
        };

        $paid = $this->decimal($node->paid ?? '0');
        $gross = $this->decimal($node->{'price-gross'} ?? '0');
        if ($paymentStatus === 'paid' && $paid <= 0) {
            $paid = $gross;
        }

        $lines = [];
        $lineOrder = 0;
        if (isset($node->positions->position)) {
            foreach ($node->positions->position as $pos) {
                $lines[] = [
                    'line_order' => $lineOrder++,
                    'name' => (string) ($pos->name ?? 'Pozycja'),
                    'quantity' => $this->decimal($pos->quantity ?? '1'),
                    'unit' => $this->nilSafe($pos->{'quantity-unit'}),
                    'vat_rate' => $this->nilSafe($pos->tax),
                    'net_amount' => $this->decimal($pos->{'total-price-net'} ?? '0'),
                    'vat_amount' => $this->decimal($pos->{'total-price-tax'} ?? '0'),
                    'gross_amount' => $this->decimal($pos->{'total-price-gross'} ?? '0'),
                    'description' => $this->nilSafe($pos->description),
                ];
            }
        }

        $infoNotes = [];
        if (isset($node->descriptions->description)) {
            foreach ($node->descriptions->description as $desc) {
                $text = trim((string) $desc);
                if ($text !== '') {
                    $infoNotes[] = $text;
                }
            }
        }

        return [
            'ksef_number' => $this->nilSafe($node->{'gov-id'}),
            'invoice_number' => $this->nilSafe($node->number),
            'issue_date' => $this->date($node->{'issue-date'}),
            'sale_date' => $this->date($node->{'sell-date'}),
            'due_date' => $this->date($node->{'payment-to'}),
            'received_date' => $this->date($node->{'created-at'}),
            'payment_date' => $this->date($node->{'paid-date'}),
            'currency' => $this->nilSafe($node->currency) ?: 'PLN',
            'net_amount' => $this->decimal($node->{'price-net'} ?? '0'),
            'vat_amount' => $this->decimal($node->{'price-tax'} ?? '0'),
            'gross_amount' => $gross,
            'paid_amount' => $paid,
            'payment_status' => $paymentStatus,
            'payment_method' => $this->mapPaymentType($this->nilSafe($node->{'payment-type'})),
            'seller_nip' => ContractorResolver::normalizeNip($this->nilSafe($node->{'buyer-tax-no'})),
            'seller_name' => $this->nilSafe($node->{'buyer-name'}),
            'seller_street' => $this->nilSafe($node->{'buyer-street'}),
            'seller_post_code' => $this->nilSafe($node->{'buyer-post-code'}),
            'seller_city' => $this->nilSafe($node->{'buyer-city'}),
            'seller_country' => $this->nilSafe($node->{'buyer-country'}) ?: 'PL',
            'seller_email' => $this->nilSafe($node->{'buyer-email'}),
            'buyer_nip' => ContractorResolver::normalizeNip($this->nilSafe($node->{'seller-tax-no'})),
            'buyer_name' => $this->nilSafe($node->{'seller-name'}),
            'notes' => $infoNotes !== [] ? implode("\n", $infoNotes) : $this->nilSafe($node->description),
            'lines' => $lines,
            'raw_payload' => json_decode(json_encode($node), true),
        ];
    }

    private function nilSafe(?SimpleXMLElement $node): ?string
    {
        if ($node === null) {
            return null;
        }

        $attrs = $node->attributes();
        if ($attrs && isset($attrs['nil']) && (string) $attrs['nil'] === 'true') {
            return null;
        }

        $value = trim((string) $node);

        return $value !== '' ? $value : null;
    }

    private function decimal(SimpleXMLElement|string|null $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $str = trim((string) $value);

        return $str !== '' ? (float) $str : 0.0;
    }

    private function date(?SimpleXMLElement $value): ?string
    {
        $str = $this->nilSafe($value);
        if (! $str) {
            return null;
        }

        try {
            return Carbon::parse($str)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapPaymentType(?string $type): ?string
    {
        return match ($type) {
            'transfer' => 'Przelew',
            'cash' => 'Gotówka',
            'card' => 'Karta płatnicza',
            default => $type,
        };
    }
}

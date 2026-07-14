<?php

namespace App\Services\Invoices;

use App\Models\Contractor;
use App\Models\VendorInvoice;

class ContractorResolver
{
    public static function normalizeNip(?string $nip): ?string
    {
        if ($nip === null || $nip === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $nip);

        return $digits !== '' ? $digits : null;
    }

    public function resolveFromInvoice(VendorInvoice $invoice): ?Contractor
    {
        $nip = self::normalizeNip($invoice->seller_nip);
        if (! $nip) {
            return null;
        }

        $contractor = Contractor::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(nip, '-', ''), ' ', ''), '.', '') = ?", [$nip])
            ->first();

        if ($contractor) {
            return $contractor;
        }

        return null;
    }

    /**
     * @return array{name: string, nip: string, street?: string, post_code?: string, city?: string, country?: string, email?: string}
     */
    public function suggestFromInvoice(VendorInvoice $invoice): array
    {
        return array_filter([
            'name' => $invoice->seller_name,
            'nip' => self::normalizeNip($invoice->seller_nip),
            'street' => $invoice->seller_street,
            'post_code' => $invoice->seller_post_code,
            'city' => $invoice->seller_city,
            'country' => $invoice->seller_country,
            'email' => $invoice->seller_email,
        ]);
    }

    public function createFromInvoice(VendorInvoice $invoice): Contractor
    {
        $suggestion = $this->suggestFromInvoice($invoice);

        $contractor = Contractor::create([
            'name' => $suggestion['name'] ?? 'Kontrahent '.$suggestion['nip'],
            'nip' => $suggestion['nip'],
            'street' => $suggestion['street'] ?? null,
            'postal_code' => $suggestion['post_code'] ?? null,
            'city' => $suggestion['city'] ?? null,
            'country' => $suggestion['country'] ?? 'PL',
            'email' => $suggestion['email'] ?? null,
        ]);

        $invoice->update(['contractor_id' => $contractor->id]);

        return $contractor;
    }
}

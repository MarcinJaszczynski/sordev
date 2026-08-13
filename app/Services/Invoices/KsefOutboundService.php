<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\SalesInvoice;
use RuntimeException;

/**
 * Stub wysyłki e-Faktury do KSeF (outbound).
 * Import KSeF już istnieje — ten serwis domyka kierunek „wystaw → KSeF”.
 */
final class KsefOutboundService
{
    /**
     * @return array{accepted: bool, reference: ?string, message: string}
     */
    public function submit(SalesInvoice $invoice): array
    {
        if (! (bool) config('invoices.ksef_outbound_enabled', false)) {
            return [
                'accepted' => false,
                'reference' => null,
                'message' => 'KSeF outbound wyłączony (invoices.ksef_outbound_enabled=false).',
            ];
        }

        throw new RuntimeException('Integracja KSeF outbound nie jest jeszcze skonfigurowana (API MF).');
    }
}

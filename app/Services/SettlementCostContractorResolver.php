<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\VendorInvoice;
use Illuminate\Support\Collection;

/**
 * Ustalenie kontrahenta pozycji kosztowej z wielu źródeł:
 * koszt → punkt programu → panel transportu/hotelu → faktura → dokument.
 */
final class SettlementCostContractorResolver
{
    public static array $sourceLabels = [
        'cost' => 'Koszt',
        'program_point' => 'Punkt programu',
        'transport' => 'Transport',
        'hotel' => 'Hotel',
        'invoice' => 'Faktura',
        'document' => 'Dokument',
        'pilot' => 'Pilot',
    ];

    /**
     * @param  Collection<int, VendorInvoice>|null  $invoicesByCostId
     * @param  Collection<int, EventSettlementDocument>|null  $documents
     * @return array{
     *   contractor: ?string,
     *   contractor_id: ?int,
     *   contractor_details: list<string>,
     *   contractor_nip: ?string,
     *   contractor_email: ?string,
     *   contractor_phone: ?string,
     *   contractor_source: ?string,
     *   contractor_source_label: ?string,
     *   contractor_search: string
     * }
     */
    public function resolve(
        EventSettlementCost $cost,
        Event $event,
        ?EventProgramPoint $programPoint = null,
        ?Collection $invoicesByCostId = null,
        ?Collection $documents = null,
    ): array {
        if ($cost->relationLoaded('contractor') && $cost->contractor instanceof Contractor) {
            return $this->packContractor($cost->contractor, 'cost');
        }

        if (filled($cost->contractor_id)) {
            $contractor = Contractor::query()->find((int) $cost->contractor_id);
            if ($contractor) {
                return $this->packContractor($contractor, 'cost');
            }
        }

        if ($programPoint instanceof EventProgramPoint) {
            $programPoint->loadMissing('contractor');
            if ($programPoint->contractor instanceof Contractor) {
                return $this->packContractor($programPoint->contractor, 'program_point');
            }
        }

        $sourceType = (string) ($cost->source_type ?? '');

        if ($sourceType === 'transport' || $sourceType === 'transport_contractor') {
            if ($sourceType === 'transport_contractor' && filled($cost->contractor_id ?: $cost->source_id)) {
                $contractor = Contractor::query()->find((int) ($cost->contractor_id ?: $cost->source_id));
                if ($contractor) {
                    return $this->packContractor($contractor, 'transport');
                }
            }

            $transportContractor = $this->resolveTransportContractor($event);
            if ($transportContractor) {
                return $this->packContractor($transportContractor, 'transport');
            }
        }

        if ($sourceType === 'accommodation') {
            $hotelPack = $this->resolveHotelContractors($event);
            if ($hotelPack !== null) {
                return $hotelPack;
            }
        }

        $invoice = $invoicesByCostId?->get((int) $cost->id);
        if ($invoice instanceof VendorInvoice) {
            $fromInvoice = $this->resolveFromVendorInvoice($invoice);
            if ($fromInvoice !== null) {
                return $fromInvoice;
            }
        }

        $documentVendor = $this->resolveDocumentVendorName($cost, $documents);
        if ($documentVendor !== null) {
            return $documentVendor;
        }

        if (($cost->paid_by ?? '') === 'pilot') {
            $event->loadMissing('pilotContractor');
            if ($event->pilotContractor instanceof Contractor) {
                return $this->packContractor($event->pilotContractor, 'pilot');
            }
        }

        return $this->emptyPack();
    }

    private function resolveTransportContractor(Event $event): ?Contractor
    {
        $event->loadMissing(['transportContractor', 'driverContractor', 'transportProgramPoints.contractor']);

        if ($event->transportContractor instanceof Contractor) {
            return $event->transportContractor;
        }

        if ($event->driverContractor instanceof Contractor) {
            return $event->driverContractor;
        }

        $transportPoint = $event->transportProgramPoints
            ->first(fn (EventProgramPoint $point): bool => filled($point->contractor_id));

        return $transportPoint?->contractor instanceof Contractor
            ? $transportPoint->contractor
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveHotelContractors(Event $event): ?array
    {
        $event->loadMissing(['hotelStays.contractor', 'hotelServiceProgramPoints.contractor']);

        $contractors = collect()
            ->merge(
                $event->hotelStays
                    ->map(fn ($stay) => $stay->contractor)
                    ->filter(fn ($c) => $c instanceof Contractor)
            )
            ->merge(
                $event->hotelServiceProgramPoints
                    ->map(fn (EventProgramPoint $point) => $point->contractor)
                    ->filter(fn ($c) => $c instanceof Contractor)
            )
            ->unique(fn (Contractor $c): int => (int) $c->id)
            ->values();

        if ($contractors->isEmpty()) {
            return null;
        }

        if ($contractors->count() === 1) {
            return $this->packContractor($contractors->first(), 'hotel');
        }

        $labels = $contractors
            ->map(fn (Contractor $c): string => $c->displayLabel())
            ->filter()
            ->values()
            ->all();

        $primary = $contractors->first();

        return [
            'contractor' => implode(', ', $labels),
            'contractor_id' => (int) $primary->id,
            'contractor_details' => $this->contractorDetails($primary),
            'contractor_nip' => $primary->nip,
            'contractor_email' => $primary->email,
            'contractor_phone' => $primary->phone,
            'contractor_source' => 'hotel',
            'contractor_source_label' => self::$sourceLabels['hotel'].' ('.$contractors->count().')',
            'contractor_search' => $this->buildSearchBlob($labels, $primary),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFromVendorInvoice(VendorInvoice $invoice): ?array
    {
        $invoice->loadMissing('contractor');

        if ($invoice->contractor instanceof Contractor) {
            return $this->packContractor($invoice->contractor, 'invoice');
        }

        if (filled($invoice->seller_name)) {
            $name = trim((string) $invoice->seller_name);

            return [
                'contractor' => $name,
                'contractor_id' => $invoice->contractor_id ? (int) $invoice->contractor_id : null,
                'contractor_details' => array_values(array_filter([
                    $name,
                    filled($invoice->seller_nip) ? 'NIP '.$invoice->seller_nip : null,
                    filled($invoice->seller_email) ? (string) $invoice->seller_email : null,
                ])),
                'contractor_nip' => $invoice->seller_nip,
                'contractor_email' => $invoice->seller_email,
                'contractor_phone' => null,
                'contractor_source' => 'invoice',
                'contractor_source_label' => self::$sourceLabels['invoice'],
                'contractor_search' => mb_strtolower(trim($name.' '.($invoice->seller_nip ?? '').' '.($invoice->seller_email ?? ''))),
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, EventSettlementDocument>|null  $documents
     * @return array<string, mixed>|null
     */
    private function resolveDocumentVendorName(
        EventSettlementCost $cost,
        ?Collection $documents,
    ): ?array {
        if (! $documents instanceof Collection || $documents->isEmpty()) {
            return null;
        }

        $costIds = [(int) $cost->id];

        $vendorName = $documents
            ->filter(function (EventSettlementDocument $doc) use ($costIds): bool {
                $linked = collect($doc->linked_cost_ids ?? [])->map(fn ($id) => (int) $id)->all();

                return count(array_intersect($costIds, $linked)) > 0;
            })
            ->pluck('vendor_name')
            ->filter(fn ($name) => filled($name))
            ->first();

        if (! filled($vendorName)) {
            return null;
        }

        $name = trim((string) $vendorName);

        return [
            'contractor' => $name,
            'contractor_id' => null,
            'contractor_details' => [$name],
            'contractor_nip' => null,
            'contractor_email' => null,
            'contractor_phone' => null,
            'contractor_source' => 'document',
            'contractor_source_label' => self::$sourceLabels['document'],
            'contractor_search' => mb_strtolower($name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packContractor(Contractor $contractor, string $source): array
    {
        $label = $contractor->displayLabel();

        return [
            'contractor' => $label !== '' ? $label : null,
            'contractor_id' => (int) $contractor->id,
            'contractor_details' => $this->contractorDetails($contractor),
            'contractor_nip' => $contractor->nip,
            'contractor_email' => $contractor->email,
            'contractor_phone' => $contractor->phone,
            'contractor_source' => $source,
            'contractor_source_label' => self::$sourceLabels[$source] ?? $source,
            'contractor_search' => $this->buildSearchBlob([$label], $contractor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPack(): array
    {
        return [
            'contractor' => null,
            'contractor_id' => null,
            'contractor_details' => [],
            'contractor_nip' => null,
            'contractor_email' => null,
            'contractor_phone' => null,
            'contractor_source' => null,
            'contractor_source_label' => null,
            'contractor_search' => '',
        ];
    }

    /**
     * @return list<string>
     */
    private function contractorDetails(Contractor $contractor): array
    {
        $parts = [];
        $name = $contractor->displayLabel();
        if ($name !== '') {
            $parts[] = $name;
        }
        if (filled($contractor->nip)) {
            $parts[] = 'NIP '.$contractor->nip;
        }
        if (filled($contractor->email)) {
            $parts[] = (string) $contractor->email;
        }
        if (filled($contractor->phone)) {
            $parts[] = (string) $contractor->phone;
        }

        return $parts;
    }

    /**
     * @param  list<string>  $labels
     */
    private function buildSearchBlob(array $labels, Contractor $contractor): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            ...$labels,
            $contractor->nip,
            $contractor->email,
            $contractor->phone,
        ]))));
    }
}

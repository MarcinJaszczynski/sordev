<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractVariant;
use App\Models\ContractVariantLocation;
use App\Models\ContractVariantTransport;
use App\Models\Event;
use App\Models\TfgDictionaryItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ContractTfgSetupService
{
    public function defaultsFromEvent(Event $event): array
    {
        $event->loadMissing([
            'startPlace',
            'bus',
            'eventTemplate.endPlace',
            'eventTemplate.startPlace',
            'eventTemplate.transportTypes',
            'eventTemplate.eventTypes',
        ]);

        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $countryCode = $this->resolveCountryCodeFromEvent($event);

        $heuristics = [
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'reservation_number' => $this->resolveReservationNumberFromEvent($event),
            'tfg_travelers_count' => $participantCount,
            'tfg_starts_at' => optional($event->start_date)?->toDateString(),
            'tfg_ends_at' => optional($event->end_date)?->toDateString(),
            'tfg_scope_type' => $this->resolveScopeTypeFromEvent($event, $countryCode),
            'tfg_country_code' => $countryCode,
            'tfg_locality' => $this->resolveLocalityFromEvent($event),
            'tfg_transport_code' => $this->resolveTransportCodeFromEvent($event),
            'tfg_icao_codes' => [],
        ];

        $saved = [];
        if (Schema::hasColumn('events', 'tfg_defaults') && is_array($event->tfg_defaults)) {
            $saved = array_filter(
                $event->tfg_defaults,
                static fn ($value) => $value !== null && $value !== '',
            );
        }

        return array_merge($heuristics, $saved);
    }

    /**
     * @return array<string, mixed>
     */
    public function fillFormFromEvent(Event $event): array
    {
        return array_merge(
            [
                'event_name' => $event->name,
                'participant_count' => max(1, (int) ($event->participant_count ?? 1)),
                'event_start_date' => optional($event->start_date)?->toDateString(),
                'event_end_date' => optional($event->end_date)?->toDateString(),
            ],
            $this->defaultsFromEvent($event),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mergeMainFieldsIntoTfg(array $data): array
    {
        if (filled($data['participant_count'] ?? null)) {
            $data['tfg_travelers_count'] = max(1, (int) $data['participant_count']);
        }

        if (filled($data['event_start_date'] ?? null)) {
            $data['tfg_starts_at'] = $data['event_start_date'];
        }

        if (filled($data['event_end_date'] ?? null)) {
            $data['tfg_ends_at'] = $data['event_end_date'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mergeTfgFieldsIntoMain(array $data): array
    {
        if (filled($data['tfg_travelers_count'] ?? null)) {
            $data['participant_count'] = max(1, (int) $data['tfg_travelers_count']);
        }

        if (filled($data['tfg_starts_at'] ?? null)) {
            $data['event_start_date'] = $data['tfg_starts_at'];
        }

        if (filled($data['tfg_ends_at'] ?? null)) {
            $data['event_end_date'] = $data['tfg_ends_at'];
        }

        return $data;
    }

    public function defaultsFromContract(Contract $contract): array
    {
        $variant = $contract->variants()->with(['locations', 'transports'])->first();
        $location = $variant?->locations->first();
        $transport = $variant?->transports->first();

        return [
            'subject_code' => $contract->subject_code ?: 'IT',
            'payment_method_code' => $contract->payment_method_code ?: 'WPLATAPRZED',
            'reservation_number' => $contract->reservation_number,
            'tfg_travelers_count' => $variant?->travelers_count ?? max(1, (int) ($contract->participant_count ?? 1)),
            'tfg_starts_at' => optional($variant?->starts_at)?->toDateString()
                ?? optional($contract->event_start_date)?->toDateString(),
            'tfg_ends_at' => optional($variant?->ends_at)?->toDateString()
                ?? optional($contract->event_end_date)?->toDateString(),
            'tfg_scope_type' => $location?->scope_type ?: 'PLISAS',
            'tfg_country_code' => $location?->country_code ?: 'PL',
            'tfg_locality' => $location?->locality,
            'tfg_transport_code' => $transport?->transport_code ?: 'NLOT',
            'tfg_icao_codes' => $transport?->icao_codes ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyToContract(Contract $contract, array $data): void
    {
        $data = $this->mergeTfgFieldsIntoMain($data);

        $travelersCount = max(1, (int) ($data['tfg_travelers_count'] ?? $data['participant_count'] ?? $contract->participant_count ?? 1));

        $contract->forceFill([
            'subject_code' => $data['subject_code'] ?? $contract->subject_code ?? 'IT',
            'payment_method_code' => $data['payment_method_code'] ?? $contract->payment_method_code ?? 'WPLATAPRZED',
            'reservation_number' => $data['reservation_number'] ?? $contract->reservation_number,
            'participant_count' => $travelersCount,
            'event_start_date' => $data['tfg_starts_at'] ?? $data['event_start_date'] ?? $contract->event_start_date,
            'event_end_date' => $data['tfg_ends_at'] ?? $data['event_end_date'] ?? $contract->event_end_date,
        ])->saveQuietly();

        $this->syncPrimaryVariant($contract, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncPrimaryVariant(Contract $contract, array $data): void
    {
        $travelersCount = max(1, (int) ($data['tfg_travelers_count'] ?? $contract->participant_count ?? 1));

        $variant = $contract->variants()->first();

        if (! $variant) {
            $variant = ContractVariant::create([
                'contract_id' => $contract->id,
                'sort_order' => 0,
                'travelers_count' => $travelersCount,
                'starts_at' => $data['tfg_starts_at'] ?? $contract->event_start_date,
                'ends_at' => $data['tfg_ends_at'] ?? $contract->event_end_date,
            ]);
        } else {
            $variant->update([
                'travelers_count' => $travelersCount,
                'starts_at' => $data['tfg_starts_at'] ?? $variant->starts_at,
                'ends_at' => $data['tfg_ends_at'] ?? $variant->ends_at,
            ]);
        }

        $location = $variant->locations()->first();

        if (! $location) {
            ContractVariantLocation::create([
                'contract_variant_id' => $variant->id,
                'scope_type' => $data['tfg_scope_type'] ?? 'PLISAS',
                'country_code' => $data['tfg_country_code'] ?? 'PL',
                'locality' => $data['tfg_locality'] ?? null,
                'sort_order' => 0,
            ]);
        } else {
            $location->update([
                'scope_type' => $data['tfg_scope_type'] ?? $location->scope_type,
                'country_code' => $data['tfg_country_code'] ?? $location->country_code,
                'locality' => $data['tfg_locality'] ?? $location->locality,
            ]);
        }

        $transport = $variant->transports()->first();
        $icaoCodes = Arr::wrap($data['tfg_icao_codes'] ?? []);
        $transportCode = (string) ($data['tfg_transport_code'] ?? 'NLOT');

        if (! $transport) {
            ContractVariantTransport::create([
                'contract_variant_id' => $variant->id,
                'transport_code' => $transportCode,
                'icao_codes' => TfgDictionaryItem::requiresIcao($transportCode) ? $icaoCodes : null,
                'sort_order' => 0,
            ]);
        } else {
            $transport->update([
                'transport_code' => $transportCode,
                'icao_codes' => TfgDictionaryItem::requiresIcao($transportCode) ? $icaoCodes : null,
            ]);
        }
    }

    public function cloneTfgStructureFromContract(Contract $source, Contract $target): void
    {
        $target->forceFill([
            'subject_code' => $source->subject_code,
            'payment_method_code' => $source->payment_method_code,
            'reservation_number' => $source->reservation_number,
        ])->saveQuietly();

        foreach ($source->variants()->with(['locations', 'transports'])->orderBy('sort_order')->get() as $index => $sourceVariant) {
            $variant = ContractVariant::create([
                'contract_id' => $target->id,
                'sort_order' => $index,
                'travelers_count' => $sourceVariant->travelers_count,
                'starts_at' => $sourceVariant->starts_at,
                'ends_at' => $sourceVariant->ends_at,
            ]);

            foreach ($sourceVariant->locations as $locationIndex => $location) {
                ContractVariantLocation::create([
                    'contract_variant_id' => $variant->id,
                    'scope_type' => $location->scope_type,
                    'country_code' => $location->country_code,
                    'locality' => $location->locality,
                    'sort_order' => $locationIndex,
                ]);
            }

            foreach ($sourceVariant->transports as $transportIndex => $transport) {
                ContractVariantTransport::create([
                    'contract_variant_id' => $variant->id,
                    'transport_code' => $transport->transport_code,
                    'icao_codes' => $transport->icao_codes,
                    'sort_order' => $transportIndex,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAnnex(Contract $parent, array $data): Contract
    {
        $parent->loadMissing(['event', 'variants.locations', 'variants.transports']);

        $sequence = Contract::query()
            ->where('event_id', $parent->event_id)
            ->where('meta->parent_contract_id', $parent->id)
            ->count() + 1;

        $parentNumber = $parent->contract_number ?: ('#'.$parent->id);

        $annex = Contract::create([
            'event_id' => $parent->event_id,
            'contract_template_id' => $data['contract_template_id'] ?? $parent->contract_template_id,
            'contract_type' => $parent->contract_type,
            'title' => $data['title'] ?? sprintf('Aneks %d do umowy %s', $sequence, $parentNumber),
            'contract_date' => $data['agreement_date'] ?? $data['contract_date'] ?? now()->toDateString(),
            'event_name' => $parent->event_name,
            'event_start_date' => $data['event_start_date'] ?? $parent->event_start_date,
            'event_end_date' => $data['event_end_date'] ?? $parent->event_end_date,
            'customer_name' => $parent->customer_name,
            'customer_email' => $parent->customer_email,
            'customer_phone' => $parent->customer_phone,
            'signer_name' => $parent->signer_name,
            'signer_email' => $parent->signer_email,
            'signer_phone' => $parent->signer_phone,
            'participant_name' => $parent->participant_name,
            'participant_birth_date' => $parent->participant_birth_date,
            'participant_email' => $parent->participant_email,
            'participant_phone' => $parent->participant_phone,
            'participant_payment_id' => $parent->participant_payment_id,
            'participant_count' => (int) ($data['participant_count'] ?? $parent->participant_count ?? 1),
            'unit_price' => $parent->unit_price,
            'total_price' => (float) ($data['amount_due'] ?? $data['total_price'] ?? $parent->total_price),
            'currency' => $parent->currency ?? 'PLN',
            'status' => $data['status'] ?? 'sent',
            'payment_status' => $data['payment_status'] ?? 'pending',
            'payment_scheme' => $parent->payment_scheme ?: Contract::PAYMENT_SCHEME_LUMP_SUM,
            'attachments' => $data['attachments'] ?? $parent->attachments,
            'body_edit_mode' => $data['body_edit_mode'] ?? Contract::BODY_EDIT_TEMPLATE,
            'created_by' => auth()->id(),
            'meta' => array_merge($parent->meta ?? [], [
                'parent_contract_id' => $parent->id,
                'is_annex' => true,
                'annex_sequence' => $sequence,
            ]),
        ]);

        $this->cloneTfgStructureFromContract($parent, $annex);
        $this->applyToContract($annex, array_merge($this->defaultsFromContract($parent), $data));

        app(ContractOrderingPartyService::class)->clonePartiesFromContract($parent, $annex);
        app(ContractAnnexService::class)->applyAnnexAttributes($annex, $data, $parent->event);

        $annex->refresh();

        if ($annex->shouldAutoGenerateAgreementBody()) {
            $annex->regenerateAgreementBody();
        }

        return $annex->fresh(['variants.locations', 'variants.transports']);
    }

    protected function resolveTransportCodeFromEvent(Event $event): string
    {
        foreach ($event->eventTemplate?->transportTypes ?? [] as $transportType) {
            $name = Str::lower((string) ($transportType->name ?? ''));

            if (str_contains($name, 'czart') || str_contains($name, 'charter')) {
                return 'LOTCZART';
            }

            if (str_contains($name, 'samolot') || str_contains($name, 'lot')) {
                return 'LOTNCZART';
            }
        }

        return 'NLOT';
    }

    protected function resolveScopeTypeFromEvent(Event $event, string $countryCode): string
    {
        $scope = TfgDictionaryItem::scopeForCountry($countryCode);

        // Foreign trip without a resolvable destination country -> assume European.
        if ($event->eventTemplate?->isForeignTrip() && ($scope === null || ($scope === 'PLISAS' && $countryCode === 'PL'))) {
            return 'EUR';
        }

        return $scope ?: 'PLISAS';
    }

    protected function resolveLocalityFromEvent(Event $event): ?string
    {
        return $event->eventTemplate?->endPlace?->name
            ?? $event->startPlace?->name;
    }

    protected function resolveCountryCodeFromEvent(Event $event): string
    {
        if ($event->startPlace?->country) {
            return strtoupper(substr((string) $event->startPlace->country, 0, 2));
        }

        return 'PL';
    }

    protected function resolveReservationNumberFromEvent(Event $event): string
    {
        if (Schema::hasTable('reservations')) {
            $reference = $event->reservations()
                ->whereNotNull('booking_reference')
                ->where('booking_reference', '!=', '')
                ->orderBy('id')
                ->value('booking_reference');

            if (filled($reference)) {
                return (string) $reference;
            }
        }

        return sprintf('IMP-%05d', $event->id);
    }
}

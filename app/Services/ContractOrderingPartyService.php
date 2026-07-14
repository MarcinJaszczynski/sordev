<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\ContractOrderingParty;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventAgreementOrderingParty;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class ContractOrderingPartyService
{
    /**
     * @param  array<int, array<string, mixed>>  $parties
     */
    public function syncForContract(Contract $contract, array $parties, ?string $orderingPartyNotes = null): void
    {
        $normalized = $this->normalizeParties($parties);

        $contract->orderingParties()->delete();

        foreach ($normalized as $party) {
            $contract->orderingParties()->create($party);
        }

        $updates = $this->primaryCustomerAttributes($normalized);

        if ($orderingPartyNotes !== null) {
            $updates['ordering_party_notes'] = $orderingPartyNotes;
        }

        if ($updates !== []) {
            $contract->updateQuietly($updates);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $parties
     */
    public function syncForEventAgreement(EventAgreement $agreement, array $parties, ?string $orderingPartyNotes = null): void
    {
        $normalized = $this->normalizeParties($parties);

        $agreement->orderingParties()->delete();

        foreach ($normalized as $party) {
            $agreement->orderingParties()->create($party);
        }

        $updates = $this->primaryCustomerAttributes($normalized);

        if ($orderingPartyNotes !== null) {
            $updates['ordering_party_notes'] = $orderingPartyNotes;
        }

        if ($updates !== []) {
            $agreement->updateQuietly($updates);
        }
    }

    public function clonePartiesFromContract(Contract $source, Contract $target): void
    {
        $source->loadMissing('orderingParties');

        if ($source->orderingParties->isEmpty()) {
            return;
        }

        foreach ($source->orderingParties as $party) {
            $target->orderingParties()->create($party->only([
                'contractor_id',
                'sort_order',
                'name',
                'email',
                'phone',
                'nip',
                'street',
                'house_number',
                'city',
                'postal_code',
                'notes',
            ]));
        }

        $target->updateQuietly([
            'customer_name' => $source->customer_name,
            'customer_email' => $source->customer_email,
            'customer_phone' => $source->customer_phone,
            'ordering_party_notes' => $source->ordering_party_notes,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function partiesFromEvent(Event $event): array
    {
        $event->loadMissing('orderingContractors');

        if ($event->orderingContractors->isNotEmpty()) {
            return $event->orderingContractors
                ->values()
                ->map(function (Contractor $contractor, int $index): array {
                    $contactId = Schema::hasColumn('event_contractor', 'contact_id')
                        ? ($contractor->pivot->contact_id ?? null)
                        : null;
                    $department = Schema::hasColumn('event_contractor', 'department_label')
                        ? ($contractor->pivot->department_label ?? null)
                        : null;

                    if ($contactId) {
                        $contact = Contact::find($contactId);

                        return array_merge(
                            ContractOrderingParty::snapshotFromContractor($contractor),
                            [
                                'sort_order' => $index,
                                'name' => $contact
                                    ? trim($contact->displayName().(filled($department) ? ' · '.$department : '').' ('.$contractor->name.')')
                                    : $contractor->name,
                                'email' => $contact?->email ?? $contractor->email,
                                'phone' => $contact?->phone ?? $contractor->phone,
                            ],
                        );
                    }

                    return array_merge(
                        ContractOrderingParty::snapshotFromContractor($contractor),
                        [
                            'sort_order' => $index,
                            'name' => filled($department) ? $department.' · '.$contractor->name : $contractor->name,
                        ],
                    );
                })
                ->all();
        }

        if (filled($event->client_name)) {
            return [[
                'contractor_id' => $event->contractor_id ?? null,
                'sort_order' => 0,
                'name' => $event->client_name,
                'email' => $event->client_email,
                'phone' => $event->client_phone,
            ]];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function partiesToFormState(Contract|EventAgreement $record): array
    {
        $record->loadMissing('orderingParties');

        if ($record->orderingParties->isNotEmpty()) {
            return $record->orderingParties
                ->map(fn (ContractOrderingParty|EventAgreementOrderingParty $party): array => [
                    'contractor_id' => $party->contractor_id,
                    'name' => $this->resolvePartyName($party),
                    'email' => $party->email,
                    'phone' => $party->phone,
                    'nip' => $party->nip,
                    'street' => $party->street,
                    'house_number' => $party->house_number,
                    'city' => $party->city,
                    'postal_code' => $party->postal_code,
                    'notes' => $party->notes,
                ])
                ->all();
        }

        if (filled($record->customer_name)) {
            return [[
                'name' => $record->customer_name,
                'email' => $record->customer_email,
                'phone' => $record->customer_phone,
            ]];
        }

        return [];
    }

    public function formattedPartyNames(Contract|EventAgreement $record): string
    {
        $record->loadMissing('orderingParties.contractor');

        $names = $record->orderingParties
            ->map(fn (ContractOrderingParty|EventAgreementOrderingParty $party): string => $this->resolvePartyName($party))
            ->filter(fn (string $name): bool => filled($name) && $name !== '—');

        if ($names->isNotEmpty()) {
            return $names->implode(', ');
        }

        return (string) ($record->customer_name ?: '—');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function partiesForTemplatePayload(Contract|EventAgreement $record): array
    {
        $record->loadMissing('orderingParties.contractor');

        if ($record->orderingParties->isNotEmpty()) {
            return $record->orderingParties
                ->map(fn (ContractOrderingParty|EventAgreementOrderingParty $party): array => [
                    'name' => $this->resolvePartyName($party),
                    'email' => $party->email ?: $party->contractor?->email,
                    'phone' => $party->phone ?: $party->contractor?->phone,
                    'nip' => $party->nip ?: $party->contractor?->nip,
                    'street' => $party->street ?: $party->contractor?->street,
                    'house_number' => $party->house_number ?: $party->contractor?->house_number,
                    'city' => $party->city ?: $party->contractor?->city,
                    'postal_code' => $party->postal_code ?: $party->contractor?->postal_code,
                    'address' => $this->formatAddress($party),
                    'notes' => $party->notes,
                ])
                ->all();
        }

        if (filled($record->customer_name)) {
            return [[
                'name' => $record->customer_name,
                'email' => $record->customer_email,
                'phone' => $record->customer_phone,
                'nip' => null,
                'street' => null,
                'house_number' => null,
                'city' => null,
                'postal_code' => null,
                'address' => '—',
                'notes' => null,
            ]];
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parties
     * @return array<string, mixed>
     */
    public function applyPrimaryCustomerToFormData(array $data): array
    {
        $parties = $this->normalizeParties(Arr::wrap($data['ordering_parties'] ?? []));

        if ($parties === []) {
            return $data;
        }

        $primary = $parties[0];

        $data['customer_name'] = $primary['name'];
        $data['customer_email'] = $primary['email'] ?? null;
        $data['customer_phone'] = $primary['phone'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: ?string}
     */
    public function extractOrderingPartyPayload(array $data): array
    {
        $parties = Arr::wrap($data['ordering_parties'] ?? []);
        $notes = $data['ordering_party_notes'] ?? null;

        unset($data['ordering_parties']);

        return [$data, $parties, $notes];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parties
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeParties(array $parties): array
    {
        return collect($parties)
            ->filter(fn (array $party): bool => filled($party['name'] ?? null))
            ->values()
            ->map(function (array $party, int $index): array {
                return [
                    'contractor_id' => filled($party['contractor_id'] ?? null) ? (int) $party['contractor_id'] : null,
                    'sort_order' => $index,
                    'name' => trim((string) $party['name']),
                    'email' => filled($party['email'] ?? null) ? trim((string) $party['email']) : null,
                    'phone' => filled($party['phone'] ?? null) ? trim((string) $party['phone']) : null,
                    'nip' => filled($party['nip'] ?? null) ? trim((string) $party['nip']) : null,
                    'street' => filled($party['street'] ?? null) ? trim((string) $party['street']) : null,
                    'house_number' => filled($party['house_number'] ?? null) ? trim((string) $party['house_number']) : null,
                    'city' => filled($party['city'] ?? null) ? trim((string) $party['city']) : null,
                    'postal_code' => filled($party['postal_code'] ?? null) ? trim((string) $party['postal_code']) : null,
                    'notes' => filled($party['notes'] ?? null) ? trim((string) $party['notes']) : null,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $parties
     * @return array<string, string|null>
     */
    protected function primaryCustomerAttributes(array $parties): array
    {
        $primary = $parties[0] ?? null;

        if ($primary === null) {
            return [];
        }

        return [
            'customer_name' => $primary['name'],
            'customer_email' => $primary['email'] ?? null,
            'customer_phone' => $primary['phone'] ?? null,
        ];
    }

    protected function formatAddress(ContractOrderingParty|EventAgreementOrderingParty $party): string
    {
        $party->loadMissing('contractor');

        $street = $party->street ?: $party->contractor?->street;
        $houseNumber = $party->house_number ?: $party->contractor?->house_number;
        $postalCode = $party->postal_code ?: $party->contractor?->postal_code;
        $city = $party->city ?: $party->contractor?->city;

        $line = trim(implode(' ', array_filter([
            $street,
            $houseNumber,
        ])));

        $cityLine = trim(implode(' ', array_filter([
            $postalCode,
            $city,
        ])));

        $address = collect([$line, $cityLine])->filter()->implode(', ');

        return $address !== '' ? $address : '—';
    }

    protected function resolvePartyName(ContractOrderingParty|EventAgreementOrderingParty $party): string
    {
        if (filled($party->name)) {
            return (string) $party->name;
        }

        $party->loadMissing('contractor');

        return (string) ($party->contractor?->name ?: '—');
    }
}

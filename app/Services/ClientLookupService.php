<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ClientLookupService
{
    public const RESULT_LIMIT = 30;

    public const MIN_TERM_LENGTH = 2;

    /**
     * @param  array<string, mixed>  $criteria
     * @return Collection<int, array<string, mixed>>
     */
    public function search(array $criteria, bool $searchAll = false): Collection
    {
        $criteria = $this->normalizeCriteria($criteria);

        if (! $this->hasActiveCriteria($criteria)) {
            return collect();
        }

        $clientTypeNames = $searchAll ? [] : \App\Models\ContractorType::clientTypeNames();
        $contacts = $this->searchContacts($criteria, $searchAll);
        $contractors = $this->searchContractors($criteria, $searchAll);

        $results = collect();
        $seenPairs = [];

        foreach ($contacts as $contact) {
            if ($clientTypeNames === []) {
                $contact->loadMissing('contractors');
            } else {
                $contact->loadMissing(['contractors' => fn ($q) => $q->withAnyTypeName($clientTypeNames)]);
            }

            if ($contact->contractors->isNotEmpty()) {
                foreach ($contact->contractors as $contractor) {
                    $key = $this->pairKey($contact->id, $contractor->id);

                    if (isset($seenPairs[$key])) {
                        continue;
                    }

                    $seenPairs[$key] = true;
                    $results->push($this->makePairRow($contact, $contractor));
                }
            } elseif ($searchAll) {
                $results->push($this->makeContactRow($contact));
            }
        }

        foreach ($contractors as $contractor) {
            $contractor->loadMissing('contacts');

            if ($contractor->contacts->isNotEmpty()) {
                foreach ($contractor->contacts as $contact) {
                    $key = $this->pairKey($contact->id, $contractor->id);

                    if (isset($seenPairs[$key])) {
                        continue;
                    }

                    $seenPairs[$key] = true;
                    $results->push($this->makePairRow($contact, $contractor));
                }

                continue;
            }

            if ($results->contains(fn (array $row): bool => (int) ($row['contractor_id'] ?? 0) === $contractor->id)) {
                continue;
            }

            $results->push($this->makeContractorRow($contractor));
        }

        return $results
            ->take(self::RESULT_LIMIT)
            ->values();
    }

    public const MIN_QUERY_LENGTH = 3;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function searchFromQuery(string $query, bool $searchAll = false): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        return $this->searchUnified($query, $searchAll);
    }

    /**
     * Jedno wyszukiwanie po wszystkich polach (zamiast 6 osobnych LIKE).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function searchUnified(string $term, bool $searchAll = false): Collection
    {
        $like = '%'.$term.'%';
        $clientTypeNames = $searchAll ? [] : \App\Models\ContractorType::clientTypeNames();

        $contractorsQuery = Contractor::query();

        if ($clientTypeNames !== []) {
            $contractorsQuery->withAnyTypeName($clientTypeNames);
        }

        $contractors = $contractorsQuery
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);

                if (Schema::hasColumn('contractors', 'street')) {
                    $builder->orWhere('street', 'like', $like);
                }

                if (Schema::hasColumn('contractors', 'city')) {
                    $builder->orWhere('city', 'like', $like);
                }

                if (Schema::hasColumn('contractors', 'postal_code')) {
                    $builder->orWhere('postal_code', 'like', $like);
                }

                if (Schema::hasColumn('contractors', 'nip')) {
                    $builder->orWhere('nip', 'like', $like);
                }
            })
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        // Kontakty: domyślnie tylko powiązane z typem „klient”; przy searchAll — też sieroty.
        $contactsQuery = Contact::query()
            ->where(function ($builder) use ($like): void {
                $builder->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);

                if (Schema::hasColumn('contacts', 'address')) {
                    $builder->orWhere('address', 'like', $like);
                }

                if (Schema::hasColumn('contacts', 'notes')) {
                    $builder->orWhere('notes', 'like', $like);
                }
            });

        if ($clientTypeNames !== []) {
            $contactsQuery->whereHas('contractors', fn ($q) => $q->withAnyTypeName($clientTypeNames));
        }

        $contacts = $contactsQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        $results = collect();
        $seenPairs = [];

        foreach ($contacts as $contact) {
            if ($clientTypeNames === []) {
                $contact->loadMissing('contractors');
            } else {
                $contact->loadMissing(['contractors' => fn ($q) => $q->withAnyTypeName($clientTypeNames)]);
            }

            if ($contact->contractors->isNotEmpty()) {
                foreach ($contact->contractors as $contractor) {
                    $key = $this->pairKey($contact->id, $contractor->id);

                    if (isset($seenPairs[$key])) {
                        continue;
                    }

                    $seenPairs[$key] = true;
                    $results->push($this->makePairRow($contact, $contractor));
                }
            } elseif ($searchAll) {
                $results->push($this->makeContactRow($contact));
            }
        }

        foreach ($contractors as $contractor) {
            $contractor->loadMissing('contacts');

            if ($contractor->contacts->isNotEmpty()) {
                foreach ($contractor->contacts as $contact) {
                    $key = $this->pairKey($contact->id, $contractor->id);

                    if (isset($seenPairs[$key])) {
                        continue;
                    }

                    $seenPairs[$key] = true;
                    $results->push($this->makePairRow($contact, $contractor));
                }

                continue;
            }

            if ($results->contains(fn (array $row): bool => (int) ($row['contractor_id'] ?? 0) === $contractor->id)) {
                continue;
            }

            $results->push($this->makeContractorRow($contractor));
        }

        return $results
            ->take(self::RESULT_LIMIT)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function quickCreate(array $data): array
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($phone === '' && $email === '') {
            throw new \InvalidArgumentException('Podaj telefon lub e-mail klienta.');
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $companyName = trim((string) ($data['company_name'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));

        if ($companyName === '') {
            $personName = trim($firstName.' '.$lastName);
            $companyName = $personName !== '' ? $personName : 'Klient '.($phone !== '' ? $phone : $email);
        }

        $contractorPayload = [
            'name' => $companyName,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'street' => $address !== '' ? $address : null,
            'nip' => null,
            'house_number' => null,
            'city' => null,
            'postal_code' => null,
            'office_notes' => null,
        ];

        $contractorId = app(EventOrderingPartyService::class)->createContractorFromFormData(
            $contractorPayload,
            typeNames: \App\Models\ContractorType::clientTypeNames(),
        );

        $contactId = null;

        if ($firstName !== '' || $lastName !== '') {
            $contactId = app(EventOrderingPartyService::class)->createContact([
                'first_name' => $firstName !== '' ? $firstName : '—',
                'last_name' => $lastName !== '' ? $lastName : '—',
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ], $contractorId);
        }

        $departmentLabel = filled($data['department_label'] ?? null)
            ? trim((string) $data['department_label'])
            : null;

        $normalizedParties = [[
            'contact_id' => $contactId,
            'contractor_id' => $contractorId,
            'department_label' => $departmentLabel,
        ]];

        app(ContactContractorLinkService::class)->linkParties($normalizedParties);

        $clientAttributes = app(EventOrderingPartyService::class)->primaryClientAttributes($normalizedParties);

        $formParty = [
            'contact_id' => $contactId ? (string) $contactId : null,
            'contractor_id' => (string) $contractorId,
            'department_label' => $departmentLabel,
            'notes' => null,
        ];

        $selected = $contactId
            ? $this->makePairRow(Contact::find($contactId), Contractor::find($contractorId), $departmentLabel)
            : $this->makeContractorRow(Contractor::find($contractorId));

        return [
            'ordering_parties' => [$formParty],
            'client_name' => (string) ($clientAttributes['client_name'] ?? $companyName),
            'client_email' => $clientAttributes['client_email'] ?? ($email !== '' ? $email : null),
            'client_phone' => $clientAttributes['client_phone'] ?? ($phone !== '' ? $phone : null),
            'selected' => $selected,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, string>
     */
    private function normalizeCriteria(array $criteria): array
    {
        return collect($criteria)
            ->only(['first_name', 'last_name', 'phone', 'email', 'address', 'company_name'])
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->all();
    }

    /**
     * @param  array<string, string>  $criteria
     */
    private function hasActiveCriteria(array $criteria): bool
    {
        foreach ($criteria as $value) {
            if ($value !== '' && mb_strlen($value) >= self::MIN_TERM_LENGTH) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $criteria
     * @return Collection<int, Contact>
     */
    private function searchContacts(array $criteria, bool $searchAll = false): Collection
    {
        $query = Contact::query();

        if (! $searchAll) {
            $clientTypeNames = \App\Models\ContractorType::clientTypeNames();
            $query->whereHas('contractors', fn ($q) => $q->withAnyTypeName($clientTypeNames));
        }

        $query->where(function ($builder) use ($criteria): void {
            $this->applyContactCriteria($builder, $criteria);
        });

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::RESULT_LIMIT)
            ->get();
    }

    /**
     * @param  array<string, string>  $criteria
     * @return Collection<int, Contractor>
     */
    private function searchContractors(array $criteria, bool $searchAll = false): Collection
    {
        $query = Contractor::query();

        if (! $searchAll) {
            $query->withAnyTypeName(\App\Models\ContractorType::clientTypeNames());
        }

        $query->where(function ($builder) use ($criteria): void {
            $this->applyContractorCriteria($builder, $criteria);
        });

        return $query
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Contact>  $query
     * @param  array<string, string>  $criteria
     */
    private function applyContactCriteria($query, array $criteria): void
    {
        $applied = false;

        if ($this->isActiveTerm($criteria['first_name'] ?? '')) {
            $query->orWhere('first_name', 'like', '%'.$criteria['first_name'].'%');
            $applied = true;
        }

        if ($this->isActiveTerm($criteria['last_name'] ?? '')) {
            $query->orWhere('last_name', 'like', '%'.$criteria['last_name'].'%');
            $applied = true;
        }

        if ($this->isActiveTerm($criteria['email'] ?? '')) {
            $query->orWhere('email', 'like', '%'.$criteria['email'].'%');
            $applied = true;
        }

        if ($this->isActiveTerm($criteria['phone'] ?? '')) {
            $phone = $criteria['phone'];
            $query->orWhere('phone', 'like', '%'.$phone.'%');
            $applied = true;
        }

        if (! $applied) {
            $query->whereRaw('0 = 1');
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Contractor>  $query
     * @param  array<string, string>  $criteria
     */
    private function applyContractorCriteria($query, array $criteria): void
    {
        $applied = false;

        if ($this->isActiveTerm($criteria['company_name'] ?? '')) {
            $query->orWhere('name', 'like', '%'.$criteria['company_name'].'%');
            $applied = true;
        }

        if ($this->isActiveTerm($criteria['email'] ?? '')) {
            $query->orWhere('email', 'like', '%'.$criteria['email'].'%');
            $applied = true;
        }

        if ($this->isActiveTerm($criteria['phone'] ?? '')) {
            $query->orWhere('phone', 'like', '%'.$criteria['phone'].'%');
            $applied = true;
        }

        if ($this->isActiveTerm($criteria['address'] ?? '')) {
            $address = $criteria['address'];
            $query->orWhere('street', 'like', '%'.$address.'%')
                ->orWhere('city', 'like', '%'.$address.'%')
                ->orWhere('postal_code', 'like', '%'.$address.'%');

            if (Schema::hasColumn('contractors', 'nip')) {
                $query->orWhere('nip', 'like', '%'.$address.'%');
            }

            $applied = true;
        }

        if ($this->isActiveTerm($criteria['first_name'] ?? '') || $this->isActiveTerm($criteria['last_name'] ?? '')) {
            $name = trim(($criteria['first_name'] ?? '').' '.($criteria['last_name'] ?? ''));

            if (mb_strlen($name) >= self::MIN_TERM_LENGTH) {
                $query->orWhere('name', 'like', '%'.$name.'%');
                $applied = true;
            }
        }

        if (! $applied) {
            $query->whereRaw('0 = 1');
        }
    }

    private function isActiveTerm(string $value): bool
    {
        return $value !== '' && mb_strlen($value) >= self::MIN_TERM_LENGTH;
    }

    /**
     * @return array<string, mixed>
     */
    public function makePairRow(
        Contact $contact,
        Contractor $contractor,
        ?string $departmentLabel = null,
        ?string $notes = null,
    ): array {
        $service = app(EventOrderingPartyService::class);

        return [
            'type' => 'pair',
            'contact_id' => $contact->id,
            'contractor_id' => $contractor->id,
            'label' => $service->formatPartyItemHeading(
                $contact->id,
                $contractor->id,
                $departmentLabel,
                $notes,
            ),
            'notes' => $notes,
            'preview' => $this->buildPreview($contact, $contractor, $departmentLabel, $notes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function makeContactRow(Contact $contact): array
    {
        return [
            'type' => 'contact',
            'contact_id' => $contact->id,
            'contractor_id' => null,
            'label' => app(EventOrderingPartyService::class)->formatContactLabel($contact),
            'notes' => null,
            'preview' => $this->buildPreview($contact, null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function makeContractorRow(Contractor $contractor, ?string $notes = null): array
    {
        return [
            'type' => 'contractor',
            'contact_id' => null,
            'contractor_id' => $contractor->id,
            'label' => app(EventOrderingPartyService::class)->formatPartyItemHeading(
                null,
                $contractor->id,
                null,
                $notes,
            ),
            'notes' => $notes,
            'preview' => $this->buildPreview(null, $contractor, null, $notes),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function buildPreview(
        ?Contact $contact,
        ?Contractor $contractor,
        ?string $departmentLabel = null,
        ?string $notes = null,
    ): array {
        $addressParts = collect([
            $contractor?->street,
            $contractor?->house_number,
            $contractor?->postal_code,
            $contractor?->city,
        ])->filter()->implode(' ');

        return [
            'person' => $contact?->displayName(),
            'company' => $contractor?->name,
            'department' => $departmentLabel,
            'phone' => $contact?->phone ?? $contractor?->phone,
            'email' => $contact?->email ?? $contractor?->email,
            'address' => $addressParts !== '' ? $addressParts : null,
            'notes' => $notes,
        ];
    }

    /**
     * Karty dodatkowych zamawiających na Podsumowaniu (bez głównego).
     *
     * @return array<int, array<string, mixed>>
     */
    public function additionalSelectedFromEvent(Event $event): array
    {
        $parties = app(EventOrderingPartyService::class)->partiesToFormState($event);

        return collect($parties)
            ->slice(1)
            ->values()
            ->map(fn (array $party): ?array => $this->selectedRowFromParty($party))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>|null
     */
    public function selectedRowFromParty(array $party): ?array
    {
        $contractorId = filled($party['contractor_id'] ?? null) ? (int) $party['contractor_id'] : null;

        if (! $contractorId) {
            return null;
        }

        $contractor = Contractor::query()->find($contractorId);

        if (! $contractor) {
            return null;
        }

        $department = filled($party['department_label'] ?? null)
            ? (string) $party['department_label']
            : null;
        $notes = filled($party['notes'] ?? null) ? (string) $party['notes'] : null;
        $contact = filled($party['contact_id'] ?? null)
            ? Contact::query()->find((int) $party['contact_id'])
            : null;

        if ($contact) {
            $row = $this->makePairRow($contact, $contractor, $department, $notes);
        } else {
            $row = $this->makeContractorRow($contractor, $notes);

            if ($department !== null) {
                $row['preview']['department'] = $department;
                $row['label'] = app(EventOrderingPartyService::class)->formatPartyItemHeading(
                    null,
                    $contractorId,
                    $department,
                    $notes,
                );
            }
        }

        $row['goes_on_trip'] = (bool) ($party['goes_on_trip'] ?? false);

        return $row;
    }

    /**
     * Kontakty powiązane z firmą — szybki wybór bez globalnego searcha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function companyContactsForQuickPick(int $contractorId): array
    {
        if ($contractorId <= 0 || ! Contractor::hasContactPivotTable()) {
            return [];
        }

        $contractor = Contractor::query()->find($contractorId);

        if (! $contractor) {
            return [];
        }

        return $contractor->contacts()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Contact $contact): array => $this->makePairRow($contact, $contractor))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selected
     * @return array{contact_id: string|null, contractor_id: string, department_label: string|null, notes: string|null}
     */
    public function selectedToFormParty(array $selected): array
    {
        return [
            'contact_id' => filled($selected['contact_id'] ?? null) ? (string) $selected['contact_id'] : null,
            'contractor_id' => (string) ($selected['contractor_id'] ?? ''),
            'department_label' => filled($selected['preview']['department'] ?? null)
                ? (string) $selected['preview']['department']
                : null,
            'notes' => filled($selected['notes'] ?? null) ? (string) $selected['notes'] : null,
            'goes_on_trip' => (bool) ($selected['goes_on_trip'] ?? false),
        ];
    }

    private function pairKey(int $contactId, int $contractorId): string
    {
        return $contactId.'-'.$contractorId;
    }

    /**
     * Wybranie firmy jako zamawiającego uzupełnia typ „klient” (np. po „szukaj wszędzie”).
     */
    private function ensureClientTypeAttached(int $contractorId): void
    {
        $contractor = Contractor::query()->find($contractorId);

        if (! $contractor) {
            return;
        }

        $typeNames = \App\Models\ContractorType::clientTypeNames();
        $typeIds = \App\Models\ContractorType::idsForNames($typeNames);

        if ($typeIds === []) {
            foreach ($typeNames as $typeName) {
                $normalized = is_string($typeName) ? mb_strtolower(trim($typeName)) : '';
                if ($normalized === '') {
                    continue;
                }

                \App\Models\ContractorType::query()->firstOrCreate(['name' => $normalized]);
            }

            \App\Models\ContractorType::clearIdsForNamesCache();
            $typeIds = \App\Models\ContractorType::idsForNames($typeNames);
        }

        if ($typeIds !== []) {
            $contractor->types()->syncWithoutDetaching($typeIds);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    public function resultToOrderingParties(array $result): array
    {
        if (($result['type'] ?? '') === 'contact') {
            throw new \InvalidArgumentException('Wybierz firmę lub utwórz nowego kontrahenta dla osoby kontaktowej.');
        }

        $parties = [[
            'contact_id' => filled($result['contact_id'] ?? null) ? (string) $result['contact_id'] : null,
            'contractor_id' => (string) ($result['contractor_id'] ?? ''),
            'department_label' => filled($result['preview']['department'] ?? null)
                ? (string) $result['preview']['department']
                : null,
            'notes' => null,
        ]];

        $contractorId = filled($result['contractor_id'] ?? null) ? (int) $result['contractor_id'] : 0;

        if ($contractorId > 0) {
            $this->ensureClientTypeAttached($contractorId);
        }

        app(ContactContractorLinkService::class)->linkParties(
            collect($parties)
                ->map(fn (array $party): array => [
                    'contact_id' => filled($party['contact_id'] ?? null) ? (int) $party['contact_id'] : null,
                    'contractor_id' => filled($party['contractor_id'] ?? null) ? (int) $party['contractor_id'] : null,
                ])
                ->all()
        );

        return $parties;
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderingParties
     * @return array<string, mixed>
     */
    public function clientAttributesFromParties(array $orderingParties): array
    {
        $normalized = collect($orderingParties)
            ->map(fn (array $party): array => [
                'contact_id' => filled($party['contact_id'] ?? null) ? (int) $party['contact_id'] : null,
                'contractor_id' => (int) ($party['contractor_id'] ?? 0),
                'department_label' => filled($party['department_label'] ?? null)
                    ? trim((string) $party['department_label'])
                    : null,
            ])
            ->all();

        return app(EventOrderingPartyService::class)->primaryClientAttributes($normalized);
    }

    /**
     * Karta „Wybrany klient” na Podsumowaniu — z pierwszego zamawiającego lub legacy client_*.
     *
     * @return array<string, mixed>|null
     */
    public function selectedFromEvent(Event $event): ?array
    {
        $parties = app(EventOrderingPartyService::class)->partiesToFormState($event);
        $first = $parties[0] ?? null;

        if (is_array($first) && filled($first['contractor_id'] ?? null)) {
            $contractor = Contractor::query()->find((int) $first['contractor_id']);
            $contact = filled($first['contact_id'] ?? null)
                ? Contact::query()->find((int) $first['contact_id'])
                : null;
            $department = filled($first['department_label'] ?? null)
                ? (string) $first['department_label']
                : null;

            if ($contractor && $contact) {
                $row = $this->makePairRow($contact, $contractor, $department);
                $row['goes_on_trip'] = (bool) ($first['goes_on_trip'] ?? false);

                return $row;
            }

            if ($contractor) {
                $row = $this->makeContractorRow($contractor);
                if ($department !== null) {
                    $row['preview']['department'] = $department;
                    $row['label'] = app(EventOrderingPartyService::class)->formatPartyItemHeading(
                        null,
                        (int) $contractor->id,
                        $department,
                    );
                }
                $row['goes_on_trip'] = (bool) ($first['goes_on_trip'] ?? false);

                return $row;
            }
        }

        if (blank($event->client_name)) {
            return null;
        }

        return [
            'type' => 'legacy',
            'contact_id' => null,
            'contractor_id' => $event->contractor_id ? (int) $event->contractor_id : null,
            'label' => (string) $event->client_name,
            'notes' => null,
            'goes_on_trip' => true,
            'preview' => [
                'person' => null,
                'company' => (string) $event->client_name,
                'department' => null,
                'phone' => $event->client_phone,
                'email' => $event->client_email,
                'address' => null,
                'notes' => null,
            ],
        ];
    }
}

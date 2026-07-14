<?php

namespace App\Services;

use App\Filament\Forms\EventOrderingPartyFields;
use App\Models\Contact;
use App\Models\Contractor;
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
    public function search(array $criteria): Collection
    {
        $criteria = $this->normalizeCriteria($criteria);

        if (! $this->hasActiveCriteria($criteria)) {
            return collect();
        }

        $contacts = $this->searchContacts($criteria);
        $contractors = $this->searchContractors($criteria);

        $results = collect();
        $seenPairs = [];

        foreach ($contacts as $contact) {
            $contact->loadMissing('contractors');

            if ($contact->contractors->isNotEmpty()) {
                foreach ($contact->contractors as $contractor) {
                    $key = $this->pairKey($contact->id, $contractor->id);

                    if (isset($seenPairs[$key])) {
                        continue;
                    }

                    $seenPairs[$key] = true;
                    $results->push($this->makePairRow($contact, $contractor));
                }
            } else {
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
    public function searchFromQuery(string $query): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        return $this->searchUnified($query);
    }

    /**
     * Jedno wyszukiwanie po wszystkich polach (zamiast 6 osobnych LIKE).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function searchUnified(string $term): Collection
    {
        $like = '%'.$term.'%';

        $contacts = Contact::query()
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
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        $contractors = Contractor::query()
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

        $results = collect();
        $seenPairs = [];

        foreach ($contacts as $contact) {
            $contact->loadMissing('contractors');

            if ($contact->contractors->isNotEmpty()) {
                foreach ($contact->contractors as $contractor) {
                    $key = $this->pairKey($contact->id, $contractor->id);

                    if (isset($seenPairs[$key])) {
                        continue;
                    }

                    $seenPairs[$key] = true;
                    $results->push($this->makePairRow($contact, $contractor));
                }
            } else {
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

        $contractorId = EventOrderingPartyFields::createContractorFromFormData($contractorPayload);

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
    private function searchContacts(array $criteria): Collection
    {
        $query = Contact::query();

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
    private function searchContractors(array $criteria): Collection
    {
        $query = Contractor::query();

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
    private function makePairRow(Contact $contact, Contractor $contractor, ?string $departmentLabel = null): array
    {
        $service = app(EventOrderingPartyService::class);

        return [
            'type' => 'pair',
            'contact_id' => $contact->id,
            'contractor_id' => $contractor->id,
            'label' => $service->formatPartyLabel($contact->id, $contractor->id, $departmentLabel),
            'preview' => $this->buildPreview($contact, $contractor, $departmentLabel),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeContactRow(Contact $contact): array
    {
        return [
            'type' => 'contact',
            'contact_id' => $contact->id,
            'contractor_id' => null,
            'label' => app(EventOrderingPartyService::class)->formatContactLabel($contact),
            'preview' => $this->buildPreview($contact, null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeContractorRow(Contractor $contractor): array
    {
        return [
            'type' => 'contractor',
            'contact_id' => null,
            'contractor_id' => $contractor->id,
            'label' => app(EventOrderingPartyService::class)->formatContractorLabel($contractor),
            'preview' => $this->buildPreview(null, $contractor),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function buildPreview(?Contact $contact, ?Contractor $contractor, ?string $departmentLabel = null): array
    {
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
        ];
    }

    private function pairKey(int $contactId, int $contractorId): string
    {
        return $contactId.'-'.$contractorId;
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
        ]];

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
}

<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use Illuminate\Support\Facades\Schema;

class EventOrderingPartyService
{
    /**
     * @return array<int, array{contact_id: int|null, contractor_id: int|null, department_label: string|null, notes: string|null}>
     */
    public function partiesToFormState(Event $event): array
    {
        if (! Schema::hasTable('event_contractor')) {
            return [];
        }

        $event->loadMissing('orderingContractors');

        if ($event->orderingContractors->isNotEmpty()) {
            return $event->orderingContractors
                ->map(fn (Contractor $contractor): array => [
                    'contact_id' => Schema::hasColumn('event_contractor', 'contact_id')
                        ? ($contractor->pivot->contact_id ? (int) $contractor->pivot->contact_id : null)
                        : null,
                    'contractor_id' => (int) $contractor->id,
                    'department_label' => Schema::hasColumn('event_contractor', 'department_label')
                        ? ($contractor->pivot->department_label ?: null)
                        : null,
                    'notes' => Schema::hasColumn('event_contractor', 'notes')
                        ? ($contractor->pivot->notes ?: null)
                        : null,
                ])
                ->values()
                ->all();
        }

        if (filled($event->client_name)) {
            return [[
                'contact_id' => null,
                'contractor_id' => $event->contractor_id ? (int) $event->contractor_id : null,
                'department_label' => null,
                'notes' => null,
            ]];
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $parties
     */
    public function syncForEvent(Event $event, ?array $parties): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        $normalized = $this->normalizeParties($parties);

        app(ContactContractorLinkService::class)->linkParties($normalized);

        $event->orderingContractors()->detach();

        foreach ($normalized as $index => $party) {
            $pivot = ['sort_order' => $index + 1];

            if (Schema::hasColumn('event_contractor', 'contact_id')) {
                $pivot['contact_id'] = $party['contact_id'];
            }

            if (Schema::hasColumn('event_contractor', 'department_label')) {
                $pivot['department_label'] = $party['department_label'];
            }

            if (Schema::hasColumn('event_contractor', 'notes')) {
                $pivot['notes'] = $party['notes'];
            }

            $event->orderingContractors()->attach($party['contractor_id'], $pivot);
        }

        $event->syncPrimaryClientFromOrderingParties();
    }

    /**
     * @return array<int, string>
     */
    public function contactOptions(string $search = ''): array
    {
        return $this->contactOptionsForContractor(null, $search);
    }

    /**
     * Opcje Selectu „Osoba kontaktowa”.
     *
     * Gdy podano contractorId: najpierw kontakty tej firmy (preload bez szukania),
     * potem ewentualnie wyniki wyszukiwania w całej bazie (powiązane na górze).
     *
     * @return array<string, string>
     */
    public function contactOptionsForContractor(?int $contractorId, string $search = ''): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $linkedIds = collect();

        if ($contractorId && Contractor::hasContactPivotTable()) {
            $contractor = Contractor::query()->whereKey($contractorId)->first();

            if ($contractor) {
                $linkedIds = $contractor->contacts()
                    ->pluck('contacts.id')
                    ->map(fn ($id): int => (int) $id)
                    ->values();
            }
        }

        $query = Contact::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        } elseif ($linkedIds->isNotEmpty()) {
            // Bez wyszukiwania: tylko kontakty wybranej firmy (ładują się w preload).
            $query->whereIn('id', $linkedIds->all());
        }

        if ($linkedIds->isNotEmpty() && $search !== '') {
            $ids = $linkedIds->all();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query->orderByRaw('CASE WHEN id IN ('.$placeholders.') THEN 0 ELSE 1 END', $ids);
        }

        $query->orderBy('last_name')->orderBy('first_name');

        return $query
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Contact $contact): array => [
                (string) $contact->id => $this->formatContactLabel($contact),
            ])
            ->all();
    }

    /**
     * Opcje Selectu „Firma / instytucja”.
     *
     * Zawsze szuka w całej bazie kontrahentów (limit 50) — nie zawężamy do firm
     * już powiązanych z osobą. Powiązanie i tak powstaje w afterStateUpdated / sync.
     * Gdy podano contactId, powiązane firmy są tylko na górze listy (priorytet UX).
     *
     * @return array<string, string>
     */
    public function contractorOptionsForContact(?int $contactId, string $search = ''): array
    {
        $linkedIds = collect();

        if ($contactId && Contractor::hasContactPivotTable()) {
            $contact = Contact::query()->whereKey($contactId)->first();

            if ($contact) {
                $linkedIds = $contact->contractors()
                    ->pluck('contractors.id')
                    ->map(fn ($id): int => (int) $id)
                    ->values();
            }
        }

        $query = Contractor::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('nip', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($linkedIds->isNotEmpty()) {
            $ids = $linkedIds->all();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query->orderByRaw('CASE WHEN id IN ('.$placeholders.') THEN 0 ELSE 1 END', $ids);
        }

        $query->orderBy('name');

        return $query
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Contractor $contractor): array => [
                (string) $contractor->id => $this->formatContractorLabel($contractor),
            ])
            ->all();
    }

    public function createContact(array $data, ?int $contractorId = null): int
    {
        $contact = Contact::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        app(ContactContractorLinkService::class)->link((int) $contact->id, $contractorId);

        return (int) $contact->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $typeNames  typy do przypięcia (np. ['klient'])
     */
    public function createContractorFromFormData(array $data, ?int $contactId = null, array $typeNames = []): int
    {
        $contractor = Contractor::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'nip' => $data['nip'] ?? null,
            'street' => $data['street'] ?? null,
            'house_number' => $data['house_number'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'office_notes' => $data['office_notes'] ?? null,
            'status' => 'active',
        ]);

        $contractorId = (int) $contractor->getKey();

        if ($typeNames !== []) {
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

        app(ContactContractorLinkService::class)->link($contactId, $contractorId);

        return $contractorId;
    }

    public function formatPartyLabel(
        ?int $contactId,
        ?int $contractorId,
        ?string $departmentLabel = null,
        ?string $notes = null,
        bool $isPrimary = true,
    ): string {
        $contact = $contactId ? Contact::find($contactId) : null;
        $contractor = $contractorId ? Contractor::find($contractorId) : null;

        return $this->formatPartyLabelFromModels($contact, $contractor, $departmentLabel, $notes, $isPrimary);
    }

    public function formatPartyLabelFromModels(
        ?Contact $contact,
        ?Contractor $contractor,
        ?string $departmentLabel = null,
        ?string $notes = null,
        bool $isPrimary = true,
    ): string {
        $parts = [];

        if ($contractor) {
            $parts[] = $contractor->name;
        }

        if (filled($departmentLabel)) {
            $parts[] = (string) $departmentLabel;
        }

        if ($contact) {
            $parts[] = $contact->displayName();
        }

        if (filled($notes)) {
            $parts[] = \Illuminate\Support\Str::limit(trim((string) $notes), 40);
        }

        $body = $parts !== [] ? implode(' · ', $parts) : ($isPrimary ? 'Nowy zamawiający' : 'Nowy dodatkowy kontakt');

        return ($isPrimary ? 'Główny: ' : 'Dodatkowy: ').$body;
    }

    /**
     * Nagłówek wiersza w repeaterze (bez prefiksu roli — rolę pokazuje badge w wierszu).
     */
    public function formatPartyItemHeading(
        ?int $contactId,
        ?int $contractorId,
        ?string $departmentLabel = null,
        ?string $notes = null,
    ): string {
        $contact = $contactId ? Contact::find($contactId) : null;
        $contractor = $contractorId ? Contractor::find($contractorId) : null;

        $parts = [];

        if ($contractor) {
            $parts[] = $contractor->name;
        }

        if (filled($departmentLabel)) {
            $parts[] = (string) $departmentLabel;
        }

        if ($contact) {
            $parts[] = $contact->displayName();
        }

        if (filled($notes)) {
            $parts[] = \Illuminate\Support\Str::limit(trim((string) $notes), 40);
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Nowy kontakt';
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $parties
     * @return array<int, array{contact_id: int|null, contractor_id: int, department_label: string|null, notes: string|null}>
     */
    public function normalizeParties(?array $parties): array
    {
        return collect($parties ?? [])
            ->map(function (array $party): ?array {
                $contractorId = isset($party['contractor_id']) ? (int) $party['contractor_id'] : null;

                if (! $contractorId) {
                    return null;
                }

                return [
                    'contact_id' => filled($party['contact_id'] ?? null) ? (int) $party['contact_id'] : null,
                    'contractor_id' => $contractorId,
                    'department_label' => filled($party['department_label'] ?? null)
                        ? trim((string) $party['department_label'])
                        : null,
                    'notes' => filled($party['notes'] ?? null)
                        ? trim((string) $party['notes'])
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Walidacja przy tworzeniu imprezy: zamawiający z lifesearcha lub ręcznie z telefonem / e-mailem.
     *
     * @param  array<int, array<string, mixed>>|null  $parties
     * @return array<string, string>
     */
    public function validateForEventCreation(
        ?array $parties,
        ?string $clientName,
        ?string $clientPhone,
        ?string $clientEmail,
    ): array {
        $errors = [];
        $normalized = $this->normalizeParties($parties);

        if ($normalized === []) {
            $errors['ordering_parties'] = 'Wybierz zamawiającego z wyszukiwarki lub użyj szybkiego wprowadzenia.';
        }

        if (blank($clientName)) {
            $errors['client_name'] = 'Podaj dane zamawiającego przed zapisem imprezy.';
        }

        $phone = trim((string) ($clientPhone ?? ''));
        $email = trim((string) ($clientEmail ?? ''));

        if ($phone === '' && $email === '') {
            $errors['client_contact'] = 'Podaj telefon lub e-mail zamawiającego.';
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $parties
     */
    public function primaryClientAttributes(?array $parties): array
    {
        $first = $this->normalizeParties($parties)[0] ?? null;

        if (! $first) {
            return [];
        }

        $contractor = Contractor::find($first['contractor_id']);
        $contact = $first['contact_id'] ? Contact::find($first['contact_id']) : null;

        if (! $contractor && ! $contact) {
            return [];
        }

        $companyName = $contractor?->name ?? '';
        $department = $first['department_label'];

        $clientName = match (true) {
            $contact && filled($department) => $contact->displayName().' · '.$department.' ('.$companyName.')',
            $contact && filled($companyName) => $contact->displayName().' · '.$companyName,
            $contact => $contact->displayName(),
            filled($department) && filled($companyName) => $department.' · '.$companyName,
            default => $companyName,
        };

        $payload = [
            'client_name' => $clientName,
            'client_email' => $contact?->email ?? $contractor?->email,
            'client_phone' => $contact?->phone ?? $contractor?->phone,
        ];

        if (Schema::hasColumn('events', 'contractor_id') && $contractor) {
            $payload['contractor_id'] = $contractor->id;
        }

        return $payload;
    }

    public function formatContactLabel(Contact $contact): string
    {
        $details = collect([$contact->phone, $contact->email])
            ->filter()
            ->map(fn ($value): string => trim((string) $value))
            ->implode(' · ');

        return $details !== ''
            ? $contact->displayName().' · '.$details
            : $contact->displayName();
    }

    public function formatContractorLabel(Contractor $contractor): string
    {
        $details = collect(\App\Support\ContractorContactDetails::displayLines($contractor))->implode(' · ');

        return $details !== ''
            ? $contractor->displayLabel().' · '.$details
            : $contractor->displayLabel();
    }
}

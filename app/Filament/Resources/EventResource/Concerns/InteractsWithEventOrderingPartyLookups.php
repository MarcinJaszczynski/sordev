<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Models\Event;
use App\Services\EventOrderingPartyService;
use Livewire\Attributes\On;

/**
 * Lookup zamawiających (główny + dodatkowi) jest poza repeaterem Filament.
 * Repeater ma klucze UUID i bywa pusty w requeście zagnieżdżonego Livewire —
 * wtedy dodatkowy kontakt nadpisywał głównego przy zapisie.
 */
trait InteractsWithEventOrderingPartyLookups
{
    /**
     * Kanoniczna lista zamawiających z lookupu (indeks 0 = główny).
     * null = nie ruszana w tej sesji, bierzemy formularz / rekord.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $lookupOrderingParties = null;

    /**
     * @param  array<int, array<string, mixed>>  $orderingParties
     */
    #[On('client-lookup-applied')]
    public function applyClientLookup(
        array $orderingParties,
        string $clientName = '',
        ?string $clientEmail = null,
        ?string $clientPhone = null,
    ): void {
        $additional = collect(array_slice($this->resolvedOrderingParties(), 1))
            ->map(function (array $row): array {
                $row['goes_on_trip'] = false;

                return $row;
            })
            ->all();
        $primary = $this->orderingPartiesFromRepeater($orderingParties)[0] ?? null;

        $this->syncLookupOrderingParties($primary
            ? array_merge([$primary], $additional)
            : $additional);
        $this->data['client_name'] = $clientName;
        $this->data['client_email'] = $clientEmail;
        $this->data['client_phone'] = $clientPhone;
    }

    #[On('client-lookup-cleared')]
    public function clearClientLookup(): void
    {
        $additional = array_slice($this->resolvedOrderingParties(), 1);

        $this->syncLookupOrderingParties($additional);
        $this->data['client_name'] = null;
        $this->data['client_email'] = null;
        $this->data['client_phone'] = null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $additionalParties
     */
    #[On('additional-ordering-parties-updated')]
    public function applyAdditionalOrderingParties(array $additionalParties): void
    {
        $primary = $this->resolvedOrderingParties()[0] ?? null;
        $additional = $this->orderingPartiesFromRepeater($additionalParties);

        $this->syncLookupOrderingParties($primary
            ? array_merge([$primary], $additional)
            : $additional);
    }

    #[On('ordering-party-trip-contact-set')]
    public function applyTripContactSelection(int $partyIndex): void
    {
        $parties = $this->resolvedOrderingParties();

        foreach (array_keys($parties) as $index) {
            $parties[$index]['goes_on_trip'] = $index === $partyIndex;
        }

        $this->syncLookupOrderingParties($parties);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parties
     */
    protected function seedLookupOrderingParties(array $parties): void
    {
        $normalized = $this->orderingPartiesFromRepeater($parties);
        $this->lookupOrderingParties = $normalized === [] ? null : $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resolvedOrderingParties(): array
    {
        if (is_array($this->lookupOrderingParties)) {
            return array_values($this->lookupOrderingParties);
        }

        $fromForm = $this->orderingPartiesFromRepeater($this->data['ordering_parties'] ?? []);
        if ($fromForm !== []) {
            return $fromForm;
        }

        if (isset($this->record) && $this->record instanceof Event && $this->record->exists) {
            return array_values(app(EventOrderingPartyService::class)->partiesToFormState($this->record));
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parties
     */
    protected function syncLookupOrderingParties(array $parties): void
    {
        $this->lookupOrderingParties = array_values($this->orderingPartiesFromRepeater($parties));
        $this->data['ordering_parties'] = $this->lookupOrderingParties;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function orderingPartiesFromRepeater(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        // Livewire czasem przekazuje pojedynczy wiersz zamiast listy wierszy.
        if ($this->isOrderingPartyRow($state)) {
            $state = [$state];
        }

        return collect($state)
            ->filter(fn (mixed $row): bool => $this->isOrderingPartyRow($row))
            ->map(fn (array $row): array => [
                'contact_id' => $row['contact_id'] ?? null,
                'contractor_id' => $row['contractor_id'],
                'department_label' => $row['department_label'] ?? null,
                'notes' => $row['notes'] ?? null,
                'goes_on_trip' => (bool) ($row['goes_on_trip'] ?? false),
            ])
            ->values()
            ->all();
    }

    protected function isOrderingPartyRow(mixed $row): bool
    {
        return is_array($row) && filled($row['contractor_id'] ?? null);
    }
}

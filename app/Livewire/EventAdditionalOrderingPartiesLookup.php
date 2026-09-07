<?php

namespace App\Livewire;

use App\Services\ClientLookupService;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pełny lookup dodatkowych zamawiających — karty jak główny klient + kontakty firmy bez globalnego szukania.
 */
class EventAdditionalOrderingPartiesLookup extends Component
{
    public string $searchQuery = '';

    public bool $showResults = false;

    public bool $showQuickCreate = false;

    public bool $showAddPanel = false;

    public bool $searchAll = false;

    /** Nowa firma z typem „klient” zamiast dopisania osoby do firmy głównego zamawiającego. */
    public bool $createAsNewCompany = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $companyName = '';

    public string $notes = '';

    public ?int $primaryContractorId = null;

    /** Indeks edytowanego dodatkowego kontaktu (jak „Zmień” u głównego). */
    public ?int $editingIndex = null;

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    /** @var array<int, array<string, mixed>> */
    public array $companyContacts = [];

    /** @var array<int, array<string, mixed>> */
    public array $additional = [];

    /**
     * @param  array<int, array<string, mixed>>  $additional
     */
    public function mount(array $additional = [], ?int $primaryContractorId = null): void
    {
        $this->additional = array_values($additional);
        $this->primaryContractorId = $primaryContractorId;
        $this->refreshCompanyContacts();
    }

    public function updatedSearchQuery(): void
    {
        $this->refreshResults();
    }

    public function updatedSearchAll(): void
    {
        $this->refreshResults();
    }

    public function openAddPanel(): void
    {
        $this->editingIndex = null;
        $this->showAddPanel = true;
        $this->showQuickCreate = false;
        $this->resetQuickCreateFields();
        $this->refreshCompanyContacts();
        $this->refreshResults();
    }

    /**
     * Jak „Zmień” u głównego: ukrywa kartę i otwiera pełny wybór na to miejsce.
     */
    public function changeAdditional(int $index): void
    {
        if (! isset($this->additional[$index])) {
            return;
        }

        $current = $this->additional[$index];
        $this->editingIndex = $index;
        $this->showAddPanel = true;
        $this->showQuickCreate = false;
        $this->showResults = false;
        $this->searchQuery = '';
        $this->results = [];
        $this->resetQuickCreateFields();
        $this->notes = filled($current['notes'] ?? null) ? (string) $current['notes'] : '';
        $this->refreshCompanyContacts();
    }

    public function closeAddPanel(): void
    {
        $this->showAddPanel = false;
        $this->showQuickCreate = false;
        $this->showResults = false;
        $this->searchQuery = '';
        $this->results = [];
        $this->editingIndex = null;
        $this->resetQuickCreateFields();
    }

    public function refreshCompanyContacts(): void
    {
        if (! $this->primaryContractorId) {
            $this->companyContacts = [];

            return;
        }

        $alreadyContactIds = collect($this->additional)
            ->reject(fn (array $row, int $index): bool => $this->editingIndex !== null && $index === $this->editingIndex)
            ->map(fn (array $row): ?int => filled($row['contact_id'] ?? null) ? (int) $row['contact_id'] : null)
            ->filter()
            ->values()
            ->all();

        $this->companyContacts = collect(app(ClientLookupService::class)
            ->companyContactsForQuickPick($this->primaryContractorId))
            ->reject(fn (array $row): bool => in_array((int) ($row['contact_id'] ?? 0), $alreadyContactIds, true))
            ->values()
            ->all();
    }

    public function refreshResults(): void
    {
        $this->showQuickCreate = false;

        if (! $this->showAddPanel) {
            $this->results = [];
            $this->showResults = false;

            return;
        }

        $query = trim($this->searchQuery);

        if (mb_strlen($query) < ClientLookupService::MIN_QUERY_LENGTH) {
            $this->results = [];
            $this->showResults = false;

            return;
        }

        $this->results = app(ClientLookupService::class)
            ->searchFromQuery($query, $this->searchAll)
            ->all();

        $this->showResults = true;
    }

    public function openQuickCreate(bool $asNewCompany = false): void
    {
        $this->createAsNewCompany = $asNewCompany || $this->primaryContractorId === null;
        $this->showQuickCreate = true;
        $this->showResults = false;

        $query = trim($this->searchQuery);

        if ($query === '') {
            return;
        }

        if (filter_var($query, FILTER_VALIDATE_EMAIL)) {
            $this->email = $query;

            return;
        }

        if (preg_match('/^[\d\s+\-()]+$/', $query)) {
            $this->phone = $query;

            return;
        }

        $parts = preg_split('/\s+/', $query, 2) ?: [];

        if (count($parts) === 2) {
            $this->firstName = $parts[0];
            $this->lastName = $parts[1];
        } else {
            $this->companyName = $query;
        }
    }

    public function selectCompanyContact(int $index): void
    {
        $result = $this->companyContacts[$index] ?? null;

        if (! is_array($result)) {
            return;
        }

        $this->addSelectedResult($result);
    }

    public function selectResult(int $index): void
    {
        $result = $this->results[$index] ?? null;

        if (! is_array($result)) {
            return;
        }

        if (($result['type'] ?? '') === 'contact') {
            Notification::make()
                ->title('Wybierz firmę')
                ->body('Ta osoba nie ma przypisanej firmy. Użyj szybkiego wprowadzenia lub wybierz wynik z firmą.')
                ->warning()
                ->send();

            return;
        }

        $this->addSelectedResult($result);
    }

    public function quickCreate(): void
    {
        $phone = trim($this->phone);
        $email = trim($this->email);

        if ($phone === '' && $email === '') {
            Notification::make()
                ->title('Uzupełnij dane kontaktu')
                ->body('Podaj telefon lub e-mail.')
                ->warning()
                ->send();

            return;
        }

        $lookup = app(ClientLookupService::class);

        $attachToPrimary = $this->primaryContractorId !== null && ! $this->createAsNewCompany;

        // Domyślnie dopisz osobę do firmy głównego zamawiającego. Przy „nowa firma” — typ klient.
        if ($attachToPrimary) {
            $firstName = trim($this->firstName);
            $lastName = trim($this->lastName);

            if ($firstName === '' && $lastName === '') {
                Notification::make()
                    ->title('Uzupełnij dane kontaktu')
                    ->body('Podaj imię lub nazwisko osoby kontaktowej.')
                    ->warning()
                    ->send();

                return;
            }

            $contactId = app(\App\Services\EventOrderingPartyService::class)->createContact([
                'first_name' => $firstName !== '' ? $firstName : '—',
                'last_name' => $lastName !== '' ? $lastName : '—',
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ], $this->primaryContractorId);

            $contact = \App\Models\Contact::query()->find($contactId);
            $contractor = \App\Models\Contractor::query()->find($this->primaryContractorId);

            if (! $contact || ! $contractor) {
                Notification::make()
                    ->title('Nie udało się dodać kontaktu')
                    ->danger()
                    ->send();

                return;
            }

            $notes = trim($this->notes);
            $selected = $lookup->makePairRow(
                $contact,
                $contractor,
                null,
                $notes !== '' ? $notes : null,
            );

            $replacing = $this->editingIndex !== null;
            $this->pushAdditional($selected);
            $this->refreshCompanyContacts();
            $this->closeAddPanel();

            Notification::make()
                ->title($replacing ? 'Dodatkowy kontakt zmieniony' : 'Dodatkowy kontakt dodany')
                ->body($replacing
                    ? 'Zaktualizowano dodatkowy kontakt.'
                    : 'Dodano osobę do firmy głównego zamawiającego.')
                ->success()
                ->send();

            return;
        }

        try {
            $payload = $lookup->quickCreate([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'company_name' => $this->companyName,
            ]);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Uzupełnij dane kontaktu')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $selected = $payload['selected'];
        $notes = trim($this->notes);
        $selected['notes'] = $notes !== '' ? $notes : null;
        if ($selected['notes']) {
            $selected['preview']['notes'] = $selected['notes'];
        }

        $replacing = $this->editingIndex !== null;
        $this->pushAdditional($selected);
        $this->closeAddPanel();

        Notification::make()
            ->title($replacing ? 'Dodatkowy kontakt zmieniony' : 'Dodatkowy kontakt dodany')
            ->body('Utworzono nową firmę z typem „klient”.')
            ->success()
            ->send();
    }

    public function updateNotes(int $index, string $notes): void
    {
        if (! isset($this->additional[$index])) {
            return;
        }

        $notes = trim($notes);
        $this->additional[$index]['notes'] = $notes !== '' ? $notes : null;
        $this->additional[$index]['preview']['notes'] = $notes !== '' ? $notes : null;
        $this->dispatchAdditionalUpdated();
    }

    public function setAsTripContact(int $index): void
    {
        if (! isset($this->additional[$index])) {
            return;
        }

        foreach ($this->additional as $i => &$row) {
            $row['goes_on_trip'] = $i === $index;
        }

        $this->dispatch('ordering-party-trip-contact-set', partyIndex: $index + 1);
        $this->dispatchAdditionalUpdated();
    }

    #[On('ordering-party-trip-contact-set')]
    public function onTripContactSet(int $partyIndex): void
    {
        foreach ($this->additional as $i => &$row) {
            $row['goes_on_trip'] = $partyIndex === ($i + 1);
        }
    }

    public function removeAdditional(int $index): void
    {
        if (! isset($this->additional[$index])) {
            return;
        }

        if ($this->editingIndex === $index) {
            $this->closeAddPanel();
        } elseif ($this->editingIndex !== null && $this->editingIndex > $index) {
            $this->editingIndex--;
        }

        unset($this->additional[$index]);
        $this->additional = array_values($this->additional);
        $this->dispatchAdditionalUpdated();
        $this->refreshCompanyContacts();
    }

    #[On('client-lookup-applied')]
    public function onPrimaryApplied(
        array $orderingParties = [],
        string $clientName = '',
        ?string $clientEmail = null,
        ?string $clientPhone = null,
    ): void {
        $primary = $orderingParties[0] ?? null;
        $this->primaryContractorId = filled($primary['contractor_id'] ?? null)
            ? (int) $primary['contractor_id']
            : null;
        $this->refreshCompanyContacts();
    }

    #[On('client-lookup-cleared')]
    public function onPrimaryCleared(): void
    {
        $this->primaryContractorId = null;
        $this->companyContacts = [];
    }

    #[On('client-lookup-reset')]
    public function resetLookup(): void
    {
        $this->reset([
            'searchQuery',
            'showResults',
            'showQuickCreate',
            'showAddPanel',
            'searchAll',
            'createAsNewCompany',
            'firstName',
            'lastName',
            'phone',
            'email',
            'address',
            'companyName',
            'notes',
            'results',
            'companyContacts',
            'additional',
            'primaryContractorId',
            'editingIndex',
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function addSelectedResult(array $result): void
    {
        try {
            app(ClientLookupService::class)->resultToOrderingParties($result);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Nie można dodać kontaktu')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        if (trim($this->notes) !== '') {
            $result['notes'] = trim($this->notes);
            $result['preview']['notes'] = trim($this->notes);
        } elseif (
            $this->editingIndex !== null
            && filled($this->additional[$this->editingIndex]['notes'] ?? null)
            && ! array_key_exists('notes', $result)
        ) {
            $result['notes'] = $this->additional[$this->editingIndex]['notes'];
            $result['preview']['notes'] = $result['notes'];
        }

        if ($this->isDuplicate($result)) {
            Notification::make()
                ->title('Kontakt już dodany')
                ->body('Ta para firma + osoba jest już na liście dodatkowych kontaktów.')
                ->warning()
                ->send();

            return;
        }

        $this->pushAdditional($result);
        $this->closeAddPanel();
    }

    /**
     * @param  array<string, mixed>  $selected
     */
    private function pushAdditional(array $selected): void
    {
        if ($this->editingIndex !== null && isset($this->additional[$this->editingIndex])) {
            $this->additional[$this->editingIndex] = $selected;
        } else {
            $this->additional[] = $selected;
        }

        $this->additional = array_values($this->additional);
        $this->dispatchAdditionalUpdated();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isDuplicate(array $result): bool
    {
        $contactId = filled($result['contact_id'] ?? null) ? (int) $result['contact_id'] : null;
        $contractorId = filled($result['contractor_id'] ?? null) ? (int) $result['contractor_id'] : null;

        foreach ($this->additional as $index => $row) {
            if ($this->editingIndex !== null && $index === $this->editingIndex) {
                continue;
            }

            $rowContact = filled($row['contact_id'] ?? null) ? (int) $row['contact_id'] : null;
            $rowContractor = filled($row['contractor_id'] ?? null) ? (int) $row['contractor_id'] : null;

            if ($rowContact === $contactId && $rowContractor === $contractorId) {
                return true;
            }
        }

        return false;
    }

    private function dispatchAdditionalUpdated(): void
    {
        $parties = collect($this->additional)
            ->map(fn (array $row): array => app(ClientLookupService::class)->selectedToFormParty($row))
            ->values()
            ->all();

        $this->dispatch('additional-ordering-parties-updated', additionalParties: $parties);
    }

    private function resetQuickCreateFields(): void
    {
        $this->firstName = '';
        $this->lastName = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->companyName = '';
        $this->notes = '';
        $this->createAsNewCompany = false;
    }

    public function render()
    {
        return view('livewire.event-additional-ordering-parties-lookup');
    }
}

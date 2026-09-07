<?php

namespace App\Livewire;

use App\Services\ClientLookupService;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class EventClientLookup extends Component
{
    public string $searchQuery = '';

    public bool $showResults = false;

    public bool $showQuickCreate = false;

    public bool $searchAll = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $companyName = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    /** @var array<string, mixed>|null */
    public ?array $selected = null;

    /**
     * @param  array<string, mixed>|null  $selected
     */
    public function mount(?array $selected = null): void
    {
        if (is_array($selected) && $selected !== []) {
            $this->selected = $selected;
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->refreshResults();
    }

    public function updatedSearchAll(): void
    {
        $this->refreshResults();
    }

    public function refreshResults(): void
    {
        $this->showQuickCreate = false;

        if ($this->selected !== null) {
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

    public function openQuickCreate(): void
    {
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

        try {
            $orderingParties = app(ClientLookupService::class)->resultToOrderingParties($result);
            if (isset($orderingParties[0])) {
                $orderingParties[0]['goes_on_trip'] = true;
            }
            $clientAttributes = app(ClientLookupService::class)->clientAttributesFromParties($orderingParties);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Nie można wybrać klienta')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->selected = $result;
        $this->selected['goes_on_trip'] = true;
        $this->searchQuery = '';
        $this->results = [];
        $this->showResults = false;
        $this->showQuickCreate = false;
        $this->dispatchSelection($orderingParties, $clientAttributes);
    }

    public function quickCreate(): void
    {
        try {
            $payload = app(ClientLookupService::class)->quickCreate([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'company_name' => $this->companyName,
            ]);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Uzupełnij dane klienta')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->selected = $payload['selected'];
        $this->selected['goes_on_trip'] = true;
        if (isset($payload['ordering_parties'][0])) {
            $payload['ordering_parties'][0]['goes_on_trip'] = true;
        }
        $this->searchQuery = '';
        $this->results = [];
        $this->showResults = false;
        $this->showQuickCreate = false;
        $this->dispatchSelection(
            $payload['ordering_parties'],
            [
                'client_name' => $payload['client_name'],
                'client_email' => $payload['client_email'],
                'client_phone' => $payload['client_phone'],
            ],
        );

        Notification::make()
            ->title('Zamawiający wybrany')
            ->body('Klient jest już w bazie i przypięty do formularza. Możesz zapisać imprezę.')
            ->success()
            ->send();
    }

    public function clearSelection(): void
    {
        $this->selected = null;
        $this->searchQuery = '';
        $this->results = [];
        $this->showResults = false;
        $this->showQuickCreate = false;
        // Bez ->to(): CreateEvent i EditEvent nasłuchują #[On] na tej samej stronie.
        $this->dispatch('client-lookup-cleared');
    }

    #[On('client-lookup-reset')]
    public function resetLookup(): void
    {
        $this->reset([
            'searchQuery',
            'showResults',
            'showQuickCreate',
            'searchAll',
            'firstName',
            'lastName',
            'phone',
            'email',
            'address',
            'companyName',
            'results',
            'selected',
        ]);
    }

    public function setAsTripContact(): void
    {
        if (! is_array($this->selected)) {
            return;
        }

        $this->selected['goes_on_trip'] = true;
        $this->dispatch('ordering-party-trip-contact-set', partyIndex: 0);
    }

    #[On('ordering-party-trip-contact-set')]
    public function onTripContactSet(int $partyIndex): void
    {
        if (! is_array($this->selected)) {
            return;
        }

        $this->selected['goes_on_trip'] = $partyIndex === 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderingParties
     * @param  array<string, mixed>  $clientAttributes
     */
    private function dispatchSelection(array $orderingParties, array $clientAttributes): void
    {
        $this->dispatch(
            'client-lookup-applied',
            orderingParties: $orderingParties,
            clientName: (string) ($clientAttributes['client_name'] ?? ''),
            clientEmail: $clientAttributes['client_email'] ?? null,
            clientPhone: $clientAttributes['client_phone'] ?? null,
        );
    }

    public function render()
    {
        return view('livewire.event-client-lookup');
    }
}

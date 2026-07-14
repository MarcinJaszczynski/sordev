<?php

namespace App\Livewire;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventBusCollection;
use App\Support\MoneyFormatter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventBusCollections extends Component
{
    public Event $event;

    public bool $readOnly = false;

    public string $title = 'Zbiórka gotówki w autokarze';

    public string $collectedAt = '';

    public string $unitAmount = '';

    public ?int $currencyId = null;

    public string $participantCount = '';

    public string $notes = '';

    public function mount(Event $event, bool $readOnly = false): void
    {
        abort_unless(Auth::user()?->can('view', $event), 403);

        $this->event = $event;
        $this->readOnly = $readOnly;
        $this->currencyId = Currency::defaultPlnId();
        $this->collectedAt = now()->format('Y-m-d\\TH:i');
    }

    public function computedTotalAmount(): ?float
    {
        $unit = (float) $this->unitAmount;
        $count = (int) $this->participantCount;

        if ($unit <= 0 || $count <= 0) {
            return null;
        }

        return round($unit * $count, 2);
    }

    public function addCollection(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->validate([
            'title' => 'required|string|max:255',
            'collectedAt' => 'required|date',
            'unitAmount' => 'required|numeric|min:0.01',
            'currencyId' => 'required|exists:currencies,id',
            'participantCount' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ]);

        $unit = round((float) $this->unitAmount, 2);
        $count = (int) $this->participantCount;
        $total = round($unit * $count, 2);

        EventBusCollection::create([
            'event_id' => $this->event->id,
            'title' => $this->title,
            'collected_at' => $this->collectedAt,
            'amount' => $total,
            'amount_per_person' => $unit,
            'currency_id' => $this->currencyId,
            'participant_count' => $count,
            'notes' => filled($this->notes) ? $this->notes : null,
            'status' => 'collected',
        ]);

        $this->reset(['title', 'unitAmount', 'participantCount', 'notes']);
        $this->title = 'Zbiórka gotówki w autokarze';
        $this->collectedAt = now()->format('Y-m-d\\TH:i');

        Notification::make()->title('Zbiórka zapisana')->success()->send();
    }

    public function updateStatus(int $id, string $status): void
    {
        if ($this->readOnly) {
            return;
        }

        if (! array_key_exists($status, EventBusCollection::$statuses)) {
            return;
        }

        $collection = EventBusCollection::query()
            ->where('event_id', $this->event->id)
            ->findOrFail($id);

        $collection->update(['status' => $status]);

        Notification::make()->title('Status zaktualizowany')->success()->send();
    }

    public function deleteCollection(int $id): void
    {
        if ($this->readOnly) {
            return;
        }

        EventBusCollection::query()
            ->where('event_id', $this->event->id)
            ->where('id', $id)
            ->delete();

        Notification::make()->title('Zbiórka usunięta')->success()->send();
    }

    public function render()
    {
        $collections = EventBusCollection::query()
            ->where('event_id', $this->event->id)
            ->with(['currency', 'recorder'])
            ->orderByDesc('collected_at')
            ->get();

        $selectedCurrency = $this->currencyId
            ? Currency::query()->find($this->currencyId)
            : null;

        return view('livewire.event-bus-collections', [
            'collections' => $collections,
            'currencyOptions' => Currency::filamentSelectOptions(),
            'computedTotal' => $this->computedTotalAmount(),
            'currency' => $selectedCurrency,
            'money' => fn (?float $amount, ?Currency $currency): string => MoneyFormatter::format(
                $amount,
                $currency?->symbol ?? 'PLN',
            ),
        ]);
    }
}

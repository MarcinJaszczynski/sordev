<?php

namespace App\Livewire;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventBusCollection;
use App\Models\EventPaymentInstallmentTemplate;
use App\Support\MoneyFormatter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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

    /** Informacja skąd wzięto domyślne wartości (UI). */
    public string $defaultsHint = '';

    public function mount(Event $event, bool $readOnly = false): void
    {
        abort_unless(Auth::user()?->can('view', $event), 403);

        $this->event = $event;
        $this->readOnly = $readOnly;
        $this->applyEventDefaults();
    }

    /**
     * Wstępne wartości z imprezy: pojemność, waluta→pilot z harmonogramu, data startu.
     * Cennik jest podpowiedzią — po zapisie nie nadpisuje wpisanej kwoty.
     */
    public function applyEventDefaults(): void
    {
        $this->fillFieldsFromEventDefaults();
        $this->refreshDefaultsHint();
        $this->notes = '';
    }

    protected function fillFieldsFromEventDefaults(): void
    {
        $count = $this->resolveDefaultParticipantCount();
        $this->participantCount = $count > 0 ? (string) $count : '';

        $pilotDue = $this->resolvePilotCurrencyDue();
        if ($pilotDue !== null) {
            $this->unitAmount = (string) $pilotDue['amount'];
            $this->currencyId = $pilotDue['currency_id'];
            $this->title = $pilotDue['title'];
        } else {
            $this->currencyId = Currency::defaultPlnId();
            $this->unitAmount = '';
            $this->title = 'Zbiórka gotówki w autokarze';
        }

        $this->collectedAt = $this->resolveDefaultCollectedAt();
    }

    protected function refreshDefaultsHint(): void
    {
        $hints = [];

        $count = $this->resolveDefaultParticipantCount();
        if ($count > 0) {
            $hints[] = $count.' os. (pojemność imprezy)';
        }

        $pilotDue = $this->resolvePilotCurrencyDue();
        if ($pilotDue !== null) {
            $hints[] = number_format($pilotDue['amount'], 2, ',', ' ').' '.$pilotDue['currency_code'].'/os. (cennik — tylko podpowiedź)';
        }

        if ($this->event->start_date) {
            $hints[] = 'data: start imprezy '.$this->event->start_date->format('d.m.Y');
        }

        $this->defaultsHint = $hints !== []
            ? 'Podpowiedź z imprezy: '.implode(' · ', $hints)
            : '';
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

    public function addPlan(): void
    {
        $this->storeCollection(EventBusCollection::STATUS_PLANNED);
    }

    public function addCollection(): void
    {
        $this->storeCollection(EventBusCollection::STATUS_COLLECTED);
    }

    public function markCollected(int $id): void
    {
        if ($this->readOnly) {
            return;
        }

        $collection = EventBusCollection::query()
            ->where('event_id', $this->event->id)
            ->where('status', EventBusCollection::STATUS_PLANNED)
            ->findOrFail($id);

        $collection->update([
            'status' => EventBusCollection::STATUS_COLLECTED,
            'collected_at' => $collection->collected_at ?? now(),
            'planned_amount' => $collection->planned_amount ?? $collection->amount,
        ]);

        $this->notifyCashDesk();

        Notification::make()->title('Oznaczono jako zebrane — zasila saldo pilota')->success()->send();
    }

    public function updateStatus(int $id, string $status): void
    {
        if ($this->readOnly) {
            return;
        }

        if (! array_key_exists($status, EventBusCollection::$statuses)) {
            return;
        }

        if ($status === EventBusCollection::STATUS_PLANNED) {
            return;
        }

        $collection = EventBusCollection::query()
            ->where('event_id', $this->event->id)
            ->findOrFail($id);

        $collection->update(['status' => $status]);

        $this->notifyCashDesk();

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

        $this->notifyCashDesk();

        Notification::make()->title('Zbiórka usunięta')->success()->send();
    }

    protected function storeCollection(string $status): void
    {
        if ($this->readOnly) {
            return;
        }

        $rules = [
            'title' => 'required|string|max:255',
            'unitAmount' => 'required|numeric|min:0.01',
            'currencyId' => 'required|exists:currencies,id',
            'participantCount' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ];

        if ($status === EventBusCollection::STATUS_COLLECTED) {
            $rules['collectedAt'] = 'required|date';
        } else {
            $rules['collectedAt'] = 'nullable|date';
        }

        $this->validate($rules);

        $unit = round((float) $this->unitAmount, 2);
        $count = (int) $this->participantCount;
        $total = round($unit * $count, 2);

        EventBusCollection::create([
            'event_id' => $this->event->id,
            'title' => $this->title,
            'collected_at' => filled($this->collectedAt) ? $this->collectedAt : (
                $status === EventBusCollection::STATUS_COLLECTED ? now() : null
            ),
            'amount' => $total,
            'planned_amount' => $total,
            'amount_per_person' => $unit,
            'currency_id' => $this->currencyId,
            'participant_count' => $count,
            'notes' => filled($this->notes) ? $this->notes : null,
            'status' => $status,
        ]);

        $this->notes = '';
        $this->refreshDefaultsHint();
        $this->notifyCashDesk();

        Notification::make()
            ->title($status === EventBusCollection::STATUS_PLANNED
                ? 'Plan zbiórki zapisany'
                : 'Zbiórka zapisana — zasila saldo pilota')
            ->success()
            ->send();
    }

    protected function notifyCashDesk(): void
    {
        $this->dispatch('bus-collections-updated', eventId: $this->event->id);
    }

    protected function resolveDefaultParticipantCount(): int
    {
        $capacity = (int) ($this->event->participant_count ?? 0);
        if ($capacity > 0) {
            return $capacity;
        }

        if (method_exists($this->event, 'activeParticipants')) {
            return (int) $this->event->activeParticipants()->count();
        }

        return 0;
    }

    /**
     * @return array{amount: float, currency_id: int, currency_code: string, title: string}|null
     */
    protected function resolvePilotCurrencyDue(): ?array
    {
        if (Schema::hasTable('event_payment_installment_templates')) {
            $row = EventPaymentInstallmentTemplate::query()
                ->where('event_id', $this->event->id)
                ->where('paid_by', EventPaymentInstallmentTemplate::PAID_BY_PILOT)
                ->where('share_type', EventPaymentInstallmentTemplate::SHARE_FOREIGN)
                ->whereNotNull('amount_foreign')
                ->where('amount_foreign', '>', 0)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($row) {
                $code = strtoupper((string) ($row->currency_code ?: 'EUR'));
                $currencyId = $this->resolveCurrencyIdByCode($code);
                if ($currencyId) {
                    return [
                        'amount' => round((float) $row->amount_foreign, 2),
                        'currency_id' => $currencyId,
                        'currency_code' => $code,
                        'title' => filled($row->label) ? (string) $row->label : 'Zbiórka '.$code.' w autokarze',
                    ];
                }
            }
        }

        try {
            $foreign = app(\App\Services\EventPriceSummaryService::class)
                ->forEvent($this->event, includeNearest: false)['foreign_prices'] ?? [];
            $first = is_array($foreign) && $foreign !== [] ? $foreign[0] : null;
            if (is_array($first) && (float) ($first['price_per_person'] ?? 0) > 0) {
                $code = strtoupper((string) ($first['currency'] ?? 'EUR'));
                $currencyId = $this->resolveCurrencyIdByCode($code);
                if ($currencyId) {
                    return [
                        'amount' => round((float) $first['price_per_person'], 2),
                        'currency_id' => $currencyId,
                        'currency_code' => $code,
                        'title' => 'Zbiórka '.$code.' w autokarze',
                    ];
                }
            }
        } catch (\Throwable) {
            // Brak cennika / serwisu — zostaw puste pola.
        }

        return null;
    }

    protected function resolveCurrencyIdByCode(string $code): ?int
    {
        return Currency::query()
            ->where(function ($q) use ($code): void {
                $q->where('code', $code)->orWhere('symbol', $code);
            })
            ->orderBy('id')
            ->value('id');
    }

    protected function resolveDefaultCollectedAt(): string
    {
        if ($this->event->start_date) {
            return $this->event->start_date->copy()->startOfDay()->setTime(8, 0)->format('Y-m-d\\TH:i');
        }

        return now()->format('Y-m-d\\TH:i');
    }

    public function render()
    {
        $collections = EventBusCollection::query()
            ->where('event_id', $this->event->id)
            ->with(['currency', 'recorder'])
            ->orderByRaw("CASE status WHEN 'planned' THEN 0 WHEN 'collected' THEN 1 WHEN 'handed_to_office' THEN 2 ELSE 3 END")
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get();

        $selectedCurrency = $this->currencyId
            ? Currency::query()->find($this->currencyId)
            : null;

        $totalsByCurrency = $collections
            ->groupBy(fn (EventBusCollection $row) => (int) $row->currency_id)
            ->map(function ($rows) {
                /** @var \Illuminate\Support\Collection<int, EventBusCollection> $rows */
                $first = $rows->first();

                return [
                    'currency' => $first?->currency,
                    'planned' => round((float) $rows->where('status', EventBusCollection::STATUS_PLANNED)->sum('amount'), 2),
                    'held' => round((float) $rows->whereIn('status', EventBusCollection::HELD_STATUSES)->sum('amount'), 2),
                    'handed' => round((float) $rows->whereIn('status', [
                        EventBusCollection::STATUS_HANDED_TO_OFFICE,
                        EventBusCollection::STATUS_CONFIRMED,
                    ])->sum('amount'), 2),
                ];
            })
            ->values();

        return view('livewire.event-bus-collections', [
            'collections' => $collections,
            'currencyOptions' => Currency::filamentSelectOptions(),
            'computedTotal' => $this->computedTotalAmount(),
            'currency' => $selectedCurrency,
            'totalsByCurrency' => $totalsByCurrency,
            'money' => fn (?float $amount, ?Currency $currency): string => MoneyFormatter::format(
                $amount,
                $currency?->symbol ?? 'PLN',
            ),
        ]);
    }
}

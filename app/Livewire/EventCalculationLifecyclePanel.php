<?php

namespace App\Livewire;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use App\Models\Event;
use App\Models\EventCalculationStage;
use App\Services\EventCalculationLifecycle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventCalculationLifecyclePanel extends Component
{
    public Event $event;

    /** Biuro/administracja może ręcznie zamrażać ceny etapów. */
    public bool $canManage = false;

    /** Aktualnie edytowany etap (preliminary|predicted) lub null. */
    public ?string $editingStage = null;

    public ?string $editClientPrice = null;

    public ?string $editCost = null;

    public ?string $editNote = null;

    public function mount(Event $event): void
    {
        $user = Auth::user();
        abort_unless($user?->can('view', $event) || $user?->hasRole(['admin', 'super_admin', 'biuro']), 403);

        $this->event = $event;
        $this->canManage = (bool) $user?->hasRole(['admin', 'super_admin', 'biuro']);
    }

    public function startEdit(string $stage): void
    {
        if (! $this->canManage || ! array_key_exists($stage, EventCalculationStage::$stages)) {
            return;
        }

        $lock = $this->event->calculationStage($stage);

        $this->editingStage = $stage;
        $this->editClientPrice = $lock?->client_price_pln !== null ? (string) (float) $lock->client_price_pln : null;
        $this->editCost = $lock?->cost_pln !== null ? (string) (float) $lock->cost_pln : null;
        $this->editNote = $lock?->note;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingStage', 'editClientPrice', 'editCost', 'editNote']);
    }

    public function saveStage(): void
    {
        if (! $this->canManage || ! $this->editingStage) {
            return;
        }

        // Puste pole = brak ręcznej wartości (przywrócenie wyliczenia).
        $this->editClientPrice = $this->editClientPrice === '' ? null : $this->editClientPrice;
        $this->editCost = $this->editCost === '' ? null : $this->editCost;

        $data = $this->validate([
            'editClientPrice' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'editCost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'editNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $clientPrice = $data['editClientPrice'] === null || $data['editClientPrice'] === ''
            ? null
            : round((float) $data['editClientPrice'], 2);
        $cost = $data['editCost'] === null || $data['editCost'] === ''
            ? null
            : round((float) $data['editCost'], 2);

        if ($clientPrice === null && $cost === null) {
            // Nic ręcznego — usuń ewentualne zamrożenie.
            $this->event->calculationStages()->where('stage', $this->editingStage)->delete();
        } else {
            $this->event->calculationStages()->updateOrCreate(
                ['stage' => $this->editingStage],
                [
                    'client_price_pln' => $clientPrice,
                    'cost_pln' => $cost,
                    'note' => $data['editNote'] ?: null,
                    'locked_by' => Auth::id(),
                    'locked_at' => now(),
                ],
            );
        }

        $this->event->load('calculationStages');
        $this->cancelEdit();

        Notification::make()->title('Zapisano wartości etapu')->success()->send();
    }

    public function clearStage(string $stage): void
    {
        if (! $this->canManage) {
            return;
        }

        $this->event->calculationStages()->where('stage', $stage)->delete();
        $this->event->load('calculationStages');

        Notification::make()->title('Przywrócono wartości wyliczone')->success()->send();
    }

    public function render()
    {
        $life = EventCalculationLifecycle::for($this->event)->build();
        $settlementId = $life['reconciliation']['settlement_id'] ?? null;

        return view('livewire.event-calculation-lifecycle-panel', [
            'life' => $life,
            'settlementUrl' => $settlementId
                ? EventSettlementResource::getUrl('edit', ['record' => $settlementId])
                : null,
            'resignationsUrl' => EventResource::getUrl('participant-resignations', ['record' => $this->event->id]),
        ]);
    }
}

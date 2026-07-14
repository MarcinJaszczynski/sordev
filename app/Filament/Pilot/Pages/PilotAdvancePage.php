<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Livewire\Concerns\HandlesPilotExpenseLedger;
use App\Models\Event;
use App\Services\PilotAccessService;
use App\Services\PilotAdvanceService;
use App\Services\PilotSettlementService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PilotAdvancePage extends Page
{
    use AuthorizesPilotTrip;
    use HandlesPilotExpenseLedger;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-advance-page';

    protected static ?string $slug = 'advance/{event}';

    public Event $event;

    public ?int $exchangeFromCurrencyId = null;

    public ?int $exchangeToCurrencyId = null;

    public string $exchangeFromAmount = '';

    public string $exchangeToAmount = '';

    public string $exchangeRate = '';

    public string $exchangeNotes = '';

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->event = $event->load(['assignedUser', 'pilotAdvanceLines.currency', 'pilotAdvancePaidCurrency']);
        $this->mountExpenseLedgerState();
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'advance';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Zaliczka: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    public function recordCurrencyExchange(): void
    {
        $this->validate([
            'exchangeFromCurrencyId' => 'required|exists:currencies,id',
            'exchangeToCurrencyId' => 'required|exists:currencies,id|different:exchangeFromCurrencyId',
            'exchangeFromAmount' => 'required|numeric|min:0.01',
            'exchangeToAmount' => 'required|numeric|min:0.01',
            'exchangeRate' => 'nullable|numeric|min:0.00001',
            'exchangeNotes' => 'nullable|string|max:2000',
        ], [
            'exchangeFromCurrencyId.required' => 'Wybierz walutę źródłową.',
            'exchangeToCurrencyId.required' => 'Wybierz walutę docelową.',
            'exchangeToCurrencyId.different' => 'Waluty wymiany muszą być różne.',
            'exchangeFromAmount.required' => 'Podaj kwotę wydaną przy wymianie.',
            'exchangeToAmount.required' => 'Podaj kwotę otrzymaną przy wymianie.',
        ]);

        try {
            app(PilotSettlementService::class)->recordCurrencyExchange($this->event, [
                'from_currency_id' => $this->exchangeFromCurrencyId,
                'to_currency_id' => $this->exchangeToCurrencyId,
                'from_amount' => $this->exchangeFromAmount,
                'to_amount' => $this->exchangeToAmount,
                'exchange_rate' => $this->exchangeRate !== '' ? $this->exchangeRate : null,
                'notes' => $this->exchangeNotes !== '' ? $this->exchangeNotes : null,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->reset([
            'exchangeFromAmount',
            'exchangeToAmount',
            'exchangeRate',
            'exchangeNotes',
        ]);
        $this->loadCashReportingFields();

        Notification::make()
            ->title('Zapisano wymianę walut')
            ->body('Wymiana nie jest kosztem — zmienia saldo gotówki w poszczególnych walutach.')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $advanceService = app(PilotAdvanceService::class);
        $settlementService = app(PilotSettlementService::class);
        $settlement = $settlementService->getOrCreateSettlement($this->event);

        return [
            'archiveMessage' => app(PilotAccessService::class)->archiveMessage($this->event),
            'readOnly' => ! app(PilotAccessService::class)->hasFullAccess($this->event, Auth::user()),
            'plannedLines' => $advanceService->plannedLines($this->event),
            'paidLines' => $advanceService->paidLines($this->event),
            'currencyExchanges' => $settlementService->currencyExchanges($this->event),
            'showCurrencyExchange' => $this->event->showsPilotCurrencyExchange(),
            'settlementEditable' => $settlement->isEditableByPilot(),
            'settlementUrl' => PilotSettlementPage::settleUrl($this->event),
        ];
    }
}

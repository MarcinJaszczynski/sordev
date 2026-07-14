<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Services\ClientAccessService;
use App\Services\ParticipantPaymentBalanceService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ClientMyPaymentsPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-my-payments-page';

    protected static ?string $slug = 'my-payments/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        abort_unless(app(ClientAccessService::class)->isParticipant(Auth::user(), $event), 403);

        $this->event = $event;
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'my_payments';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Moje wpłaty: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $payment = app(ClientAccessService::class)->participantPayment($user, $this->event);
        $balance = $payment
            ? app(ParticipantPaymentBalanceService::class)->balanceRow($payment->loadMissing('contracts.paymentSchedules'))
            : null;

        return [
            'event' => $this->event,
            'payment' => $payment,
            'balance' => $balance,
            'archiveMessage' => app(ClientAccessService::class)->archiveMessage($this->event),
        ];
    }
}

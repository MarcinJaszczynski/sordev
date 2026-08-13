<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Legacy redirect → ClientPaymentsPage.
 */
class ClientMyPaymentsPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'my-payments/{event}';

    protected static string $view = 'filament.client.pages.client-payments-page';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->redirect(ClientPaymentsPage::urlFor($event), navigate: false);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Moje wpłaty';
    }

    public static function urlFor(Event $event): string
    {
        return ClientPaymentsPage::urlFor($event);
    }
}

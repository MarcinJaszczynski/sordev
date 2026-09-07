<?php

namespace App\Filament\Client\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ClientAgreementPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-agreement-page';

    protected static ?string $slug = 'agreement/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        $this->event = $event;
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'agreement';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('umowa', 'portal'),
        ];
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $service = app(ClientAccessService::class);
        $role = $service->isGuardian($user, $this->event)
            ? EventPortalAccess::ROLE_GUARDIAN
            : EventPortalAccess::ROLE_PARTICIPANT;
        $contract = $service->accessibleContract($user, $this->event, $role);

        return [
            'event' => $this->event,
            'contract' => $contract,
            'archiveMessage' => app(ClientAccessService::class)->archiveMessage($this->event),
            'pdfUrl' => $contract
                ? route('portal.contract.pdf', ['event' => $this->event->id])
                : null,
        ];
    }
}

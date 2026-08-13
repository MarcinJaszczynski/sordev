<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Forms\ClientInvoiceRequestFormFields;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\Concerns\ResolvesEventSettlement;
use App\Filament\Resources\EventSettlementResource\RelationManagers\ParticipantPaymentsRelationManager;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Support\ClientInvoiceRequestAdminHelper;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Kanoniczny UI wpłat uczestników — zakładka w hubie Finanse (+ wnioski o fakturę).
 */
class EventFinanceParticipantPayments extends Page
{
    use HasEventFinanceSubNavigation;
    use HasRelationManagers;
    use InteractsWithEventRecord;
    use ResolvesEventSettlement;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-participant-payments';

    protected static ?string $navigationLabel = 'Wpłaty';

    protected static ?string $title = 'Wpłaty / Faktury';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public ?int $focusPaymentId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $this->resolveEventSettlement($this->getRecord());

        $paymentId = request()->integer('payment');
        $this->focusPaymentId = $paymentId > 0 ? $paymentId : null;
    }

    protected function currentEvent(): Event
    {
        /** @var Event $event */
        $event = $this->record;

        return $event;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ClientInvoiceRequest>
     */
    public function invoiceRequests()
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            return collect();
        }

        return ClientInvoiceRequest::query()
            ->where('event_id', $this->currentEvent()->id)
            ->with(['processedByUser:id,name', 'salesInvoices'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            ParticipantPaymentsRelationManager::class,
        ];
    }

    public function getRelationManagers(): array
    {
        $managers = [];

        foreach ($this->getAllRelationManagers() as $manager) {
            $managers[$manager] = $manager;
        }

        return $managers;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('wplaty-i-linki'),
            Actions\Action::make('create_invoice_request')
                ->label('Wniosek o fakturę')
                ->icon('heroicon-o-receipt-percent')
                ->color('primary')
                ->visible(fn (): bool => Schema::hasTable('client_invoice_requests'))
                ->modalHeading(fn (): string => 'Wniosek o fakturę — '.$this->currentEvent()->name)
                ->modalDescription('Formularz dla biura — wniosek trafi do skrzynki wniosków o fakturę.')
                ->modalIcon('heroicon-o-receipt-percent')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Zapisz wniosek')
                ->fillForm(fn (): array => ClientInvoiceRequestAdminHelper::prefillFromEvent($this->currentEvent()))
                ->form(fn (): array => ClientInvoiceRequestFormFields::adminModalSchema(
                    lockedEventId: (int) $this->currentEvent()->id,
                    event: $this->currentEvent(),
                ))
                ->action(function (array $data): void {
                    ClientInvoiceRequestAdminHelper::createFromAdminForm(
                        $data,
                        linkedUser: auth()->user(),
                    );

                    Notification::make()
                        ->title('Utworzono wniosek o fakturę')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function getResourcePageName(): string
    {
        foreach (EventResource::getPages() as $pageName => $pageRegistration) {
            if ($pageRegistration->getPage() !== static::class) {
                continue;
            }

            return $pageName;
        }

        throw new \Exception('Page ['.static::class.'] is not registered to the resource ['.EventResource::class.'].');
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}

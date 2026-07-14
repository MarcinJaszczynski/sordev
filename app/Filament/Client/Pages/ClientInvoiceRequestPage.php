<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\ClientInvoiceRequest;
use App\Models\EventPortalAccess;
use App\Models\Event;
use App\Services\ClientAccessService;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ClientInvoiceRequestPage extends Page implements HasForms
{
    use AuthorizesClientTrip;
    use HasClientTripNav;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-invoice-request-page';

    protected static ?string $slug = 'invoice-request/{event}';

    public Event $event;

    public ?array $data = [];

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        $service = app(ClientAccessService::class);
        $user = Auth::user();

        abort_unless(
            $service->isGuardian($user, $event) || $service->isParticipant($user, $event),
            403,
        );

        $this->event = $event;
        $contract = $this->invoiceContractFor($user);

        $this->form->fill([
            'company_name' => $contract?->customer_name ?: $contract?->signer_name,
            'invoice_email' => $user?->email,
            'payment_reference' => $contract?->agreement_number,
        ]);
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'invoice_request';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Wniosek o fakturę: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('company_name')
                    ->label('Nazwa firmy / instytucji')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('nip')
                    ->label('NIP')
                    ->required()
                    ->maxLength(16),
                Forms\Components\TextInput::make('street')
                    ->label('Ulica')
                    ->maxLength(255),
                Forms\Components\TextInput::make('house_number')
                    ->label('Nr domu / lokalu')
                    ->maxLength(32),
                Forms\Components\TextInput::make('postal_code')
                    ->label('Kod pocztowy')
                    ->maxLength(16),
                Forms\Components\TextInput::make('city')
                    ->label('Miasto')
                    ->maxLength(120),
                Forms\Components\TextInput::make('invoice_email')
                    ->label('E-mail do faktury')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('payment_reference')
                    ->label('Referencja płatności / umowy')
                    ->maxLength(120),
                Forms\Components\TextInput::make('amount')
                    ->label('Kwota do zafakturowania')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\Textarea::make('notes')
                    ->label('Uwagi')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function submit(): void
    {
        $payload = $this->form->getState();
        $user = Auth::user();
        $contract = $this->invoiceContractFor($user);

        ClientInvoiceRequest::query()->create([
            'event_id' => $this->event->id,
            'contract_id' => $contract?->id,
            'user_id' => $user->id,
            'company_name' => $payload['company_name'],
            'nip' => $payload['nip'],
            'street' => $payload['street'] ?? null,
            'house_number' => $payload['house_number'] ?? null,
            'postal_code' => $payload['postal_code'] ?? null,
            'city' => $payload['city'] ?? null,
            'invoice_email' => $payload['invoice_email'],
            'payment_reference' => $payload['payment_reference'] ?? null,
            'amount' => filled($payload['amount'] ?? null) ? $payload['amount'] : null,
            'notes' => $payload['notes'] ?? null,
            'status' => ClientInvoiceRequest::STATUS_PENDING,
        ]);

        Notification::make()
            ->title('Wniosek wysłany')
            ->body('Biuro otrzymało Twój wniosek o fakturę.')
            ->success()
            ->send();

        $this->form->fill([
            'company_name' => $payload['company_name'],
            'nip' => '',
            'street' => '',
            'house_number' => '',
            'postal_code' => '',
            'city' => '',
            'invoice_email' => $payload['invoice_email'],
            'payment_reference' => '',
            'amount' => null,
            'notes' => '',
        ]);
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'event' => $this->event,
            'requests' => ClientInvoiceRequest::query()
                ->where('event_id', $this->event->id)
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'archiveMessage' => app(ClientAccessService::class)->archiveMessage($this->event),
        ];
    }

    protected function invoiceContractFor(User $user): ?\App\Models\Contract
    {
        $service = app(ClientAccessService::class);

        if ($service->isParticipant($user, $this->event)) {
            return $service->accessibleContract($user, $this->event, EventPortalAccess::ROLE_PARTICIPANT);
        }

        return $service->accessibleContract($user, $this->event, EventPortalAccess::ROLE_GUARDIAN);
    }
}

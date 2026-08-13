<?php

namespace App\Livewire;

use App\Filament\Client\Resources\ClientEventResource;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Models\User;
use App\Services\ClientPortalProvisioningService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class ClientPortalSettingsToolbar extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        return view('livewire.client-portal-settings-toolbar', [
            'event' => $this->event(),
            'accesses' => $this->activeAccesses(),
        ]);
    }

    public function inviteParticipantAction(): Action
    {
        return Action::make('inviteParticipant')
            ->label('Zaproś uczestnika')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (): bool => Schema::hasTable('event_portal_accesses'))
            ->form([
                Forms\Components\Select::make('event_participant_id')
                    ->label('Uczestnik')
                    ->options(fn (): array => $this->participantOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?int $state): void {
                        if (! $state || ! Schema::hasTable('event_participants')) {
                            return;
                        }

                        $participant = EventParticipant::query()->find($state);
                        $set('email', $participant?->email);
                        $set('name', $participant?->fullName());
                        $set('contract_id', $participant?->contract_id);
                    }),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Imię i nazwisko')
                    ->maxLength(255),
                Forms\Components\Select::make('contract_id')
                    ->label('Umowa')
                    ->options(fn (): array => $this->contractOptions())
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                $this->grantAccess(
                    role: EventPortalAccess::ROLE_PARTICIPANT,
                    email: $data['email'],
                    name: $data['name'] ?? null,
                    contractId: $data['contract_id'] ?? null,
                    participantId: $data['event_participant_id'] ?? null,
                );
            });
    }

    public function inviteGuardianAction(): Action
    {
        return Action::make('inviteGuardian')
            ->label('Zaproś opiekuna grupy')
            ->icon('heroicon-o-users')
            ->color('success')
            ->visible(fn (): bool => Schema::hasTable('event_portal_accesses'))
            ->form([
                Forms\Components\Select::make('contract_id')
                    ->label('Umowa grupowa')
                    ->options(fn (): array => $this->groupContractOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?int $state): void {
                        if (! $state || ! Schema::hasTable('contracts')) {
                            return;
                        }

                        $contract = Contract::query()->find($state);
                        $set('email', $contract?->signer_email ?: $contract?->customer_email);
                        $set('name', $contract?->signer_name ?: $contract?->customer_name);
                    }),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail opiekuna')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Imię i nazwisko')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->grantAccess(
                    role: EventPortalAccess::ROLE_GUARDIAN,
                    email: $data['email'],
                    name: $data['name'] ?? null,
                    contractId: $data['contract_id'] ?? null,
                );
            });
    }

    public function revokeAccessAction(): Action
    {
        return Action::make('revokeAccess')
            ->label('Cofnij dostęp')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (): bool => $this->activeAccesses()->isNotEmpty())
            ->form([
                Forms\Components\Select::make('access_id')
                    ->label('Dostęp do cofnięcia')
                    ->options(fn (): array => $this->activeAccesses()
                        ->mapWithKeys(fn (EventPortalAccess $access): array => [
                            $access->id => $this->accessLabel($access),
                        ])
                        ->all())
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (array $data): void {
                $access = EventPortalAccess::query()->find($data['access_id']);

                if (! $access || (int) $access->event_id !== $this->eventId) {
                    return;
                }

                app(ClientPortalProvisioningService::class)->revokeAccess($access);

                Notification::make()
                    ->title('Dostęp cofnięty')
                    ->success()
                    ->send();

                $this->dispatch('$refresh');
            });
    }

    protected function grantAccess(
        string $role,
        string $email,
        ?string $name,
        ?int $contractId,
        ?int $participantId = null,
    ): void {
        $event = $this->event();
        $plainPassword = Str::password(12);
        $roleName = $role === EventPortalAccess::ROLE_GUARDIAN ? 'client_guardian' : 'client_participant';

        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $user = User::query()->where('email', $email)->first();

        $isNew = false;
        if (! $user) {
            $user = User::query()->create([
                'name' => $name ?: Str::before($email, '@'),
                'email' => $email,
                'password' => bcrypt($plainPassword),
                'status' => 'active',
            ]);
            $isNew = true;
        }

        app(ClientPortalProvisioningService::class)->grantAccess(
            event: $event,
            user: $user,
            role: $role,
            contractId: $contractId,
            eventParticipantId: $participantId,
            sharedBy: Auth::id(),
            sendCredentials: $isNew,
            plainPassword: $isNew ? $plainPassword : null,
        );

        Notification::make()
            ->title('Udostępniono portal klienta')
            ->body($isNew ? 'Wysłano dane logowania na e-mail.' : 'Wysłano powiadomienie o wycieczce.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    protected function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }

    protected function activeAccesses()
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            return collect();
        }

        return EventPortalAccess::query()
            ->with(['user', 'contract'])
            ->active()
            ->where('event_id', $this->eventId)
            ->orderBy('role')
            ->orderBy('shared_at')
            ->get();
    }

    protected function participantOptions(): array
    {
        if (! Schema::hasTable('event_participants')) {
            return [];
        }

        return EventParticipant::query()
            ->where('event_id', $this->eventId)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (EventParticipant $p): array => [
                $p->id => trim($p->first_name.' '.$p->last_name).' · '.($p->email ?: 'brak e-mail'),
            ])
            ->all();
    }

    protected function contractOptions(): array
    {
        if (! Schema::hasTable('contracts')) {
            return [];
        }

        return Contract::query()
            ->where('event_id', $this->eventId)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Contract $c): array => [
                $c->id => ($c->agreement_number ?: '#'.$c->id).' · '.($c->participant_name ?: $c->signer_name ?: $c->customer_name ?: 'umowa'),
            ])
            ->all();
    }

    protected function groupContractOptions(): array
    {
        if (! Schema::hasTable('contracts')) {
            return [];
        }

        return Contract::query()
            ->where('event_id', $this->eventId)
            ->where('contract_type', Contract::TYPE_GROUP)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Contract $c): array => [
                $c->id => ($c->agreement_number ?: '#'.$c->id).' · '.($c->customer_name ?: 'umowa grupowa'),
            ])
            ->all();
    }

    protected function accessLabel(EventPortalAccess $access): string
    {
        $role = $access->role === EventPortalAccess::ROLE_GUARDIAN ? 'opiekun' : 'uczestnik';

        return ($access->user?->email ?: '—').' ('.$role.')';
    }

    public function previewUrl(): string
    {
        return ClientEventResource::getUrl('view', ['record' => $this->eventId], panel: 'portal').'?preview=1';
    }

    public function previewAsAccessUrl(EventPortalAccess $access): string
    {
        return ClientEventResource::getUrl('view', ['record' => $this->eventId], panel: 'portal')
            .'?preview=1&access='.$access->id;
    }
}

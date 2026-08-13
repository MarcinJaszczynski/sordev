<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Kompaktowy pasek gotowości klienta na hubie wycieczki.
 */
final class ClientTripReadinessService
{
    /**
     * @return list<array{key: string, label: string, status: 'ok'|'warn'|'na', detail: string, url: ?string}>
     */
    public function items(User $user, Event $event): array
    {
        $access = app(ClientAccessService::class);
        $isGuardian = $access->isGuardian($user, $event);
        $isParticipant = $access->isParticipant($user, $event);
        $contract = $access->accessibleContract($user, $event)
            ?? $access->accessibleContract($user, $event, EventPortalAccess::ROLE_GUARDIAN)
            ?? $access->accessibleContract($user, $event, EventPortalAccess::ROLE_PARTICIPANT);

        $items = [];

        $items[] = $this->agreementItem($contract, $event);
        if ($isParticipant || $isGuardian) {
            $items[] = $this->paymentItem($contract, $event);
        }
        if ($isGuardian) {
            $items[] = $this->participantsItem($user, $event);
        }
        if ($isParticipant || $isGuardian) {
            $items[] = $this->extrasItem($contract, $event);
            if (Schema::hasTable('client_trip_inquiries')) {
                $items[] = [
                    'key' => 'contact',
                    'label' => 'Kontakt',
                    'status' => 'na',
                    'detail' => 'Napisz do biura, gdy potrzebujesz pomocy',
                    'url' => \App\Filament\Client\Pages\ClientContactPage::urlFor($event),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warn'|'na', detail: string, url: ?string}
     */
    private function agreementItem(?Contract $contract, Event $event): array
    {
        $url = \App\Filament\Client\Pages\ClientAgreementPage::urlFor($event);
        if (! $contract) {
            return [
                'key' => 'agreement',
                'label' => 'Umowa',
                'status' => 'warn',
                'detail' => 'Brak powiązanej umowy',
                'url' => $url,
            ];
        }

        $status = (string) ($contract->status ?? '');
        $ok = in_array($status, ['signed', 'completed'], true);

        return [
            'key' => 'agreement',
            'label' => 'Umowa',
            'status' => $ok ? 'ok' : 'warn',
            'detail' => $ok ? 'Podpisana' : (Contract::$statuses[$status] ?? $status ?: 'W toku'),
            'url' => $url,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warn'|'na', detail: string, url: ?string}
     */
    private function paymentItem(?Contract $contract, Event $event): array
    {
        $url = \App\Filament\Client\Pages\ClientPaymentsPage::urlFor($event);
        if (! $contract) {
            return [
                'key' => 'payment',
                'label' => 'Płatność',
                'status' => 'na',
                'detail' => '—',
                'url' => $url,
            ];
        }

        $balance = app(ParticipantPaymentBalanceService::class)->forContract($contract);
        $remaining = (float) ($balance['remaining_pln'] ?? 0);
        $due = (float) ($balance['due_pln'] ?? 0);

        if ($due <= 0.009) {
            return [
                'key' => 'payment',
                'label' => 'Płatność',
                'status' => 'na',
                'detail' => 'Brak należności',
                'url' => $url,
            ];
        }

        return [
            'key' => 'payment',
            'label' => 'Płatność',
            'status' => $remaining <= 0.009 ? 'ok' : 'warn',
            'detail' => $remaining <= 0.009
                ? 'Opłacona'
                : ('Pozostało '.number_format($remaining, 2, ',', ' ').' PLN'),
            'url' => $url,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warn'|'na', detail: string, url: ?string}
     */
    private function participantsItem(User $user, Event $event): array
    {
        $url = \App\Filament\Client\Pages\ClientParticipantsPage::urlFor($event);
        $participants = app(ClientAccessService::class)
            ->guardianParticipantsQuery($user, $event)
            ->get();
        $total = $participants->count();
        if ($total === 0) {
            return [
                'key' => 'participants',
                'label' => 'Uczestnicy',
                'status' => 'warn',
                'detail' => 'Brak listy',
                'url' => $url,
            ];
        }

        $withConsent = $participants->filter(fn ($p) => $p->hasParentConsent())->count();
        $ok = $withConsent === $total;

        return [
            'key' => 'participants',
            'label' => 'Uczestnicy',
            'status' => $ok ? 'ok' : 'warn',
            'detail' => $withConsent.'/'.$total.' ze zgodami',
            'url' => $url,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warn'|'na', detail: string, url: ?string}
     */
    private function extrasItem(?Contract $contract, Event $event): array
    {
        $url = \App\Filament\Client\Pages\ClientExtrasPage::urlFor($event);
        if (! $contract) {
            return [
                'key' => 'extras',
                'label' => 'Świadczenia',
                'status' => 'na',
                'detail' => '—',
                'url' => $url,
            ];
        }

        $catalog = app(ContractExtrasSurchargeService::class)->presentation($contract);
        if ($catalog === []) {
            return [
                'key' => 'extras',
                'label' => 'Świadczenia',
                'status' => 'na',
                'detail' => 'Brak katalogu',
                'url' => $url,
            ];
        }

        return [
            'key' => 'extras',
            'label' => 'Świadczenia',
            'status' => 'ok',
            'detail' => count($catalog).' pozycji w ofercie',
            'url' => $url,
        ];
    }
}

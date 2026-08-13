<?php

namespace App\Support;

use App\Filament\Client\Pages\ClientAgreementPage;
use App\Filament\Client\Pages\ClientContactPage;
use App\Filament\Client\Pages\ClientExtrasPage;
use App\Filament\Client\Pages\ClientGroupPaymentsPage;
use App\Filament\Client\Pages\ClientInvoiceRequestPage;
use App\Filament\Client\Pages\ClientParticipantsPage;
use App\Filament\Client\Pages\ClientPaymentsPage;
use App\Filament\Client\Pages\ClientProgramPage;
use App\Filament\Client\Resources\ClientEventResource;
use App\Models\Event;
use App\Services\ClientAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-nawigacja workspace wycieczki w portalu klienta.
 */
final class ClientTripModuleNavigation
{
    /** @return array<int, array{key: string, label: string, description?: string, url: string, icon?: string, active?: bool}> */
    public static function tabs(Event $event, ?string $active = null): array
    {
        $user = Auth::user();
        $service = app(ClientAccessService::class);
        $fullAccess = $service->hasFullAccess($event, $user);
        $isGuardian = $user && $service->isGuardian($user, $event);
        $isParticipant = $user && $service->isParticipant($user, $event);

        $tabs = [
            [
                'key' => 'info',
                'label' => 'Informacje',
                'url' => ClientEventResource::getUrl('view', ['record' => $event->id]),
                'icon' => 'heroicon-o-information-circle',
            ],
        ];

        if ($fullAccess) {
            $tabs[] = [
                'key' => 'program',
                'label' => 'Program',
                'url' => ClientProgramPage::urlFor($event),
                'icon' => 'heroicon-o-calendar-days',
            ];
            $tabs[] = [
                'key' => 'agreement',
                'label' => 'Umowa',
                'url' => ClientAgreementPage::urlFor($event),
                'icon' => 'heroicon-o-document-text',
            ];

            if ($isParticipant || $isGuardian) {
                $tabs[] = [
                    'key' => 'payments',
                    'label' => 'Płatności',
                    'url' => ClientPaymentsPage::urlFor($event),
                    'icon' => 'heroicon-o-banknotes',
                ];
            }

            if ($isParticipant) {
                $tabs[] = [
                    'key' => 'invoice_request',
                    'label' => 'Faktura',
                    'url' => ClientInvoiceRequestPage::urlFor($event),
                    'icon' => 'heroicon-o-receipt-percent',
                ];
            }

            if ($isGuardian) {
                $tabs[] = [
                    'key' => 'participants',
                    'label' => 'Uczestnicy',
                    'url' => ClientParticipantsPage::urlFor($event),
                    'icon' => 'heroicon-o-user-group',
                ];
                $tabs[] = [
                    'key' => 'group_payments',
                    'label' => 'Wpłaty grupy',
                    'url' => ClientGroupPaymentsPage::urlFor($event),
                    'icon' => 'heroicon-o-users',
                ];
                if (! $isParticipant) {
                    $tabs[] = [
                        'key' => 'invoice_request',
                        'label' => 'Faktura',
                        'url' => ClientInvoiceRequestPage::urlFor($event),
                        'icon' => 'heroicon-o-receipt-percent',
                    ];
                }
            }

            if (($isParticipant || $isGuardian) && Schema::hasTable('client_trip_inquiries')) {
                $tabs[] = [
                    'key' => 'contact',
                    'label' => 'Kontakt',
                    'url' => ClientContactPage::urlFor($event),
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                ];
            }

            if ($isParticipant || $isGuardian) {
                $tabs[] = [
                    'key' => 'extras',
                    'label' => 'Świadczenia',
                    'url' => ClientExtrasPage::urlFor($event),
                    'icon' => 'heroicon-o-sparkles',
                ];
            }
        }

        if ($active !== null) {
            $tabs = array_map(function (array $tab) use ($active): array {
                $tab['active'] = $tab['key'] === $active;

                return $tab;
            }, $tabs);
        }

        return $tabs;
    }
}

<?php

namespace App\Support;

use App\Filament\Pilot\Pages\PilotAdvancePage;
use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Pilot\Pages\PilotHotelPlanPage;
use App\Filament\Pilot\Pages\PilotProgramPage;
use App\Filament\Pilot\Pages\PilotSettlementPage;
use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Services\PilotAccessService;
use Illuminate\Support\Facades\Auth;

/**
 * Sub-nawigacja workspace wycieczki pilota.
 */
final class PilotTripModuleNavigation
{
    /** @return array<int, array{key: string, label: string, description?: string, url: string, icon?: string, active?: bool}> */
    public static function tabs(Event $event, ?string $active = null): array
    {
        $user = Auth::user();
        $fullAccess = app(PilotAccessService::class)->hasFullAccess($event, $user);

        $tabs = [
            [
                'key' => 'info',
                'label' => 'Informacje',
                'description' => 'Teczka wycieczki',
                'url' => PilotEventResource::getUrl('view', ['record' => $event->id]),
                'icon' => 'heroicon-o-information-circle',
            ],
        ];

        if ($fullAccess) {
            $tabs[] = [
                'key' => 'program',
                'label' => 'Program',
                'description' => 'Plan dnia po dniu',
                'url' => PilotProgramPage::urlFor($event),
                'icon' => 'heroicon-o-calendar-days',
            ];

            $tabs[] = [
                'key' => 'checklist',
                'label' => 'Checklista',
                'description' => 'Zadania przed wyjazdem',
                'url' => PilotChecklistPage::urlFor($event),
                'icon' => 'heroicon-o-clipboard-document-check',
            ];

            $tabs[] = [
                'key' => 'advance',
                'label' => 'Zaliczka',
                'description' => 'Gotówka, wymiana walut, zwrot',
                'url' => PilotAdvancePage::urlFor($event),
                'icon' => 'heroicon-o-banknotes',
            ];

            $tabs[] = [
                'key' => 'settlement',
                'label' => 'Rozliczenie',
                'description' => 'Raport i wydatki',
                'url' => PilotSettlementPage::getUrl(['event' => $event->id]),
                'icon' => 'heroicon-o-calculator',
            ];
        }

        $hasHotelPlan = $event->hotelStays()->exists();

        if ($fullAccess && $user && $hasHotelPlan && ($user->hasRole(['admin', 'super_admin']) || $user->hasRole('pilot'))) {
            $tabs[] = [
                'key' => 'hotel',
                'label' => 'Hotele',
                'description' => $user->hasRole('pilot') ? 'Lista pokoi i numery' : 'Plan pokoi',
                'url' => PilotHotelPlanPage::urlFor($event),
                'icon' => 'heroicon-o-building-office-2',
            ];
        }

        if ($fullAccess) {
            $tabs[] = [
                'key' => 'documents',
                'label' => 'Dokumenty',
                'description' => 'PDF teczki i pilota',
                'url' => route('pilot.events.pdf', ['event' => $event->id, 'audience' => 'folder']),
                'icon' => 'heroicon-o-document-arrow-down',
            ];
        }

        return WorkflowModuleNavigation::markActive($tabs, $active);
    }
}

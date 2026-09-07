<?php

namespace App\Support;

use App\Filament\Pilot\Pages\PilotAttendancePage;
use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Pilot\Pages\PilotContactPage;
use App\Filament\Pilot\Pages\PilotDocumentsPage;
use App\Filament\Pilot\Pages\PilotHotelPlanPage;
use App\Filament\Pilot\Pages\PilotProgramPage;
use App\Filament\Pilot\Pages\PilotSettlementPage;
use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Services\PilotAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
                'url' => PilotEventResource::getUrl('view', ['record' => $event->id], panel: 'pilot'),
                'icon' => 'heroicon-o-information-circle',
            ],
        ];

        if ($fullAccess) {
            $tabs[] = [
                'key' => 'program',
                'label' => 'Program',
                'url' => PilotProgramPage::urlFor($event),
                'icon' => 'heroicon-o-calendar-days',
            ];

            $tabs[] = [
                'key' => 'checklist',
                'label' => 'Checklista i czynności',
                'url' => PilotChecklistPage::urlFor($event),
                'icon' => 'heroicon-o-clipboard-document-check',
            ];

            if ($event->showsPilotAttendance()) {
                $tabs[] = [
                    'key' => 'attendance',
                    'label' => 'Obecność',
                    'url' => PilotAttendancePage::urlFor($event),
                    'icon' => 'heroicon-o-clipboard-document-list',
                ];
            }

            $tabs[] = [
                'key' => 'settlement',
                'label' => 'Rozliczenie',
                'url' => PilotSettlementPage::getUrl(['event' => $event->id], panel: 'pilot'),
                'icon' => 'heroicon-o-banknotes',
            ];
        }

        $hasHotelPlan = $event->hotelStays()->exists();

        if ($fullAccess && $user && $hasHotelPlan && ($user->hasRole('pilot') || app(PilotAccessService::class)->canStaffPreviewPortal($user))) {
            $tabs[] = [
                'key' => 'hotel',
                'label' => 'Hotele',
                'url' => PilotHotelPlanPage::urlFor($event),
                'icon' => 'heroicon-o-building-office-2',
            ];
        }

        if ($fullAccess) {
            $tabs[] = [
                'key' => 'documents',
                'label' => 'Dokumenty',
                'url' => PilotDocumentsPage::urlFor($event),
                'icon' => 'heroicon-o-document-arrow-down',
            ];

            if (Schema::hasTable('client_trip_inquiries')) {
                $tabs[] = [
                    'key' => 'contact',
                    'label' => 'Kontakt',
                    'url' => PilotContactPage::urlFor($event),
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                ];
            }
        }

        return WorkflowModuleNavigation::markActive($tabs, $active);
    }
}

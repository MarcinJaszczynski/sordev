<?php

namespace App\Filament\Pilot\Widgets;

use App\Filament\Pilot\Pages\PilotSettlementPage;
use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Services\PilotAccessService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PilotTripCalendarWidget extends Widget
{
    protected static string $view = 'filament.pilot.widgets.pilot-trip-calendar-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Kalendarz wycieczek';

    protected function getViewData(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [
                'calendarEvents' => [],
                'eventsCount' => 0,
            ];
        }

        $events = app(PilotAccessService::class)
            ->visibleTripsQuery($user)
            ->whereNotNull('start_date')
            ->orderBy('start_date')
            ->limit(200)
            ->get();

        $calendarEvents = $events->map(function (Event $event): array {
            $end = $event->end_date ?? $event->start_date;

            return [
                'id' => (string) $event->id,
                'title' => $event->name,
                'start' => $event->start_date?->toDateString(),
                'end' => $end?->copy()->addDay()->toDateString(),
                'url' => PilotEventResource::getUrl('view', ['record' => $event]),
                'settleUrl' => PilotSettlementPage::settleUrl($event),
                'backgroundColor' => '#0d9488',
                'borderColor' => '#0f766e',
                'textColor' => '#ffffff',
            ];
        })->values()->all();

        return [
            'calendarEvents' => $calendarEvents,
            'eventsCount' => count($calendarEvents),
        ];
    }
}

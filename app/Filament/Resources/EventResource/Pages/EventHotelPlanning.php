<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Pilot\Pages\PilotHotelPlanPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Services\EventHotelOccupancyService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;

class EventHotelPlanning extends Page
{
    use HasEventOperationsSubNavigation;
    use InteractsWithEventRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-hotel-planning';

    protected static ?string $navigationLabel = 'Hotele';

    protected static ?string $title = 'Hotele';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load(['eventTemplate', 'hotelStays']);
    }

    /**
     * @return array{
     *   participants: int,
     *   beds: int,
     *   assigned: int,
     *   free_beds: int,
     *   occupancy_percent: float,
     *   stays: list<array<string, mixed>>
     * }
     */
    public function occupancySummary(): array
    {
        return app(EventHotelOccupancyService::class)->forEvent($this->record);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Action::make('pdf_hotel')
            ->label('Pakiet hotelu')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->tooltip('Załączniki dodasz w zakładce „Dokumenty”: zaznacz pakiety PDF i status „Zaakceptowany”.')
            ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'hotel']))
            ->openUrlInNewTab();

        $actions[] = Action::make('pdf_hotel_agendas')
            ->label('Agendy hotelowe (ZIP)')
            ->icon('heroicon-o-building-office-2')
            ->color('gray')
            ->tooltip('Osobny PDF „Agenda dla hotelu” dla każdego obiektu z planu noclegów.')
            ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'hotel_agendas']))
            ->openUrlInNewTab();

        if (Auth::user()?->hasRole(['admin', 'super_admin'])) {
            $actions[] = Action::make('pilotHotelPreview')
                ->label('Podgląd w portalu pilota')
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->url(PilotHotelPlanPage::urlFor($this->record))
                ->openUrlInNewTab();
        }

        return $actions;
    }
}

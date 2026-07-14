<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Forms\EventNotesFields;
use App\Filament\Pilot\Pages\PilotHotelPlanPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EventHotelPlanning extends Page
{
    use HasEventWorkflowContext;
    use InteractsWithRecord;

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

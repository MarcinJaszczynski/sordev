<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Concerns\ConfirmsEventTemplateEditing;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Filament\Resources\EventTemplateResource\Concerns\ManagesTemplateHotelDays;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class EventTemplateHotelPlanning extends Page
{
    use AuthorizesEventTemplatePages;
    use ConfirmsEventTemplateEditing;
    use HasEventTemplateWorkflowContext;
    use InteractsWithRecord;
    use ManagesTemplateHotelDays;

    protected static string $resource = EventTemplateResource::class;

    protected static string $view = 'filament.resources.event-template-resource.pages.event-template-hotel-planning';

    protected static ?string $navigationLabel = 'Hotele';

    protected static ?string $title = 'Hotele';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load('hotelDays');
        $this->bootTemplateHotelDays();
        $this->bootTemplateEditingGate();
    }

    protected function getHeaderActions(): array
    {
        return $this->templateEditingHeaderActions();
    }
}

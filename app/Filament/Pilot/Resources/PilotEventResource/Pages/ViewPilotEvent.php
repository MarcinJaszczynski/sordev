<?php

namespace App\Filament\Pilot\Resources\PilotEventResource\Pages;

use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Pilot\Pages\PilotProgramPage;
use App\Filament\Pilot\Pages\PilotSettlementPage;
use App\Filament\Pilot\Resources\PilotEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPilotEvent extends ViewRecord
{
    use HasPilotTripNav;

    protected static string $resource = PilotEventResource::class;

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'info';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Teczka: '.$this->record->name;
    }

    protected function getHeaderActions(): array
    {
        $fullAccess = auth()->user()?->can('viewPilotDetails', $this->record) ?? false;

        if (! $fullAccess) {
            return [];
        }

        return [
            Actions\Action::make('program')
                ->label('Program')
                ->icon('heroicon-o-calendar-days')
                ->url(PilotProgramPage::urlFor($this->record)),
            Actions\Action::make('checklist')
                ->label('Checklista')
                ->icon('heroicon-o-clipboard-document-check')
                ->url(PilotChecklistPage::urlFor($this->record)),
            Actions\Action::make('settle')
                ->label('Rozliczenie')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->url(PilotSettlementPage::settleUrl($this->record)),
            Actions\ActionGroup::make([
                Actions\Action::make('pdf_folder')
                    ->label('Teczka PDF')
                    ->url(route('pilot.events.pdf', ['event' => $this->record, 'audience' => 'folder']))
                    ->openUrlInNewTab(),
                Actions\Action::make('pdf_pilot')
                    ->label('Pakiet pilota')
                    ->url(route('pilot.events.pdf', ['event' => $this->record, 'audience' => 'pilot']))
                    ->openUrlInNewTab(),
            ])
                ->label('Dokumenty PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->button(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['startPlace']);

        return $data;
    }
}

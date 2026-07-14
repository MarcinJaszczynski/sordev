<?php

namespace App\Filament\Client\Resources\ClientEventResource\Pages;

use App\Filament\Client\Concerns\HasClientTripNav;
use App\Filament\Client\Pages\ClientAgreementPage;
use App\Filament\Client\Pages\ClientProgramPage;
use App\Filament\Client\Resources\ClientEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewClientEvent extends ViewRecord
{
    use HasClientTripNav;

    protected static string $resource = ClientEventResource::class;

    public function getClientTripNavActiveTab(): ?string
    {
        return 'info';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Wycieczka: '.$this->record->name;
    }

    protected function getHeaderActions(): array
    {
        $fullAccess = auth()->user()?->can('viewClientPortalDetails', $this->record) ?? false;

        if (! $fullAccess) {
            return [];
        }

        return [
            Actions\Action::make('program')
                ->label('Program')
                ->icon('heroicon-o-calendar-days')
                ->url(ClientProgramPage::urlFor($this->record)),
            Actions\Action::make('agreement')
                ->label('Umowa')
                ->icon('heroicon-o-document-text')
                ->url(ClientAgreementPage::urlFor($this->record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['startPlace']);

        return $data;
    }
}

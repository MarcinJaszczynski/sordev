<?php

namespace App\Filament\Resources\EventTemplateResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Models\EventTemplate;
use App\Support\EventTemplateOfferPreview;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;

trait HasGenerateEventAction
{
    protected function makeGenerateEventAction(): Actions\Action
    {
        return Actions\Action::make('generate_event')
            ->label('Generuj imprezę')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->url(fn () => EventResource::getUrl('create', [
                'template' => $this->getEventTemplateRecord()->id,
            ]))
            ->tooltip('Otwiera pełny formularz tworzenia imprezy z programem i danymi ze szablonu');
    }

    protected function makePreviewOfferAction(): Actions\Action
    {
        return Actions\Action::make('preview_offer')
            ->label('Podgląd oferty')
            ->icon('heroicon-o-globe-alt')
            ->color('gray')
            ->modalHeading('Podgląd oferty na WWW')
            ->modalDescription('Wybierz miejsce wyjazdu — otworzy się strona klienta z ceną i dostępnością dla tego punktu.')
            ->modalSubmitActionLabel('Otwórz ofertę')
            ->form([
                Select::make('start_place_id')
                    ->label('Miejsce wyjazdu')
                    ->options(fn (): array => EventTemplateOfferPreview::startPlaceOptions(
                        $this->getEventTemplateRecord()
                    ))
                    ->default(fn (): ?int => EventTemplateOfferPreview::defaultStartPlaceId(
                        $this->getEventTemplateRecord()
                    ))
                    ->searchable()
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $template = $this->getEventTemplateRecord();
                $url = $this->resolvePreviewOfferUrl($template, $data);

                if ($url === null) {
                    return;
                }

                $this->js('window.open('.Js::from($url).', "_blank")');
            })
            ->tooltip('Otwiera publiczną stronę oferty na WWW z wybranym miejscem wyjazdu');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolvePreviewOfferUrl(EventTemplate $template, array $data): ?string
    {
        $options = EventTemplateOfferPreview::startPlaceOptions($template);

        if ($options === []) {
            Notification::make()
                ->title('Brak miejsc wyjazdu')
                ->body('Szablon nie ma dostępnego miejsca wyjazdu. Ustaw availability w kalkulacji/transporcie.')
                ->warning()
                ->send();

            return null;
        }

        $startPlaceId = (int) ($data['start_place_id'] ?? 0);

        if ($startPlaceId <= 0 || ! array_key_exists($startPlaceId, $options)) {
            Notification::make()
                ->title('Wybierz miejsce wyjazdu')
                ->warning()
                ->send();

            return null;
        }

        return EventTemplateOfferPreview::url($template, $startPlaceId);
    }

    protected function getEventTemplateRecord(): EventTemplate
    {
        $record = $this->record;

        if ($record instanceof EventTemplate) {
            return $record;
        }

        return EventTemplate::findOrFail((int) $record);
    }
}

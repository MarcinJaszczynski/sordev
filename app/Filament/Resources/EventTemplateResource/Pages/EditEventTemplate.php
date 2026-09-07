<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Actions\EventTemplates\CloneEventTemplateAction;
use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Concerns\ConfirmsEventTemplateEditing;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Filament\Resources\EventTemplateResource\Concerns\HasGenerateEventAction;
use App\Services\UnifiedPriceCalculator;
use App\Traits\CompressesImages;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditEventTemplate extends EditRecord
{
    use AuthorizesEventTemplatePages;
    use CompressesImages;
    use ConfirmsEventTemplateEditing;
    use HasEventTemplateWorkflowContext;
    use HasGenerateEventAction;

    protected static string $resource = EventTemplateResource::class;

    protected static ?string $navigationLabel = 'Dane';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.resources.event-template-resource.pages.edit-event-template';

    public function mount($record): void
    {
        parent::mount($record);
        $this->bootTemplateEditingGate();
    }

    public function form(Form $form): Form
    {
        return parent::form($form)
            ->disabled(fn (): bool => ! $this->canMutateEventTemplateNow());
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->ensureTemplateEditingAllowed();

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->templateEditingHeaderActions(),
            $this->makePreviewOfferAction(),
            $this->makeGenerateEventAction(),
            Actions\Action::make('clone')
                ->label('Klonuj')
                ->icon('heroicon-o-document-duplicate')
                ->tooltip('Tworzy kopię szablonu z punktami programu, cenami i hotelami — bez wyłączania kluczy obcych.')
                ->visible(fn (): bool => $this->canMutateEventTemplateNow())
                ->action(fn () => $this->cloneEventTemplate()),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->canMutateEventTemplateNow()),
            Actions\ForceDeleteAction::make()
                ->visible(fn (): bool => $this->canMutateEventTemplateNow()),
            Actions\RestoreAction::make()
                ->visible(fn (): bool => $this->canMutateEventTemplateNow()),
        ];
    }

    protected function cloneEventTemplate()
    {
        $this->ensureTemplateEditingAllowed();

        $hotelDays = (isset($this->hotel_days) && is_array($this->hotel_days) && $this->hotel_days !== [])
            ? $this->hotel_days
            : null;

        $clone = app(CloneEventTemplateAction::class)($this->record, $hotelDays);

        Notification::make()
            ->title('Szablon został pomyślnie sklonowany!')
            ->success()
            ->send();

        return redirect(static::getResource()::getUrl('edit', ['record' => $clone->id]));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->ensureTemplateEditingAllowed();

        $response = parent::mutateFormDataBeforeSave($data);
        (new UnifiedPriceCalculator)->recalculateForTemplate($this->record);

        return $response;
    }

    protected function afterSave(): void
    {
        Log::info('EditEventTemplate afterSave - data:', $this->data);
        Log::info('EditEventTemplate afterSave - event_price_description_id:', [$this->data['event_price_description_id'] ?? 'NOT SET']);

        $priceDescriptionId = $this->data['event_price_description_id'] ?? null;
        Log::info('EditEventTemplate afterSave - priceDescriptionId value:', [$priceDescriptionId]);

        if ($priceDescriptionId) {
            Log::info('EditEventTemplate afterSave - syncing price description:', [$priceDescriptionId]);
            $this->record->eventPriceDescription()->sync([$priceDescriptionId]);
            Log::info('EditEventTemplate afterSave - sync completed');
        } else {
            Log::info('EditEventTemplate afterSave - clearing price descriptions');
            $this->record->eventPriceDescription()->sync([]);
        }

        try {
            (new UnifiedPriceCalculator)->recalculateForTemplate($this->record);
        } catch (\Exception $e) {
            Log::error('Error recalculating prices after save: '.$e->getMessage());
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $eventPriceDescription = $this->record->eventPriceDescription()->first();
        if ($eventPriceDescription) {
            $data['event_price_description_id'] = $eventPriceDescription->id;
            Log::info('EditEventTemplate mutateFormDataBeforeFill - loaded event_price_description_id:', [$eventPriceDescription->id]);
        } else {
            $data['event_price_description_id'] = null;
            Log::info('EditEventTemplate mutateFormDataBeforeFill - no event_price_description found');
        }

        return $data;
    }
}

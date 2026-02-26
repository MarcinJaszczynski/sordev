<?php

namespace App\Filament\Resources\EventTemplateProgramPointResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\EventTemplateProgramPoint;
use App\Models\EventTemplate;
use App\Services\UnifiedPriceCalculator;
use Filament\Notifications\Notification;

class EventProgramPointPriceTable extends Widget
{
    protected static string $view = 'filament.resources.event-template-program-point-resource.widgets.event-program-point-price-table';
    public ?EventTemplateProgramPoint $record = null;
    protected int | string | array $columnSpan = 'full';
    public $priceRows = [];

    public function mount()
    {
        $this->priceRows = $this->getPriceRowsProperty();
    }

    public function getPriceRowsProperty()
    {
        if (!$this->record) return collect();
        $rows = collect();
        $eventTemplates = $this->record->eventTemplates()->with(['qtyVariants', 'programPoints.currency'])->get();
        foreach ($eventTemplates as $template) {
            foreach ($template->qtyVariants as $qtyVariant) {
                $qty = $qtyVariant->qty;
                // use qty only for point calculations
                $peopleForPoints = $qty;
                $currencies = $template->programPoints->pluck('currency')->unique('id');
                foreach ($currencies as $currency) {
                    $total = 0;
                    foreach ($template->programPoints->where('currency_id', $currency->id) as $point) {
                        $groupSize = $point->group_size ?? 1;
                        $unitPrice = $point->unit_price ?? 0;
                        if ($groupSize == 1) {
                            $pointPrice = $unitPrice * $qty;
                        } else {
                            $pointPrice = ceil($qty / $groupSize) * $unitPrice;
                        }
                        $total += $pointPrice;
                    }
                    $pricePerPerson = $qty > 0 ? ceil($total / $qty) : 0;
                    $rows->push([
                        'event_template' => $template->name,
                        'qty' => $qty,
                        'currency' => $currency->symbol,
                        'price_per_person' => $pricePerPerson,
                    ]);
                }
            }
        }
        return $rows;
    }

    public function recalculatePrices(): void
    {
        if (!$this->record) return;
        $eventTemplates = $this->record->eventTemplates;
        foreach ($eventTemplates as $template) {
            (new UnifiedPriceCalculator())->recalculateForTemplate($template);
        }
        Notification::make()
            ->title('Ceny zostały przeliczone!')
            ->success()
            ->send();
    }
}

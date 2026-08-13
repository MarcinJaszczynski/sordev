<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Widgets;

use App\Models\Event;
use App\Support\EventMarginPlanVsActual;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class EventFinanceMarginWidget extends Widget
{
    protected static string $view = 'filament.resources.event-resource.widgets.event-finance-margin';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    /**
     * @return array<string, mixed>
     */
    public function getMarginProperty(): array
    {
        $event = $this->record;
        if (! $event instanceof Event) {
            return [];
        }

        return EventMarginPlanVsActual::forEvent($event);
    }
}

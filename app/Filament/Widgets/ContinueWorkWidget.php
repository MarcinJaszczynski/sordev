<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Widgets\Widget;

class ContinueWorkWidget extends Widget
{
    protected static string $view = 'filament.widgets.continue-work-widget';

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function getRecentEvents(): array
    {
        return Event::query()
            ->whereIn('status', [
                Event::STATUS_CONFIRMED,
                Event::STATUS_PROVISIONAL_RESERVATION,
                Event::STATUS_TO_SETTLE,
                Event::STATUS_OFFER,
            ])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'updated' => $event->updated_at?->diffForHumans(),
                'url' => EventResource::getUrl('edit', ['record' => $event->id]),
            ])
            ->all();
    }
}

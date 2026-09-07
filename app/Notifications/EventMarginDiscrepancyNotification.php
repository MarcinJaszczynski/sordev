<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventMarginDiscrepancyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public float $calculationPln,
        public float $settlementPln,
        public ?float $deltaPercent,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Rozbieżność szablon vs planowane',
            'body' => sprintf(
                '%s: szablon %s PLN, planowane %s PLN (Δ %s%%).',
                $this->event->name,
                number_format($this->calculationPln, 2, ',', ' '),
                number_format($this->settlementPln, 2, ',', ' '),
                $this->deltaPercent !== null ? number_format($this->deltaPercent, 1, ',', ' ') : '—',
            ),
            'event_id' => $this->event->id,
            'url' => route('filament.admin.resources.events.edit', ['record' => $this->event->id]),
        ];
    }
}

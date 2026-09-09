<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskPriority;
use App\Events\EventStatusChanged;
use App\Models\Event;
use App\Support\AdminPanelUrls;
use App\Support\Tasks\SystemTaskFactory;
use Illuminate\Support\Facades\Log;

/**
 * Automatyzacje po zmianie statusu imprezy (zadania biurowe).
 *
 * Bez auto-maila/SMS do klienta — wysyłka ręczna.
 */
final class EventStatusAutomationService
{
    public function handle(EventStatusChanged $change): void
    {
        $event = $change->event;
        $from = $change->previousStatus;
        $to = $change->newStatus;

        Log::info('EventStatusChanged', [
            'event_id' => $event->id,
            'from' => $from,
            'to' => $to,
        ]);

        match ($to) {
            Event::STATUS_CONFIRMED => $this->createOfficeTask(
                $event,
                'Impreza potwierdzona — lista kontrolna: '.$this->label($event),
                'Umowy, zaliczki, udostępnienie pilotowi i portalowi klienta.',
                TaskPriority::Urgent,
            ),
            // Oferta / rezerwacja wstępna / do rozliczenia — bez auto-taska (zaśmiecało skrzynkę).
            default => null,
        };
    }

    private function label(Event $event): string
    {
        return ($event->code ?: '#'.$event->id).' — '.($event->name ?: 'Impreza');
    }

    private function createOfficeTask(Event $event, string $title, string $description, TaskPriority $priority): void
    {
        SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':'.$event->status,
            title: $title,
            description: $description,
            priority: $priority,
            eventForAssignee: $event,
            url: AdminPanelUrls::eventEdit($event),
            onlyOpenWhenFinding: false,
        );
    }
}

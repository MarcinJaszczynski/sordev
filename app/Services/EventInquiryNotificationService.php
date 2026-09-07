<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Support\AdminPanelUrls;
use App\Support\Tasks\OfficeTaskRecipients;
use App\Support\Tasks\SystemTaskFactory;
use Illuminate\Support\Collection;

class EventInquiryNotificationService
{
    public function notifyOfficeAboutNewInquiry(Event $event, ?User $author = null): void
    {
        if ($this->wasRecentlyNotified($event)) {
            return;
        }

        $fingerprint = 'event-inquiry:'.$event->id;

        SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: $fingerprint,
            title: $this->buildTitle($event),
            description: $this->buildDescription($event),
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
            preferredAssigneeId: $author?->id,
            url: AdminPanelUrls::eventEdit($event),
        );
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveOfficeRecipients(): Collection
    {
        return OfficeTaskRecipients::users();
    }

    private function buildTitle(Event $event): string
    {
        $code = filled($event->code) ? $event->code : '#'.$event->id;
        $name = $event->name ?: 'Impreza';

        return 'Nowe zapytanie: '.$code.' — '.$name;
    }

    private function buildDescription(Event $event): string
    {
        $lines = [
            'Utworzono nowe zapytanie o imprezę.',
            'Klient: '.($event->client_name ?: '—'),
        ];

        if (filled($event->client_phone)) {
            $lines[] = 'Telefon: '.$event->client_phone;
        }

        if (filled($event->client_email)) {
            $lines[] = 'E-mail: '.$event->client_email;
        }

        if (filled($event->start_date)) {
            $lines[] = 'Termin: '.$event->start_date->format('d.m.Y');
        }

        $lines[] = 'Liczba uczestników: '.(int) ($event->participant_count ?? 0);

        return implode("\n", $lines);
    }

    private function wasRecentlyNotified(Event $event): bool
    {
        $needle = 'Nowe zapytanie:';

        return Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('title', 'like', $needle.'%')
            ->exists();
    }
}

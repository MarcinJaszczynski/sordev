<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EventInquiryNotificationService
{
    public function notifyOfficeAboutNewInquiry(Event $event, ?User $author = null): void
    {
        if ($this->wasRecentlyNotified($event)) {
            return;
        }

        $statusId = Task::getDefaultStatusId();

        if (! $statusId) {
            return;
        }

        $recipients = $this->resolveOfficeRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $authorId = $author?->id ?? Auth::id() ?? $recipients->first()->id;
        $title = $this->buildTitle($event);
        $description = $this->buildDescription($event);
        $eventUrl = EventResource::getUrl('edit', ['record' => $event]);
        $maxOrder = (int) Task::query()->where('status_id', $statusId)->max('order');

        foreach ($recipients as $recipient) {
            Task::create([
                'title' => $title,
                'description' => $description."\n\nLink do imprezy: ".$eventUrl,
                'due_date' => now()->addDay(),
                'status_id' => $statusId,
                'priority' => TaskPriority::Urgent->value,
                'source' => TaskSource::System->value,
                'author_id' => $authorId,
                'assignee_id' => $recipient->id,
                'taskable_type' => Event::class,
                'taskable_id' => $event->id,
                'order' => ++$maxOrder,
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveOfficeRecipients(): Collection
    {
        return User::role(['admin', 'super_admin', 'biuro'])->get();
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

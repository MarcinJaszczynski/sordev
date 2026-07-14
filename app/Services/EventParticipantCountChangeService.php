<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\EventParticipantResignation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EventParticipantCountChangeService
{
    public const REASON_MANUAL_EDIT = 'manual_edit';

    public const REASON_RESIGNATION = 'resignation';

    public const REASON_RESIGNATION_REVERT = 'resignation_revert';

    /**
     * @return Collection<int, User>
     */
    public function resolveOfficeRecipients(): Collection
    {
        return User::role(['admin', 'super_admin', 'biuro'])->get();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyOffice(Event $event, int $oldCount, int $newCount, string $reason, array $context = []): void
    {
        if ($oldCount === $newCount) {
            return;
        }

        if ($this->wasRecentlyNotified($event, $oldCount, $newCount)) {
            return;
        }

        $statusId = Task::getDefaultStatusId();

        if (! $statusId) {
            return;
        }

        $authorId = Auth::id() ?? ($context['author_id'] ?? null);
        $recipients = $this->resolveOfficeRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $authorId ??= $recipients->first()->id;

        $title = $this->buildTitle($event);
        $description = $this->buildDescription($oldCount, $newCount, $reason, $context);
        $reservationsUrl = route('filament.admin.resources.events.reservations', ['record' => $event->id]);
        $maxOrder = (int) Task::query()->where('status_id', $statusId)->max('order');

        foreach ($recipients as $recipient) {
            Task::create([
                'title' => $title,
                'description' => $description."\n\nLink do rezerwacji: ".$reservationsUrl,
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

            NotificationService::clearCacheForUser($recipient->id);
        }
    }

    public function applyResignationDecrement(EventParticipantResignation $resignation): void
    {
        $event = $resignation->event;

        if (! $event) {
            return;
        }

        $oldCount = max(1, (int) $event->participant_count);
        $newCount = max(1, $oldCount - 1);

        if ($newCount !== $oldCount) {
            $event->update(['participant_count' => $newCount]);
            $event = $event->fresh();
        }

        $this->notifyOffice(
            $event,
            $oldCount,
            $newCount,
            self::REASON_RESIGNATION,
            $this->resignationContext($resignation),
        );
    }

    public function revertResignationIncrement(EventParticipantResignation $resignation): void
    {
        $event = $resignation->event;

        if (! $event) {
            return;
        }

        $oldCount = max(1, (int) $event->participant_count);
        $newCount = $oldCount + 1;

        $event->update(['participant_count' => $newCount]);

        $this->notifyOffice(
            $event->fresh(),
            $oldCount,
            $newCount,
            self::REASON_RESIGNATION_REVERT,
            $this->resignationContext($resignation),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resignationContext(EventParticipantResignation $resignation): array
    {
        return [
            'participant_name' => $resignation->participant_name,
            'resigned_at' => $resignation->resigned_at?->format('d.m.Y'),
            'author_id' => $resignation->created_by,
        ];
    }

    private function buildTitle(Event $event): string
    {
        $name = $event->name ?: 'Impreza #'.$event->id;

        return 'Zmiana liczby uczestników: '.$name;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildDescription(int $oldCount, int $newCount, string $reason, array $context): string
    {
        return implode("\n", [
            'Liczba uczestników imprezy zmieniła się z '.$oldCount.' na '.$newCount.'.',
            'Powód: '.$this->reasonLabel($reason, $context).'.',
            'Sprawdź i zaktualizuj rezerwacje u kontrahentów.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reasonLabel(string $reason, array $context): string
    {
        return match ($reason) {
            self::REASON_MANUAL_EDIT => 'zmiana w formularzu imprezy'
                .(filled($context['editor_name'] ?? null) ? ' przez '.$context['editor_name'] : ''),
            self::REASON_RESIGNATION => 'potwierdzona rezygnacja'
                .(filled($context['participant_name'] ?? null) ? ' — '.$context['participant_name'] : '')
                .(filled($context['resigned_at'] ?? null) ? ' ('.$context['resigned_at'].')' : ''),
            self::REASON_RESIGNATION_REVERT => 'cofnięcie rezygnacji'
                .(filled($context['participant_name'] ?? null) ? ' — '.$context['participant_name'] : ''),
            default => $reason,
        };
    }

    private function wasRecentlyNotified(Event $event, int $oldCount, int $newCount): bool
    {
        $needle = 'z '.$oldCount.' na '.$newCount;

        return Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('description', 'like', '%'.$needle.'%')
            ->exists();
    }
}

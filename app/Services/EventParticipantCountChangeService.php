<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Models\Event;
use App\Models\EventParticipantResignation;
use App\Models\Task;
use App\Models\User;
use App\Support\AdminPanelUrls;
use App\Support\Tasks\OfficeTaskRecipients;
use App\Support\Tasks\SystemTaskFactory;
use Illuminate\Support\Collection;

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
        return OfficeTaskRecipients::users();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyOffice(Event $event, int $oldCount, int $newCount, string $reason, array $context = []): void
    {
        if ($oldCount === $newCount) {
            return;
        }

        // Powiadomienie tylko dla imprez potwierdzonych (Potwierdzona + Odprawa OK).
        // Na ofercie/zapytaniu liczba uczestników zmienia się często — bez generowania szumu w zadaniach.
        if (! $event->isConfirmedLike()) {
            return;
        }

        if ($this->wasRecentlyNotified($event, $oldCount, $newCount)) {
            return;
        }

        $title = $this->buildTitle($event);
        $description = $this->buildDescription($oldCount, $newCount, $reason, $context);
        $fingerprint = 'event-participant-count:'.$event->id.':'.$oldCount.':'.$newCount;

        SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: $fingerprint,
            title: $title,
            description: $description,
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
            url: AdminPanelUrls::eventReservations($event),
        );
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

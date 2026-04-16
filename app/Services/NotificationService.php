<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationService
{
    private static function userCanSeeAllEvents(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    private static function eventQueryForUser(User $user)
    {
        $query = Event::query();

        if (! static::userCanSeeAllEvents($user)) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private static function buildEventIndexUrlWithStatus(string $status): string
    {
        return route('filament.admin.resources.events.index') . '?tableFilters[status][value]=' . urlencode($status);
    }

    private static function formatEventNotification(Event $event, string $type, string $fallbackLabel): array
    {
        $startDate = $event->start_date ? $event->start_date->format('d.m.Y') : 'bez daty';

        $url = match ($type) {
            'new_event' => static::buildEventIndexUrlWithStatus(Event::STATUS_INQUIRY),
            'event' => static::buildEventIndexUrlWithStatus(Event::STATUS_CONFIRMED),
            'pending_cancellation_event' => static::buildEventIndexUrlWithStatus(Event::STATUS_PENDING_CANCELLATION),
            default => route('filament.admin.resources.events.edit', ['record' => $event->id]),
        };

        return [
            'type' => $type,
            'id' => (int) $event->id,
            'title' => Str::limit($event->name ?? ('Impreza #' . $event->id), 60),
            'meta' => ($event->status_label ?: $fallbackLabel) . ' | Start: ' . $startDate,
            'time' => optional($event->updated_at)->diffForHumans() ?? 'teraz',
            'url' => $url,
            'at' => optional($event->updated_at)?->timestamp ?? now()->timestamp,
        ];
    }

    /**
     * Pobiera liczbę nowych powiadomień dla użytkownika
     */
    public static function getUnreadCountsForUser(int $userId): array
    {
        return static::getTopbarDataForUser($userId)['counts'];
    }

    /**
     * Zwraca dane do górnego paska powiadomień: liczniki + listę najważniejszych zdarzeń.
     */
    public static function getTopbarDataForUser(int $userId): array
    {
        $cacheKey = "user_notifications_{$userId}";

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($userId) {
            $user = User::find($userId);

            if (! $user) {
                return [
                    'counts' => [
                        'tasks' => 0,
                        'messages' => 0,
                        'comments' => 0,
                        'task_updates' => 0,
                        'new_events' => 0,
                        'confirmed_events' => 0,
                        'pending_cancellation_events' => 0,
                        'important' => 0,
                    ],
                    'items' => [],
                    'items_by_type' => [
                        'task' => [],
                        'comment' => [],
                        'new_event' => [],
                        'event' => [],
                        'pending_cancellation_event' => [],
                        'message' => [],
                    ],
                ];
            }

            $activeStatusIds = TaskStatus::query()
                ->whereIn('name', ['Do zrobienia', 'W trakcie', 'Oczekuje na weryfikację'])
                ->pluck('id');

            $myActiveTasks = Task::query()
                ->where('assignee_id', $user->id)
                ->whereIn('status_id', $activeStatusIds)
                ->count();

            $myAuthoredTasks = Task::query()
                ->where('author_id', $user->id)
                ->whereHas('status', function ($query) {
                    $query->whereNotIn('name', ['Zakończone', 'Anulowane']);
                })
                ->count();

            $taskUpdatesCount = Task::query()
                ->where(function ($query) use ($user) {
                    $query->where('assignee_id', $user->id)
                        ->orWhere('author_id', $user->id);
                })
                ->where('updated_at', '>', now()->subDay())
                ->count();

            $confirmedEventsCount = static::eventQueryForUser($user)
                ->where('status', Event::STATUS_CONFIRMED)
                ->count();

            $newEventsCount = static::eventQueryForUser($user)
                ->where('status', Event::STATUS_INQUIRY)
                ->count();

            $pendingCancellationEventsCount = static::eventQueryForUser($user)
                ->where('status', Event::STATUS_PENDING_CANCELLATION)
                ->count();

            $unreadMessagesCount = 0;
            $conversationNotifications = [];

            $userConversations = $user->conversations()->with(['lastMessage', 'participants'])->get();

            foreach ($userConversations as $conversation) {
                $lastReadAt = $conversation->pivot->last_read_at ?? $conversation->pivot->joined_at ?? now()->subWeek();
                $unreadCount = $conversation->messages()
                    ->where('user_id', '!=', $user->id)
                    ->where('created_at', '>', $lastReadAt)
                    ->count();

                if ($unreadCount > 0) {
                    $conversationNotifications[] = [
                        'type' => 'message',
                        'title' => $conversation->getDisplayName($user),
                        'meta' => $unreadCount . ' nieprzeczytanych wiadomosci',
                        'time' => optional($conversation->last_message_at)->diffForHumans() ?? 'teraz',
                        'url' => route('filament.admin.pages.chat'),
                        'at' => optional($conversation->last_message_at)?->timestamp ?? now()->timestamp,
                    ];
                }

                $unreadMessagesCount += $unreadCount;
            }

            $taskNotifications = Task::query()
                ->with('status')
                ->where('assignee_id', $user->id)
                ->whereIn('status_id', $activeStatusIds)
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->limit(4)
                ->get()
                ->map(function (Task $task): array {
                    $due = $task->due_date ? $task->due_date->format('d.m.Y H:i') : 'brak terminu';

                    return [
                        'type' => 'task',
                        'title' => Str::limit($task->title, 60),
                        'meta' => 'Termin: ' . $due . ' | Status: ' . ($task->status->name ?? 'brak'),
                        'time' => optional($task->updated_at)->diffForHumans() ?? 'teraz',
                        'url' => route('filament.admin.resources.tasks.edit', ['record' => $task->id]),
                        'at' => optional($task->updated_at)?->timestamp ?? now()->timestamp,
                    ];
                })
                ->all();

            $eventNotifications = static::eventQueryForUser($user)
                ->where('status', Event::STATUS_CONFIRMED)
                ->orderByRaw('start_date IS NULL, start_date ASC')
                ->limit(4)
                ->get()
                ->map(fn (Event $event): array => static::formatEventNotification($event, 'event', 'Impreza'))
                ->all();

            $newEventNotifications = static::eventQueryForUser($user)
                ->where('status', Event::STATUS_INQUIRY)
                ->orderByDesc('created_at')
                ->limit(4)
                ->get()
                ->map(fn (Event $event): array => static::formatEventNotification($event, 'new_event', 'Nowa impreza'))
                ->all();

            $pendingCancellationEventNotifications = static::eventQueryForUser($user)
                ->where('status', Event::STATUS_PENDING_CANCELLATION)
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get()
                ->map(fn (Event $event): array => static::formatEventNotification($event, 'pending_cancellation_event', 'Do anulacji'))
                ->all();

            $commentNotifications = TaskComment::query()
                ->where('author_id', '!=', $user->id)
                ->where('created_at', '>', now()->subDays(14))
                ->whereHas('task', function ($query) use ($user) {
                    $query->where('assignee_id', $user->id)
                        ->orWhere('author_id', $user->id);
                })
                ->with(['author:id,name', 'task:id,title'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(function (TaskComment $comment): array {
                    $taskTitle = Str::limit($comment->task?->title ?? ('Zadanie #' . $comment->task_id), 40);

                    return [
                        'type' => 'comment',
                        'title' => 'Nowy komentarz: ' . $taskTitle,
                        'meta' => ($comment->author?->name ?? 'Użytkownik') . ': ' . Str::limit($comment->content ?? '', 70),
                        'time' => optional($comment->created_at)->diffForHumans() ?? 'teraz',
                        'url' => $comment->task_id
                            ? route('filament.admin.resources.tasks.edit', ['record' => $comment->task_id])
                            : route('filament.admin.resources.tasks.index'),
                        'at' => optional($comment->created_at)?->timestamp ?? now()->timestamp,
                    ];
                })
                ->all();

            $items = collect(array_merge(
                $commentNotifications,
                $taskNotifications,
                $newEventNotifications,
                $eventNotifications,
                $pendingCancellationEventNotifications,
                $conversationNotifications,
            ))
                ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                ->take(10)
                ->map(function (array $item): array {
                    unset($item['at']);

                    return $item;
                })
                ->values()
                ->all();

            $itemsByType = [
                'task' => collect($taskNotifications)
                    ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                    ->take(10)
                    ->map(function (array $item): array {
                        unset($item['at']);

                        return $item;
                    })
                    ->values()
                    ->all(),
                'comment' => collect($commentNotifications)
                    ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                    ->take(10)
                    ->map(function (array $item): array {
                        unset($item['at']);

                        return $item;
                    })
                    ->values()
                    ->all(),
                'new_event' => collect($newEventNotifications)
                    ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                    ->take(10)
                    ->map(function (array $item): array {
                        unset($item['at']);

                        return $item;
                    })
                    ->values()
                    ->all(),
                'event' => collect($eventNotifications)
                    ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                    ->take(10)
                    ->map(function (array $item): array {
                        unset($item['at']);

                        return $item;
                    })
                    ->values()
                    ->all(),
                'pending_cancellation_event' => collect($pendingCancellationEventNotifications)
                    ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                    ->take(10)
                    ->map(function (array $item): array {
                        unset($item['at']);

                        return $item;
                    })
                    ->values()
                    ->all(),
                'message' => collect($conversationNotifications)
                    ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                    ->take(10)
                    ->map(function (array $item): array {
                        unset($item['at']);

                        return $item;
                    })
                    ->values()
                    ->all(),
            ];

            $commentsCount = count($commentNotifications);

            $newTasksCount = $myActiveTasks + $myAuthoredTasks;

            return [
                'counts' => [
                    'tasks' => $newTasksCount,
                    'messages' => $unreadMessagesCount,
                    'comments' => $commentsCount,
                    'task_updates' => $taskUpdatesCount,
                    'new_events' => $newEventsCount,
                    'confirmed_events' => $confirmedEventsCount,
                    'pending_cancellation_events' => $pendingCancellationEventsCount,
                    'important' => $newTasksCount + $newEventsCount + $confirmedEventsCount + $pendingCancellationEventsCount + $unreadMessagesCount + $commentsCount,
                ],
                'items' => $items,
                'items_by_type' => $itemsByType,
            ];
        });
    }
    
    /**
     * Czyści cache powiadomień dla użytkownika
     */
    public static function clearCacheForUser(int $userId): void
    {
        Cache::forget("user_notifications_{$userId}");
    }
    
    /**
     * Oznacza wiadomości jako przeczytane dla użytkownika w konwersacji
     */
    public static function markMessagesAsRead(int $userId, int $conversationId): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->conversations()->updateExistingPivot($conversationId, [
                'last_read_at' => now()
            ]);
            
            self::clearCacheForUser($userId);
        }
    }
}

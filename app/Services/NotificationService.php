<?php

namespace App\Services;

use App\Enums\TaskSource;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\UserNotificationRead;
use App\Support\AdminPanelUrls;
use App\Support\Tasks\OfficeTaskRecipients;
use App\Support\Tasks\TaskListColumn;
use App\Support\Tasks\TaskNavigation;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationService
{
    public const TOPBAR_LIMIT_PER_TYPE = 15;

    public const TOPBAR_COMBINED_LIMIT = 15;

    public const TOPBAR_TASK_QUERY_LIMIT = 30;

    private static function userCanSeeAllEvents(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    private static function userCanSeeInvoiceRequests(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc']);
    }

    private static function eventQueryForUser(User $user)
    {
        $query = Event::query();

        if (! static::userCanSeeAllEvents($user)) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private static function emptyTopbarPayload(): array
    {
        return [
            'counts' => [
                'tasks' => 0,
                'messages' => 0,
                'comments' => 0,
                'new_events' => 0,
                'confirmed_events' => 0,
                'pending_cancellation_events' => 0,
                'invoice_requests' => 0,
                'work' => 0,
                'events' => 0,
                'total_unread' => 0,
            ],
            'items' => [],
            'items_by_type' => [
                'task' => [],
                'comment' => [],
                'new_event' => [],
                'event' => [],
                'pending_cancellation_event' => [],
                'invoice_request' => [],
                'message' => [],
            ],
            'items_by_group' => [
                'work' => [],
                'events' => [],
                'messages' => [],
            ],
        ];
    }

    private static function formatEventNotification(Event $event, string $type, string $fallbackLabel): array
    {
        $startDate = $event->start_date ? $event->start_date->format('d.m.Y') : 'bez daty';

        return [
            'type' => $type,
            'id' => (int) $event->id,
            'revision' => (string) (optional($event->updated_at)?->timestamp ?? now()->timestamp),
            'title' => Str::limit($event->name ?? ('Impreza #'.$event->id), 60),
            'meta' => ($event->status_label ?: $fallbackLabel).' | Start: '.$startDate,
            'time' => optional($event->updated_at)->diffForHumans() ?? 'teraz',
            'url' => AdminPanelUrls::eventEdit($event),
            'at' => optional($event->updated_at)?->timestamp ?? now()->timestamp,
            'color' => match ($type) {
                'new_event' => 'amber',
                'pending_cancellation_event' => 'rose',
                default => 'blue',
            },
        ];
    }

    private static function formatInsuranceAlertNotification(Event $event): array
    {
        $startDate = $event->start_date ? $event->start_date->format('d.m.Y') : 'bez daty';

        // Osobny type niż "event" oraz osobna revision — fingerprint nie może
        // kolidować z powiadomieniem o potwierdzeniu tej samej imprezy
        // (Alpine x-for pada na zduplikowanych :key).
        return [
            'type' => 'insurance_alert',
            'id' => (int) $event->id,
            'revision' => 'insurance:'.(string) (optional($event->updated_at)?->timestamp ?? now()->timestamp),
            'title' => 'Ubezpieczenie do domknięcia: '.Str::limit($event->name ?? ('Impreza #'.$event->id), 42),
            'meta' => 'Brak kompletu danych/płatności ubezpieczenia | Start: '.$startDate,
            'time' => optional($event->updated_at)->diffForHumans() ?? 'teraz',
            'url' => AdminPanelUrls::eventEdit($event),
            'at' => optional($event->updated_at)?->timestamp ?? now()->timestamp,
            'color' => 'blue',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function finalizeItem(array $item, int $userId): array
    {
        if (! isset($item['revision']) && isset($item['at'])) {
            $item['revision'] = (string) $item['at'];
        }

        unset($item['at']);
        $item['fingerprint'] = UserNotificationRead::fingerprintFor($item);
        $item['is_read'] = static::isRead($userId, $item['fingerprint']);

        return $item;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function finalizeItems(array $items, int $userId): array
    {
        return collect($items)
            ->map(fn (array $item): array => static::finalizeItem($item, $userId))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private static function unreadOnly(array $items): Collection
    {
        return collect($items)->filter(fn (array $item): bool => ! ($item['is_read'] ?? false));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private static function unreadCount(array $items): int
    {
        return static::unreadOnly($items)->count();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sortNewestFirst(array $items): array
    {
        return collect($items)
            ->sortByDesc(fn (array $item): int => (int) ($item['revision'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function unreadList(array $items, int $limit): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => ! ($item['is_read'] ?? false))
            ->unique(fn (array $item): string => (string) ($item['fingerprint'] ?? ($item['type'].'|'.$item['id'].'|'.$item['revision'])))
            ->sortByDesc(fn (array $item): int => (int) ($item['revision'] ?? 0))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Zadania widoczne w topbarze — ta sama reguła co lista/kanban (inboxFor).
     * Podzadania są pełnoprawnymi Task — wchodzą do listy i licznika.
     */
    private static function visibleTasksQueryFor(User $user)
    {
        $query = Task::query();
        TaskQueryFilters::officeOnly($query);
        TaskQueryFilters::excludeCompleted($query);
        TaskQueryFilters::excludeArchived($query);

        return TaskQueryFilters::inboxFor($query, $user);
    }

    /**
     * Pełna liczba nieprzeczytanych zadań (bez limitu listy topbara).
     */
    private static function unreadTaskCountFor(User $user): int
    {
        $tasks = static::visibleTasksQueryFor($user)->get(['id', 'updated_at']);

        if ($tasks->isEmpty()) {
            return 0;
        }

        if (! Schema::hasTable('user_notification_reads')) {
            return $tasks->count();
        }

        $fingerprints = $tasks
            ->map(fn (Task $task): string => UserNotificationRead::fingerprintFor([
                'type' => 'task',
                'id' => (int) $task->id,
                'revision' => (string) ($task->updated_at?->timestamp ?? 0),
            ]))
            ->all();

        $readSet = UserNotificationRead::query()
            ->where('user_id', $user->id)
            ->whereIn('fingerprint', $fingerprints)
            ->pluck('fingerprint')
            ->flip()
            ->all();

        return collect($fingerprints)
            ->reject(fn (string $fingerprint): bool => isset($readSet[$fingerprint]))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function taskNotificationsFor(User $user, int $queryLimit = 30): array
    {
        return static::visibleTasksQueryFor($user)
            ->with(['status', 'author', 'assignee', 'parent:id,title'])
            ->orderByDesc('updated_at')
            ->limit($queryLimit)
            ->get()
            ->map(function (Task $task): array {
                $due = $task->due_date ? $task->due_date->format('d.m.Y H:i') : 'brak terminu';
                $metaParts = [];

                if ($task->parent_id) {
                    $task->loadMissing('parent');
                    $parentTitle = $task->parent?->title;
                    $metaParts[] = $parentTitle
                        ? 'Podzadanie → '.Str::limit($parentTitle, 40)
                        : 'Podzadanie';
                }

                $metaParts[] = TaskListColumn::ownershipLine($task);
                $metaParts[] = 'Termin: '.$due;
                $metaParts[] = 'Status: '.($task->status->name ?? 'brak');

                return [
                    'type' => 'task',
                    'id' => (int) $task->id,
                    'revision' => (string) ($task->updated_at?->timestamp ?? 0),
                    'title' => Str::limit($task->title, 60),
                    'meta' => implode(' | ', $metaParts),
                    'time' => optional($task->updated_at)->diffForHumans() ?? 'teraz',
                    'url' => TaskNavigation::fullViewUrl($task),
                    'at' => optional($task->updated_at)?->timestamp ?? now()->timestamp,
                    'color' => 'violet',
                ];
            })
            ->all();
    }

    public static function clearCacheForTaskStakeholders(Task $task): void
    {
        $task->loadMissing('parent');

        collect([
            $task->assignee_id,
            $task->author_id,
            $task->parent?->assignee_id,
            $task->parent?->author_id,
        ])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->each(fn (int $userId) => static::clearCacheForUser($userId));
    }

    public static function clearCacheForOfficeUsers(): void
    {
        OfficeTaskRecipients::users()
            ->each(fn (User $user) => static::clearCacheForUser((int) $user->id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function commentNotificationsFor(User $user, int $queryLimit = 50): array
    {
        return static::commentsQueryFor($user)
            ->with(['author:id,name', 'task:id,title,source,taskable_type,taskable_id'])
            ->orderByDesc('created_at')
            ->limit($queryLimit)
            ->get()
            ->map(fn (TaskComment $comment): array => static::formatCommentNotification($comment))
            ->all();
    }

    /**
     * Komentarze w topbarze: do zadań widocznych jak w liczniku zadań.
     * Własne komentarze są wykluczone — licznik ma pokazywać tylko cudzą aktywność.
     */
    private static function commentsQueryFor(User $user)
    {
        return TaskComment::query()
            ->where('user_id', '!=', $user->id)
            ->whereIn('task_id', static::visibleTasksQueryFor($user)->select('id'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatCommentNotification(TaskComment $comment): array
    {
        $authorName = $comment->author?->name ?? 'Użytkownik';
        $taskTitle = Str::limit($comment->task?->title ?? ('Zadanie #'.$comment->task_id), 40);

        return [
            'type' => 'comment',
            'id' => (int) $comment->id,
            'task_id' => (int) $comment->task_id,
            'revision' => (string) ($comment->created_at?->timestamp ?? 0),
            'title' => $authorName.' dodał komentarz do zadania '.$taskTitle,
            'meta' => TaskListColumn::sanitizeTaskText($comment->content ?? '', 70),
            'time' => optional($comment->created_at)->diffForHumans() ?? 'teraz',
            'url' => $comment->task
                ? TaskNavigation::fullViewUrl($comment->task)
                : AdminPanelUrls::taskBoard(),
            'at' => optional($comment->created_at)?->timestamp ?? now()->timestamp,
            'color' => 'sky',
        ];
    }

    /**
     * Pełna liczba nieprzeczytanych komentarzy (bez limitu listy topbara).
     */
    private static function unreadCommentCountFor(User $user): int
    {
        $comments = static::commentsQueryFor($user)->get(['id', 'created_at']);

        if ($comments->isEmpty()) {
            return 0;
        }

        if (! Schema::hasTable('user_notification_reads')) {
            return $comments->count();
        }

        $fingerprints = $comments
            ->map(fn (TaskComment $comment): string => UserNotificationRead::fingerprintFor([
                'type' => 'comment',
                'id' => (int) $comment->id,
                'revision' => (string) ($comment->created_at?->timestamp ?? 0),
            ]))
            ->all();

        $readSet = UserNotificationRead::query()
            ->where('user_id', $user->id)
            ->whereIn('fingerprint', $fingerprints)
            ->pluck('fingerprint')
            ->flip()
            ->all();

        return collect($fingerprints)
            ->reject(fn (string $fingerprint): bool => isset($readSet[$fingerprint]))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function eventNotificationsFor(User $user, string $status, string $type, string $label, int $queryLimit = 50): array
    {
        $orderColumn = $type === 'new_event' ? 'created_at' : 'updated_at';

        $query = static::eventQueryForUser($user)
            ->where('status', $status);

        // „Nowa impreza” tylko gdy przy create zaznaczono „Powiadom biuro” (task event-inquiry)
        // albo zapytanie przyszło z WWW (też tworzy ten fingerprint).
        if ($type === 'new_event' && $status === Event::STATUS_INQUIRY) {
            $query->whereHas('tasks', function ($taskQuery) {
                $taskQuery->where('description', 'like', '%event-inquiry:%');
            });
        }

        return $query
            ->orderByDesc($orderColumn)
            ->limit($queryLimit)
            ->get()
            ->map(fn (Event $event): array => static::formatEventNotification($event, $type, $label))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function insuranceAlertNotificationsFor(User $user, int $queryLimit = 15): array
    {
        if (! Schema::hasColumn('events', 'insurance_status') || ! Schema::hasTable('event_day_insurance')) {
            return [];
        }

        return static::eventQueryForUser($user)
            ->whereIn('status', [...Event::getConfirmedLikeStatuses(), Event::STATUS_TO_SETTLE])
            ->whereHas('dayInsurances', fn ($query) => $query->whereNotNull('insurance_id'))
            ->orderByDesc('updated_at')
            ->limit($queryLimit)
            ->get()
            ->filter(fn (Event $event): bool => ! $event->isInsuranceCompleted())
            ->map(fn (Event $event): array => static::formatInsuranceAlertNotification($event))
            ->values()
            ->all();
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread_messages: int}
     */
    private static function messageNotificationsFor(User $user, int $queryLimit = 20): array
    {
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
                    'id' => (int) $conversation->id,
                    'revision' => (string) (optional($conversation->last_message_at)?->timestamp ?? now()->timestamp),
                    'title' => $conversation->getDisplayName($user),
                    'meta' => $unreadCount.' nieprzeczytanych wiadomości',
                    'time' => optional($conversation->last_message_at)->diffForHumans() ?? 'teraz',
                    'url' => route('filament.admin.pages.chat'),
                    'at' => optional($conversation->last_message_at)?->timestamp ?? now()->timestamp,
                    'color' => 'blue',
                ];
            }

            $unreadMessagesCount += $unreadCount;
        }

        return [
            'items' => collect($conversationNotifications)
                ->sortByDesc(fn (array $item) => $item['at'] ?? 0)
                ->take($queryLimit)
                ->values()
                ->all(),
            'unread_messages' => $unreadMessagesCount,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function invoiceRequestNotificationsFor(User $user, int $queryLimit = 50): array
    {
        if (! static::userCanSeeInvoiceRequests($user) || ! Schema::hasTable('client_invoice_requests')) {
            return [];
        }

        return ClientInvoiceRequest::query()
            ->with(['event:id,name'])
            ->where('status', ClientInvoiceRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->limit($queryLimit)
            ->get()
            ->map(function (ClientInvoiceRequest $request): array {
                $eventName = $request->event?->name ?? ('Impreza #'.$request->event_id);

                return [
                    'type' => 'invoice_request',
                    'id' => (int) $request->id,
                    'revision' => (string) ($request->created_at?->timestamp ?? 0),
                    'title' => Str::limit($request->company_name, 50),
                    'meta' => 'NIP: '.$request->nip.' | '.$eventName,
                    'time' => optional($request->created_at)->diffForHumans() ?? 'teraz',
                    'url' => AdminPanelUrls::clientInvoiceRequestsInbox([
                        'status' => ['value' => ClientInvoiceRequest::STATUS_PENDING],
                    ]),
                    'at' => optional($request->created_at)?->timestamp ?? now()->timestamp,
                    'color' => 'indigo',
                ];
            })
            ->all();
    }

    public static function getUnreadCountsForUser(int $userId): array
    {
        return static::getTopbarDataForUser($userId)['counts'];
    }

    public static function getTopbarDataForUser(
        int $userId,
        int $limitPerType = 4,
        int $combinedLimit = 10,
        int $taskQueryLimit = 30,
        bool $fresh = false,
        bool $includeReadItems = false,
    ): array {
        $cacheKey = "user_notifications_{$userId}_{$limitPerType}_{$combinedLimit}_{$taskQueryLimit}"
            .($includeReadItems ? '_all' : '');

        try {
            if ($fresh) {
                Cache::forget($cacheKey);
            }

            return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($userId, $limitPerType, $combinedLimit, $taskQueryLimit, $includeReadItems) {
                $user = User::find($userId);

                if (! $user) {
                    return static::emptyTopbarPayload();
                }

                $queryLimit = max($limitPerType, min(50, $combinedLimit));

                $taskItems = static::finalizeItems(static::taskNotificationsFor($user, $taskQueryLimit), $userId);
                $commentItems = static::finalizeItems(static::commentNotificationsFor($user, $taskQueryLimit), $userId);
                $newEventItems = static::finalizeItems(
                    static::eventNotificationsFor($user, Event::STATUS_INQUIRY, 'new_event', 'Nowa impreza', $queryLimit),
                    $userId,
                );
                $confirmedEventItems = static::finalizeItems(
                    static::eventNotificationsFor($user, Event::STATUS_CONFIRMED, 'event', 'Nowe potwierdzenie', $queryLimit),
                    $userId,
                );
                $pendingCancellationItems = static::finalizeItems(
                    static::eventNotificationsFor($user, Event::STATUS_PENDING_CANCELLATION, 'pending_cancellation_event', 'Do anulacji', $queryLimit),
                    $userId,
                );
                $insuranceItems = static::finalizeItems(static::insuranceAlertNotificationsFor($user), $userId);
                $eventItems = static::sortNewestFirst(
                    collect($confirmedEventItems)->merge($insuranceItems)->values()->all(),
                );

                $messageData = static::messageNotificationsFor($user, $queryLimit);
                $messageItems = static::finalizeItems($messageData['items'], $userId);

                $invoiceItems = static::finalizeItems(static::invoiceRequestNotificationsFor($user, $queryLimit), $userId);

                // Licznik niezależny od limitu listy (podzadanie / 31. zadanie musi podbić badge).
                $tasksCount = static::unreadTaskCountFor($user);
                $commentsCount = static::unreadCommentCountFor($user);
                $newEventsCount = static::unreadCount($newEventItems);
                $confirmedEventsCount = static::unreadCount($eventItems);
                $pendingCancellationEventsCount = static::unreadCount($pendingCancellationItems);
                $invoiceRequestsCount = static::unreadCount($invoiceItems);
                $unreadMessagesCount = (int) $messageData['unread_messages'];

                $listForType = function (array $items) use ($limitPerType, $includeReadItems): array {
                    if ($includeReadItems) {
                        return collect($items)
                            ->sortBy([
                                fn (array $item): int => ($item['is_read'] ?? false) ? 1 : 0,
                                fn (array $item): int => -((int) ($item['revision'] ?? 0)),
                            ])
                            ->take($limitPerType)
                            ->values()
                            ->all();
                    }

                    return self::unreadList($items, $limitPerType);
                };

                $itemsByType = [
                    'task' => $listForType($taskItems),
                    'comment' => $listForType($commentItems),
                    'new_event' => $listForType($newEventItems),
                    'event' => $listForType($eventItems),
                    'pending_cancellation_event' => $listForType($pendingCancellationItems),
                    'invoice_request' => $listForType($invoiceItems),
                    'message' => $listForType($messageItems),
                ];

                $items = collect(array_merge(
                    $itemsByType['comment'],
                    $itemsByType['task'],
                    $itemsByType['new_event'],
                    $itemsByType['event'],
                    $itemsByType['pending_cancellation_event'],
                    $itemsByType['invoice_request'],
                    $itemsByType['message'],
                ))
                    ->take($combinedLimit)
                    ->values()
                    ->all();

                $workCount = $tasksCount + $commentsCount;
                $eventsGroupCount = $newEventsCount
                    + $confirmedEventsCount
                    + $pendingCancellationEventsCount
                    + $invoiceRequestsCount;

                $totalUnread = $workCount
                    + $eventsGroupCount
                    + $unreadMessagesCount;

                $itemsByGroup = [
                    'work' => static::sortNewestFirst(array_merge(
                        $itemsByType['task'],
                        $itemsByType['comment'],
                    )),
                    'events' => static::sortNewestFirst(array_merge(
                        $itemsByType['new_event'],
                        $itemsByType['event'],
                        $itemsByType['pending_cancellation_event'],
                        $itemsByType['invoice_request'],
                    )),
                    'messages' => $itemsByType['message'],
                ];

                return [
                    'counts' => [
                        'tasks' => $tasksCount,
                        'messages' => $unreadMessagesCount,
                        'comments' => $commentsCount,
                        'new_events' => $newEventsCount,
                        'confirmed_events' => $confirmedEventsCount,
                        'pending_cancellation_events' => $pendingCancellationEventsCount,
                        'invoice_requests' => $invoiceRequestsCount,
                        'work' => $workCount,
                        'events' => $eventsGroupCount,
                        'total_unread' => $totalUnread,
                    ],
                    'items' => $items,
                    'items_by_type' => $itemsByType,
                    'items_by_group' => $itemsByGroup,
                ];
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('NotificationService::getTopbarDataForUser failed: '.$e->getMessage(), [
                'user_id' => $userId,
            ]);

            return static::emptyTopbarPayload();
        }
    }

    public static function markTaskAsRead(int $userId, Task $task): void
    {
        static::markAsRead($userId, UserNotificationRead::fingerprintFor([
            'type' => 'task',
            'id' => (int) $task->id,
            'revision' => (string) ($task->updated_at?->timestamp ?? 0),
        ]));
    }

    public static function markTaskAsUnread(int $userId, Task $task): void
    {
        static::markAsUnread($userId, UserNotificationRead::fingerprintFor([
            'type' => 'task',
            'id' => (int) $task->id,
            'revision' => (string) ($task->updated_at?->timestamp ?? 0),
        ]));
    }

    public static function markCommentAsRead(int $userId, TaskComment $comment): void
    {
        static::markAsRead($userId, UserNotificationRead::fingerprintFor([
            'type' => 'comment',
            'id' => (int) $comment->id,
            'revision' => (string) ($comment->created_at?->timestamp ?? 0),
        ]));
    }

    /**
     * Własne utworzenie zadania biurowego nie ma podbijać licznika autora.
     * Zadania systemowe / checklisty pilota zostawiamy — assignee ma je zobaczyć.
     */
    public static function acknowledgeOwnTaskCreation(Task $task): void
    {
        $authorId = (int) ($task->author_id ?? 0);
        $authId = (int) (Auth::id() ?? 0);

        if ($authId <= 0 || $authId !== $authorId) {
            return;
        }

        $source = $task->source instanceof TaskSource
            ? $task->source
            : TaskSource::tryFrom((string) ($task->source ?? ''));

        if (in_array($source, [TaskSource::System, TaskSource::PilotChecklist], true)) {
            return;
        }

        static::markTaskAsRead($authorId, $task);
    }

    public static function markTaskCommentNotificationsAsRead(int $userId, int $taskId): void
    {
        if (! Schema::hasTable('user_notification_reads') || ! Schema::hasTable('task_comments')) {
            return;
        }

        $now = now();
        $marked = false;

        TaskComment::query()
            ->where('task_id', $taskId)
            ->orderBy('id')
            ->get(['id', 'created_at'])
            ->each(function (TaskComment $comment) use ($userId, $now, &$marked): void {
                UserNotificationRead::query()->updateOrCreate(
                    [
                        'user_id' => $userId,
                        'fingerprint' => UserNotificationRead::fingerprintFor([
                            'type' => 'comment',
                            'id' => (int) $comment->id,
                            'revision' => (string) ($comment->created_at?->timestamp ?? 0),
                        ]),
                    ],
                    [
                        'read_at' => $now,
                    ],
                );

                $marked = true;
            });

        if ($marked) {
            static::clearCacheForUser($userId);
        }
    }

    public static function clearCacheForTaskCommentStakeholders(TaskComment $comment): void
    {
        $comment->loadMissing('task');

        $task = $comment->task;

        if (! $task) {
            return;
        }

        // Touch → kolumna „Modyfikacja” i revision zadania też się ruszają.
        $task->touch();
        $task->refresh();

        // Autor komentarza nie powinien dostać powiadomienia o własnej aktywności
        // (nowy fingerprint zadania po touch + własny komentarz).
        $commenterId = (int) ($comment->user_id ?? 0);
        if ($commenterId > 0) {
            static::markCommentAsRead($commenterId, $comment);
            static::markTaskAsRead($commenterId, $task);
        }

        // Czyść cache wszystkich stakeholderów, w tym autora komentarza —
        // gdy owner=assignee=autor, inaczej nikt nie dostaje odświeżenia topbara.
        collect([$task->assignee_id, $task->author_id, $comment->user_id])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->each(fn (int $userId) => static::clearCacheForUser($userId));
    }

    public static function markAsRead(int $userId, string $fingerprint): void
    {
        if (! Schema::hasTable('user_notification_reads') || $fingerprint === '') {
            return;
        }

        UserNotificationRead::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'fingerprint' => $fingerprint,
            ],
            [
                'read_at' => now(),
            ],
        );

        static::clearCacheForUser($userId);
    }

    public static function markAsUnread(int $userId, string $fingerprint): void
    {
        if (! Schema::hasTable('user_notification_reads') || $fingerprint === '') {
            return;
        }

        UserNotificationRead::query()
            ->where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->delete();

        static::clearCacheForUser($userId);
    }

    public static function isRead(int $userId, string $fingerprint): bool
    {
        if (! Schema::hasTable('user_notification_reads') || $fingerprint === '') {
            return false;
        }

        return UserNotificationRead::query()
            ->where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->exists();
    }

    /**
     * @return array{counts: array<string, int>, items: list<array<string, mixed>>, type_labels: array<string, string>}
     */
    public static function getInboxDataForUser(int $userId, ?string $typeFilter = null, bool $unreadOnly = false): array
    {
        // Inbox pokazuje też przeczytane (żeby dało się oznaczyć jako nieprzeczytane).
        $data = static::getTopbarDataForUser($userId, 50, 200, taskQueryLimit: 50, includeReadItems: true);

        $items = collect();

        foreach ($data['items_by_type'] as $type => $list) {
            foreach ($list as $item) {
                $items->push(array_merge(['type' => $type], $item));
            }
        }

        if ($typeFilter && $typeFilter !== 'all') {
            $items = $items->where('type', $typeFilter);
        }

        if ($unreadOnly) {
            $items = $items->filter(fn (array $item): bool => ! ($item['is_read'] ?? false));
        }

        return [
            'counts' => $data['counts'],
            'items' => $items
                ->sortByDesc(fn (array $item): int => (int) ($item['revision'] ?? 0))
                ->values()
                ->all(),
            'type_labels' => static::inboxTypeLabels(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function inboxTypeLabels(): array
    {
        return [
            'all' => 'Wszystkie',
            'task' => 'Zadania',
            'comment' => 'Komentarze',
            'new_event' => 'Nowe imprezy',
            'event' => 'Nowe potwierdzenia',
            'insurance_alert' => 'Ubezpieczenie',
            'pending_cancellation_event' => 'Do anulacji',
            'invoice_request' => 'Wnioski o fakturę',
            'message' => 'Wiadomości',
        ];
    }

    public static function markAllAsRead(int $userId): int
    {
        if (! Schema::hasTable('user_notification_reads')) {
            return 0;
        }

        $data = static::getInboxDataForUser($userId);
        $marked = 0;

        foreach ($data['items'] as $item) {
            $fingerprint = (string) ($item['fingerprint'] ?? '');

            if ($fingerprint === '' || ($item['is_read'] ?? false)) {
                continue;
            }

            static::markAsRead($userId, $fingerprint);
            $marked++;
        }

        return $marked;
    }

    public static function clearCacheForUser(int $userId): void
    {
        Cache::forget(sprintf(
            'user_notifications_%d_%d_%d_%d',
            $userId,
            self::TOPBAR_LIMIT_PER_TYPE,
            self::TOPBAR_COMBINED_LIMIT,
            self::TOPBAR_TASK_QUERY_LIMIT,
        ));

        foreach ([[4, 10, 30], [15, 15, 30], [50, 200, 30], [50, 200, 50]] as [$perType, $combined, $taskQueryLimit]) {
            Cache::forget("user_notifications_{$userId}_{$perType}_{$combined}_{$taskQueryLimit}");
            Cache::forget("user_notifications_{$userId}_{$perType}_{$combined}_{$taskQueryLimit}_all");
        }

        foreach ([[4, 10], [15, 15], [50, 50], [50, 200]] as [$perType, $combined]) {
            Cache::forget("user_notifications_{$userId}_{$perType}_{$combined}");
        }
    }

    public static function clearCacheForFinanceUsers(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'admin', 'biuro', 'ksiegowosc']))
            ->pluck('id')
            ->each(fn (int $userId) => static::clearCacheForUser($userId));
    }

    public static function markMessagesAsRead(int $userId, int $conversationId): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->conversations()->updateExistingPivot($conversationId, [
                'last_read_at' => now(),
            ]);

            self::clearCacheForUser($userId);
        }
    }
}

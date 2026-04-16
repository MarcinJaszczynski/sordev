<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Models\Message;
use App\Services\NotificationService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class NotificationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.notifications';

    protected static ?int $sort = -10; // Wysoko w liście widgetów

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    // Odświeżaj co 30 sekund
    protected static ?string $pollingInterval = '30s';

    public ?string $taskFilter = null; // 'all', 'new', 'in_progress'
    public ?string $sortTasks = 'newest'; // 'newest', 'oldest'

    public function getViewData(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [
                'newTasksCount' => 0,
                'unreadMessagesCount' => 0,
                'pendingCancellationEventsCount' => 0,
                'recentTasks' => collect(),
                'recentMessages' => collect(),
                'pendingCancellationEvents' => collect(),
            ];
        }

        // Pobierz pełne dane do topbara (liczniki + listy)
        $topbarData = \App\Services\NotificationService::getTopbarDataForUser($user->id);

        // Ostatnie zadania
        $tasksQuery = Task::where('assignee_id', $user->id)
            ->whereHas('status', function ($query) {
                $query->whereIn('name', ['Do zrobienia', 'W trakcie', 'Oczekuje na weryfikację']);
            })
            ->with('status');

        if ($this->taskFilter === 'new') {
            $tasksQuery->whereHas('status', fn($q) => $q->where('name', 'Do zrobienia'));
        } elseif ($this->taskFilter === 'in_progress') {
            $tasksQuery->whereHas('status', fn($q) => $q->where('name', 'W trakcie'));
        }

        if ($this->sortTasks === 'oldest') {
            $tasksQuery->orderBy('created_at', 'asc');
        } else {
            $tasksQuery->orderBy('created_at', 'desc');
        }

        $recentTasks = $tasksQuery->limit(5)->get();

        // Ostatnie wiadomości
        $recentMessages = Message::whereHas('conversation.participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>', now()->subDays(3))
            ->with(['user', 'conversation'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Imprezy do anulacji (liczba i lista)
        $pendingCancellationEventsCount = $topbarData['counts']['pending_cancellation_events'] ?? 0;
        $pendingCancellationEvents = collect($topbarData['items_by_type']['pending_cancellation_event'] ?? [])->take($pendingCancellationEventsCount);

        return [
            'newTasksCount' => $topbarData['counts']['tasks'] ?? 0,
            'unreadMessagesCount' => $topbarData['counts']['messages'] ?? 0,
            'pendingCancellationEventsCount' => $pendingCancellationEventsCount,
            'recentTasks' => $recentTasks,
            'recentMessages' => $recentMessages,
            'pendingCancellationEvents' => $pendingCancellationEvents,
        ];
    }
}

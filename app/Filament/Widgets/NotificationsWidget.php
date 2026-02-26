<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Models\Message;
use App\Services\NotificationService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class NotificationsWidget extends Widget
{
<<<<<<< HEAD
    protected static string $view = 'filament.widgets.notifications-no-alpine';
=======
    protected static string $view = 'filament.widgets.notifications';
>>>>>>> a992b356d2e98be8f4891f442e8b570ff0a9faa5

    protected static ?int $sort = -10; // Wysoko w liście widgetów

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    // Odświeżaj co 30 sekund
    protected static ?string $pollingInterval = '30s';

    public function getViewData(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [
                'newTasksCount' => 0,
                'unreadMessagesCount' => 0,
                'recentTasks' => collect(),
                'recentMessages' => collect(),
            ];
        }

        // Używaj serwisu do pobierania liczników
        $counts = NotificationService::getUnreadCountsForUser($user->id);

        // Ostatnie zadania
        $recentTasks = Task::where('assignee_id', $user->id)
            ->whereHas('status', function ($query) {
                $query->whereIn('name', ['Nowe', 'W toku', 'Do wykonania']);
            })
            ->with('status')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

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

        return [
            'newTasksCount' => $counts['tasks'],
            'unreadMessagesCount' => $counts['messages'],
            'recentTasks' => $recentTasks,
            'recentMessages' => $recentMessages,
        ];
    }
}

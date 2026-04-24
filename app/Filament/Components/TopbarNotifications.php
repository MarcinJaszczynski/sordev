<?php

namespace App\Filament\Components;

use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class TopbarNotifications extends Component
{
    public function render(): View
    {
        $user = Auth::user();

        if (! $user) {
            $data = [
                'newTasksCount' => 0,
                'unreadMessagesCount' => 0,
                'commentsCount' => 0,
                'newEventsCount' => 0,
                'confirmedEventsCount' => 0,
                'pendingCancellationEventsCount' => 0,
                'importantCount' => 0,
                'notificationItems' => [],
                'notificationItemsByType' => [],
            ];
        } else {
            $notificationData = NotificationService::getTopbarDataForUser($user->id);
            $counts = $notificationData['counts'];
            $data = [
                'newTasksCount' => $counts['tasks'],
                'unreadMessagesCount' => $counts['messages'],
                'commentsCount' => $counts['comments'] ?? 0,
                'newEventsCount' => $counts['new_events'] ?? 0,
                'confirmedEventsCount' => $counts['confirmed_events'] ?? 0,
                'pendingCancellationEventsCount' => $counts['pending_cancellation_events'] ?? 0,
                'importantCount' => $counts['important'] ?? 0,
                'notificationItems' => $notificationData['items'] ?? [],
                'notificationItemsByType' => $notificationData['items_by_type'] ?? [],
            ];
        }

        return view('filament.components.topbar-notifications', $data);
    }
}

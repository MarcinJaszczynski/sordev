<?php

namespace App\Filament\Components;

use App\Services\NotificationService;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class TopbarNotifications extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        
        if (!$user) {
            $data = [
                'newTasksCount' => 0,
                'unreadMessagesCount' => 0,
            ];
        } else {
            $counts = NotificationService::getUnreadCountsForUser($user->id);
            $data = [
                'newTasksCount' => $counts['tasks'],
                'unreadMessagesCount' => $counts['messages'],
            ];
        }
        
        return view('filament.components.topbar-notifications', $data);
    }
}

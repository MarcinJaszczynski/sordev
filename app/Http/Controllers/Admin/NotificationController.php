<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getCounts(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'tasks' => 0,
                'messages' => 0,
                'comments' => 0,
                'new_events' => 0,
                'confirmed_events' => 0,
                'pending_cancellation_events' => 0,
                'important' => 0,
                'counts' => [
                    'tasks' => 0,
                    'messages' => 0,
                    'comments' => 0,
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
            ]);
        }

        $notificationData = NotificationService::getTopbarDataForUser($user->id);
        $counts = $notificationData['counts'];

        return response()->json([
            'tasks' => $counts['tasks'],
            'messages' => $counts['messages'],
            'comments' => $counts['comments'] ?? 0,
            'new_events' => $counts['new_events'] ?? 0,
            'confirmed_events' => $counts['confirmed_events'] ?? 0,
            'pending_cancellation_events' => $counts['pending_cancellation_events'] ?? 0,
            'important' => $counts['important'] ?? 0,
            'counts' => $counts,
            'items' => $notificationData['items'] ?? [],
            'items_by_type' => $notificationData['items_by_type'] ?? [
                'task' => [],
                'comment' => [],
                'new_event' => [],
                'event' => [],
                'pending_cancellation_event' => [],
                'message' => [],
            ],
        ]);
    }
}

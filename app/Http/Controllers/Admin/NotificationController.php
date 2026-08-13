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
                'invoice_requests' => 0,
                'work' => 0,
                'events' => 0,
                'total_unread' => 0,
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
            ]);
        }

        $notificationData = NotificationService::getTopbarDataForUser(
            $user->id,
            NotificationService::TOPBAR_LIMIT_PER_TYPE,
            NotificationService::TOPBAR_COMBINED_LIMIT,
            NotificationService::TOPBAR_TASK_QUERY_LIMIT,
            fresh: true,
        );
        $counts = $notificationData['counts'];

        return response()->json([
            'tasks' => $counts['tasks'],
            'messages' => $counts['messages'],
            'comments' => $counts['comments'] ?? 0,
            'new_events' => $counts['new_events'] ?? 0,
            'confirmed_events' => $counts['confirmed_events'] ?? 0,
            'pending_cancellation_events' => $counts['pending_cancellation_events'] ?? 0,
            'invoice_requests' => $counts['invoice_requests'] ?? 0,
            'work' => $counts['work'] ?? 0,
            'events' => $counts['events'] ?? 0,
            'total_unread' => $counts['total_unread'] ?? 0,
            'counts' => $counts,
            'items' => $notificationData['items'] ?? [],
            'items_by_type' => $notificationData['items_by_type'] ?? [
                'task' => [],
                'comment' => [],
                'new_event' => [],
                'event' => [],
                'pending_cancellation_event' => [],
                'invoice_request' => [],
                'message' => [],
            ],
            'items_by_group' => $notificationData['items_by_group'] ?? [
                'work' => [],
                'events' => [],
                'messages' => [],
            ],
        ]);
    }

    public function markRead(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $fingerprint = (string) request()->input('fingerprint', '');

        if ($fingerprint === '') {
            return response()->json(['ok' => false], 422);
        }

        NotificationService::markAsRead($user->id, $fingerprint);

        return response()->json(['ok' => true]);
    }
}

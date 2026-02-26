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
        
        if (!$user) {
            return response()->json([
                'tasks' => 0,
                'messages' => 0,
            ]);
        }
        
        $counts = NotificationService::getUnreadCountsForUser($user->id);
        
        return response()->json([
            'tasks' => $counts['tasks'],
            'messages' => $counts['messages'],
        ]);
    }
}

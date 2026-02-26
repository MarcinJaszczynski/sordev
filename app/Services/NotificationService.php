<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * Pobiera liczbę nowych powiadomień dla użytkownika
     */
    public static function getUnreadCountsForUser(int $userId): array
    {
        $cacheKey = "user_notifications_{$userId}";
        
        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($userId) {
            $user = User::find($userId);
            
            if (!$user) {
                return ['tasks' => 0, 'messages' => 0, 'task_updates' => 0];
            }
            
            // Zadania gdzie użytkownik jest zleceniobiorcą (assignee) - aktywne statusy
            $myActiveTasks = Task::where('assignee_id', $user->id)
                ->whereHas('status', function ($query) {
                    $query->whereIn('name', ['Do zrobienia', 'W trakcie', 'Oczekuje na weryfikację']);
                })
                ->count();
                
            // Zadania gdzie użytkownik jest autorem - wszystkie niezakończone
            $myAuthoredTasks = Task::where('author_id', $user->id)
                ->whereHas('status', function ($query) {
                    $query->whereNotIn('name', ['Zakończone', 'Anulowane']);
                })
                ->count();
                
            // Zadania ze zmianami statusu w ostatnich 24h (gdzie jestem autorem lub zleceniobiorcą)
            $taskUpdatesCount = Task::where(function ($query) use ($user) {
                    $query->where('assignee_id', $user->id)
                          ->orWhere('author_id', $user->id);
                })
                ->where('updated_at', '>', now()->subDay())
                ->count();
                
            $newTasksCount = $myActiveTasks + $myAuthoredTasks;
                
            // Nieprzeczytane wiadomości w konwersacjach użytkownika
            $unreadMessagesCount = 0;
            $userConversations = $user->conversations()->with('messages')->get();
            
            foreach ($userConversations as $conversation) {
                $lastReadAt = $conversation->pivot->last_read_at ?? $conversation->pivot->joined_at ?? now()->subWeek();
                $unreadCount = $conversation->messages()
                    ->where('user_id', '!=', $user->id)
                    ->where('created_at', '>', $lastReadAt)
                    ->count();
                $unreadMessagesCount += $unreadCount;
            }
            
            return [
                'tasks' => $newTasksCount,
                'messages' => $unreadMessagesCount,
                'task_updates' => $taskUpdatesCount,
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

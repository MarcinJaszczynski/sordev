<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends BaseApiController
{
    public function board(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to_me' => ['nullable', 'boolean'],
            'status_id' => ['nullable', 'integer', 'exists:task_statuses,id'],
        ]);

        $tasksQuery = Task::query()->with([
            'status:id,name,color,order',
            'author:id,name',
            'assignee:id,name',
        ]);

        if ((bool) ($validated['assigned_to_me'] ?? false)) {
            $tasksQuery->where('assignee_id', $request->user()->id);
        }

        if (! empty($validated['status_id'])) {
            $tasksQuery->where('status_id', (int) $validated['status_id']);
        }

        $tasks = $tasksQuery
            ->orderBy('status_id')
            ->orderBy('order')
            ->limit(500)
            ->get();

        $statuses = TaskStatus::query()
            ->orderBy('order')
            ->get(['id', 'name', 'color', 'order']);

        return $this->success([
            'statuses' => $statuses,
            'tasks' => $tasks,
        ]);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        if (! $this->canModifyTask($request->user(), $task)) {
            return $this->error('Brak uprawnien do modyfikacji tego zadania.', [], 403);
        }

        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'exists:task_statuses,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $task->status_id = (int) $validated['status_id'];

        if (isset($validated['order'])) {
            $task->order = (int) $validated['order'];
        }

        $task->save();

        return $this->success($task->fresh(['status:id,name,color,order']), 'Zadanie zostalo przeniesione.');
    }

    protected function canModifyTask($user, Task $task): bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $task->author_id === $user->id || $task->assignee_id === $user->id;
    }
}

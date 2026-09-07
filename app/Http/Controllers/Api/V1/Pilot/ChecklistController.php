<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Models\Task;
use App\Services\PilotAccessService;
use App\Services\PilotChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(Event $event, PilotChecklistService $checklist): JsonResponse
    {
        $this->authorizePilotView($event);

        $tasks = $checklist->tasksForEvent($event)->map(fn (Task $task) => [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status_id' => $task->status_id,
            'status_name' => $task->status?->name,
            'is_done' => strcasecmp((string) ($task->status?->name ?? ''), 'Zakończone') === 0,
            'order' => $task->order,
            'input_type' => $task->checklistInputType()->value,
            'input_label' => $task->checklist_input_label,
            'input_required' => (bool) $task->checklist_input_required,
            'input_unit' => $task->checklist_input_unit,
            'response' => $task->checklist_response,
        ])->values();

        return $this->success([
            'event_id' => $event->id,
            'progress' => $checklist->progressFor($event),
            'tasks' => $tasks,
            'can_edit' => app(PilotAccessService::class)->hasFullAccess($event, request()->user()),
        ]);
    }

    public function toggle(Request $request, Event $event, Task $task, PilotChecklistService $checklist): JsonResponse
    {
        $this->authorizePilotView($event);
        $this->assertPilotCanMutate($event);

        abort_unless(
            $task->taskable_type === Event::class && (int) $task->taskable_id === (int) $event->id,
            404
        );

        $owned = $checklist->tasksForEvent($event)->contains(fn (Task $row) => (int) $row->id === (int) $task->id);
        abort_unless($owned, 404);

        try {
            if ($task->checklistInputType()->requiresValue() && $request->has('response')) {
                $checklist->saveResponse($task, $request->input('response'));
                $task->refresh();
            }

            $checklist->toggleDone($task);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->error(
                collect($exception->errors())->flatten()->first() ?? 'Nie udało się zaktualizować checklisty.',
                [],
                422
            );
        }

        return $this->success(
            $this->show($event, $checklist)->getData(true)['data'],
            'Zaktualizowano checklistę.'
        );
    }
}

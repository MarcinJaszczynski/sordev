<?php

namespace App\Support\Tasks;

use App\Models\Task;
use App\Models\TaskStatus;

/**
 * Kolory statusów zadań — paleta z mockupu (docs/zadania poprawione).
 * Mapowanie po nazwie TaskStatus z seedera, bez zmiany modelu.
 */
final class TaskStatusPresentation
{
    public const TONE_TODO = 'todo';

    public const TONE_PROGRESS = 'progress';

    public const TONE_DONE = 'done';

    public const TONE_CANCEL = 'cancel';

    public const TONE_NEUTRAL = 'neutral';

    /**
     * @return self::TONE_*
     */
    public static function toneForStatus(?TaskStatus $status): string
    {
        $name = trim((string) ($status?->name ?? ''));

        return match (true) {
            $name === 'Do zrobienia' => self::TONE_TODO,
            $name === 'W trakcie', $name === 'Oczekuje na weryfikację' => self::TONE_PROGRESS,
            $name === 'Zakończone', $name === 'Zaakceptowane', $name === 'Zarchiwizowane' => self::TONE_DONE,
            $name === 'Anulowane' => self::TONE_CANCEL,
            default => self::TONE_NEUTRAL,
        };
    }

    /**
     * @return self::TONE_*
     */
    public static function toneForTask(Task $task): string
    {
        $task->loadMissing('status');

        return self::toneForStatus($task->status);
    }

    public static function labelForTask(Task $task): string
    {
        $task->loadMissing('status');

        $name = trim((string) ($task->status?->name ?? ''));

        return $name !== '' ? $name : '—';
    }

    /**
     * Klasa CSS pilla (scoped pod .tasks-split-view / .task-status-pill).
     */
    public static function pillClass(?TaskStatus $status): string
    {
        return 'task-status-pill task-status-pill--'.self::toneForStatus($status);
    }

    public static function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $letters = [];

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters[] = mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== [] ? implode('', $letters) : '?';
    }
}

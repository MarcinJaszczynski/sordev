<?php

namespace App\Support\Tasks;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentStore
{
    private const DISK = 'public';

    /**
     * @param  array<int, string|null>  $filePaths
     */
    public static function storeMany(Task $task, array $filePaths, ?int $userId): void
    {
        foreach (array_filter($filePaths) as $path) {
            static::storeOne($task, (string) $path, $userId);
        }
    }

    public static function storeOne(Task $task, string $path, ?int $userId): TaskAttachment
    {
        $disk = Storage::disk(self::DISK);

        return $task->attachments()->create([
            'file_path' => $path,
            'name' => basename($path),
            'mime_type' => $disk->exists($path) ? $disk->mimeType($path) : null,
            'size' => $disk->exists($path) ? $disk->size($path) : null,
            'user_id' => $userId,
        ]);
    }
}

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
        $exists = $disk->exists($path);

        return $task->attachments()->create([
            'file_path' => $path,
            'name' => basename($path),
            'mime_type' => $exists ? static::detectMimeType($disk, $path) : null,
            'size' => $exists ? $disk->size($path) : null,
            'user_id' => $userId,
        ]);
    }

    private static function detectMimeType(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $path): ?string
    {
        try {
            return $disk->mimeType($path);
        } catch (\Throwable) {
            return null;
        }
    }
}

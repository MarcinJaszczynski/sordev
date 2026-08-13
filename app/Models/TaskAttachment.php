<?php

namespace App\Models;

use App\Support\StoragePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_path',
        'mime_type',
        'size',
        'task_id',
        'user_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFilenameAttribute(): string
    {
        return $this->name ?: basename((string) $this->file_path);
    }

    public function setFilePathAttribute(?string $value): void
    {
        $this->attributes['file_path'] = StoragePath::normalize($value);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            $path = $attachment->file_path;
            if (! is_string($path) || $path === '') {
                return;
            }

            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        });
    }

    public function getPublicUrlAttribute(): ?string
    {
        return $this->download_url;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return route('admin.task-attachments.download', ['attachment' => $this->getKey()]);
    }

    public function getReadableSizeAttribute(): ?string
    {
        if (! $this->size) {
            return null;
        }

        if ($this->size >= 1024 * 1024) {
            return number_format($this->size / (1024 * 1024), 2).' MB';
        }

        return number_format($this->size / 1024, 1).' KB';
    }
}

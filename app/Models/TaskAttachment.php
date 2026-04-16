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

    public function getPublicUrlAttribute(): ?string
    {
        return StoragePath::publicUrl($this->file_path);
    }

    public function getReadableSizeAttribute(): ?string
    {
        if (! $this->size) {
            return null;
        }

        if ($this->size >= 1024 * 1024) {
            return number_format($this->size / (1024 * 1024), 2) . ' MB';
        }

        return number_format($this->size / 1024, 1) . ' KB';
    }
} 
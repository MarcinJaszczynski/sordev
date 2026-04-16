<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'task_id',
        'user_id',
        'author_id',
    ];

    public function getAuthorIdAttribute(): ?int
    {
        return $this->attributes['user_id'] ?? null;
    }

    public function setAuthorIdAttribute($value): void
    {
        $this->attributes['user_id'] = $value;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
} 
<?php

namespace App\Models;

use App\Support\Tasks\TaskContextRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Task extends Model implements Sortable
{
    use HasFactory, SoftDeletes, SortableTrait;

    protected $fillable = [
        'title',
        'description',
        'due_date',
        'status_id',
        'priority',
        'author_id',
        'assignee_id',
        'parent_id',
        'order',
        'taskable_type',
        'taskable_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            $task->normalizeTaskableContext();
            $task->inheritTaskableContextFromParent();
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class);
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public static function getTaskableTypeOptions(): array
    {
        return TaskContextRegistry::types();
    }

    public static function getSupportedTaskableTypes(): array
    {
        return TaskContextRegistry::supportedTypes();
    }

    public static function isSupportedTaskableType(?string $type): bool
    {
        return TaskContextRegistry::isSupported($type);
    }

    public static function getTaskableRecordOptions(?string $type): array
    {
        return TaskContextRegistry::recordOptions($type);
    }

    public static function getDefaultStatusId(): ?int
    {
        return TaskStatus::query()->where('is_default', true)->value('id')
            ?? TaskStatus::query()->orderBy('order')->value('id');
    }

    public function getTaskableTypeLabelAttribute(): ?string
    {
        return TaskContextRegistry::labelForType($this->taskable_type);
    }

    public function getTaskableLabelAttribute(): string
    {
        if (! $this->taskable_type || ! $this->taskable_id) {
            return 'Brak powiązania';
        }

        return TaskContextRegistry::labelForRecord($this->taskable)
            ?? '#' . $this->taskable_id;
    }

    public function getTaskContextLabelAttribute(): string
    {
        if (! $this->taskable_type || ! $this->taskable_id) {
            return 'Wolne / nieprzypisane';
        }

        return trim(($this->taskable_type_label ?? 'Powiązanie') . ': ' . $this->taskable_label);
    }

    protected function normalizeTaskableContext(): void
    {
        if (! $this->taskable_type || ! $this->taskable_id || ! static::isSupportedTaskableType($this->taskable_type)) {
            $this->taskable_type = null;
            $this->taskable_id = null;

            return;
        }

        $this->taskable_id = (int) $this->taskable_id;
    }

    protected function inheritTaskableContextFromParent(): void
    {
        if (($this->taskable_type && $this->taskable_id) || ! $this->parent_id) {
            return;
        }

        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();

        if (! $parent) {
            return;
        }

        $this->taskable_type = $parent->taskable_type;
        $this->taskable_id = $parent->taskable_id;
    }
} 
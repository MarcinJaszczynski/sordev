<?php

namespace App\Models;

use App\Support\StickyNotes\StickyNoteCategory;
use App\Support\Tasks\TaskContextRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StickyNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'notable_type',
        'notable_id',
        'body',
        'category',
        'created_by',
        'updated_by',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (StickyNote $note): void {
            $note->normalizeNotableContext();
            $note->category = StickyNoteCategory::normalize($note->category);
        });
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return StickyNoteCategory::label($this->category);
    }

    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    protected function normalizeNotableContext(): void
    {
        if (! $this->notable_type || ! $this->notable_id || ! TaskContextRegistry::isSupported($this->notable_type)) {
            $this->notable_type = null;
            $this->notable_id = null;

            return;
        }

        $this->notable_id = (int) $this->notable_id;
    }
}

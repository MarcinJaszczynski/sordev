<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'content',
        'type',
        'attachment_path',
        'attachment_name',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
    ];

    /**
     * Rozmowa
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Autor wiadomości
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * URL załącznika
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment_path) {
            return null;
        }

        return Storage::url($this->attachment_path);
    }

    /**
     * Sprawdź czy wiadomość ma załącznik
     */
    public function hasAttachment(): bool
    {
        return !empty($this->attachment_path);
    }

    /**
     * Sprawdź czy wiadomość jest obrazem
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Sprawdź czy wiadomość jest plikiem
     */
    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Formatuj czas utworzenia
     */
    public function getFormattedTimeAttribute(): string
    {
        if ($this->created_at->isToday()) {
            return $this->created_at->format('H:i');
        } elseif ($this->created_at->isYesterday()) {
            return 'Wczoraj ' . $this->created_at->format('H:i');
        } else {
            return $this->created_at->format('d.m.Y H:i');
        }
    }
}

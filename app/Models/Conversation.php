<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Twórca rozmowy
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Uczestnicy rozmowy
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->withPivot(['joined_at', 'last_read_at'])
                    ->withTimestamps();
    }

    /**
     * Wiadomości w rozmowie
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Ostatnia wiadomość
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * Liczba nieprzeczytanych wiadomości dla użytkownika
     */
    public function unreadCount(User $user): int
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        
        if (!$participant || !$participant->pivot->last_read_at) {
            return $this->messages()->count();
        }

        return $this->messages()
                    ->where('created_at', '>', $participant->pivot->last_read_at)
                    ->where('user_id', '!=', $user->id)
                    ->count();
    }

    /**
     * Oznacz jako przeczytane dla użytkownika
     */
    public function markAsRead(User $user): void
    {
        $this->participants()
             ->updateExistingPivot($user->id, [
                 'last_read_at' => now()
             ]);
    }

    /**
     * Sprawdź czy użytkownik jest uczestnikiem
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * Nazwa wyświetlana dla użytkownika
     */
    public function getDisplayName(User $user): string
    {
        if ($this->type === 'group') {
            return $this->title ?? 'Czat grupowy';
        }

        // Dla rozmów prywatnych: title (nazwa drugiego użytkownika)
        $otherParticipant = $this->participants()
                                 ->where('user_id', '!=', $user->id)
                                 ->first();

        $participantName = $otherParticipant?->name ?? 'Nieznany użytkownik';
        
        // Title jest teraz zawsze wymagany, więc zawsze go wyświetlamy
        return ($this->title ?? 'Rozmowa prywatna') . ' (' . $participantName . ')';
    }
}

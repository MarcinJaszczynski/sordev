<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasPanelShield, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => 'string',
            'status' => 'string',
        ];
    }

    /**
     * Filament panel access - allow super_admin and admin roles
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Check if user is active first
        if ($this->status !== 'active') {
            return false;
        }

        // Allow super_admin or admin roles
        return $this->hasRole(['super_admin', 'admin']);
    }

    /**
     * Rozmowy użytkownika
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    /**
     * Wiadomości użytkownika
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Rozmowy utworzone przez użytkownika
     */
    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /**
     * Sprawdź czy użytkownik jest online (można rozszerzyć o cache/redis)
     */
    public function isOnline(): bool
    {
        return $this->updated_at?->diffInMinutes() < 5;
    }
}

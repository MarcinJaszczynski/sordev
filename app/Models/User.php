<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
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
        'phone',
        'password',
        'type',
        'status',
        'birth_date',
        'pesel',
        'pilot_panel_access_sent_at',
        'pilot_panel_access_sent_by',
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
            'tasks_last_seen_at' => 'datetime',
            'password' => 'hashed',
            'type' => 'string',
            'status' => 'string',
            'birth_date' => 'date',
            'pilot_panel_access_sent_at' => 'datetime',
        ];
    }

    /**
     * Filament panel access - allow super_admin and admin roles
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasRole(['super_admin', 'admin']),
            'pilot' => $this->hasRole('pilot') || $this->hasRole(['super_admin', 'admin', 'biuro']),
            'portal' => $this->hasRole(['client_participant', 'client_guardian'])
                || $this->hasRole(['super_admin', 'admin', 'biuro']),
            default => false,
        };
    }

    /**
     * Rozmowy użytkownika
     */
    public function pilotPanelAccessSentByUser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pilot_panel_access_sent_by');
    }

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

    /**
     * Zapis daty urodzenia i PESEL w profilu użytkownika (np. pilota z formularza imprezy).
     *
     * @param  \DateTimeInterface|string|null  $birthDate
     */
    public static function syncPilotDemographics(?int $userId, $birthDate, ?string $pesel, ?string $phone = null): void
    {
        if (! $userId || ! Schema::hasColumn('users', 'birth_date')) {
            return;
        }

        $payload = [
            'birth_date' => null,
            'pesel' => null,
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $payload['phone'] = null;
        }

        if (filled($birthDate)) {
            $payload['birth_date'] = $birthDate;
        }

        if (filled($pesel)) {
            $payload['pesel'] = $pesel;
        }

        if (Schema::hasColumn('users', 'phone') && filled($phone)) {
            $payload['phone'] = trim($phone);
        }

        static::query()->whereKey($userId)->update($payload);
    }
}

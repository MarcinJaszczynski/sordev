<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'description',
        'ip_address',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    /**
     * Impreza
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Użytkownik który wykonał zmianę
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pobierz czytelny opis akcji
     */
    public function getReadableActionAttribute(): string
    {
        return match($this->action) {
            'created' => 'Utworzono',
            'updated' => 'Zaktualizowano',
            'deleted' => 'Usunięto',
            'program_changed' => 'Zmieniono program',
            'program_added' => 'Dodano punkt programu',
            'program_removed' => 'Usunięto punkt programu',
            'program_moved' => 'Przeniesiono punkt programu',
            'program_copied' => 'Skopiowano program',
            'status_changed' => 'Zmieniono status',
            default => ucfirst($this->action),
        };
    }

    /**
     * Pobierz kolor dla akcji (dla badge w Filament)
     */
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'created', 'program_added', 'program_copied' => 'success',
            'updated', 'program_changed', 'program_moved' => 'warning',
            'deleted', 'program_removed' => 'danger',
            'status_changed' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Formatuj wartość dla wyświetlenia
     */
    public function formatValue($value): string
    {
        if (is_null($value)) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Tak' : 'Nie';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Pobierz sformatowaną starą wartość
     */
    public function getFormattedOldValueAttribute(): string
    {
        return $this->formatValue($this->old_value);
    }

    /**
     * Pobierz sformatowaną nową wartość
     */
    public function getFormattedNewValueAttribute(): string
    {
        return $this->formatValue($this->new_value);
    }

    /**
     * Sprawdź czy zmiana jest istotna (do podświetlenia)
     */
    public function isImportantChange(): bool
    {
        return in_array($this->action, [
            'status_changed',
            'program_copied',
            'created',
        ]);
    }
}

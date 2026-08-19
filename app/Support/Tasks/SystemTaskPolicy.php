<?php

declare(strict_types=1);

namespace App\Support\Tasks;

use App\Enums\TaskSource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * Allowlista automatycznych zadań biurowych.
 *
 * Dozwolone: nowe zapytanie, zmiana liczby uczestników, status → potwierdzona.
 * Reszta fingerprintów (rezerwacje, płatności, inne statusy) nie tworzy tasków.
 */
final class SystemTaskPolicy
{
    public static function allowsFingerprint(string $fingerprint): bool
    {
        $fingerprint = trim($fingerprint);

        if ($fingerprint === '') {
            return false;
        }

        if (str_starts_with($fingerprint, 'event-inquiry:')) {
            return true;
        }

        if (str_starts_with($fingerprint, 'event-participant-count:')) {
            return true;
        }

        return (bool) preg_match('/^event-status:\d+:confirmed$/', $fingerprint);
    }

    public static function descriptionIsAllowed(?string $description): bool
    {
        $description = (string) $description;

        if ($description === '') {
            return false;
        }

        if (str_contains($description, 'event-inquiry:')) {
            return true;
        }

        if (str_contains($description, 'event-participant-count:')) {
            return true;
        }

        return (bool) preg_match('/event-status:\d+:confirmed/', $description);
    }

    public static function taskIsAllowed(Task $task): bool
    {
        $source = $task->source instanceof TaskSource
            ? $task->source
            : TaskSource::tryFrom((string) ($task->source ?? ''));

        if ($source !== TaskSource::System) {
            return true;
        }

        return self::descriptionIsAllowed($task->description);
    }

    /**
     * Ogranicza query do systemowych z dozwolonym fingerprintem
     * (lub łączy z innymi warunkami przez where/orWhere).
     */
    public static function constrainAllowedSystem(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('description', 'like', '%event-inquiry:%')
                ->orWhere('description', 'like', '%event-participant-count:%')
                ->orWhere('description', 'like', '%event-status:%:confirmed%');
        });
    }

    /**
     * Otwarte systemowe poza allowlistą — do komendy sprzątającej.
     */
    public static function constrainDisallowedOpenSystem(Builder $query): Builder
    {
        $query->where('source', TaskSource::System->value);
        TaskQueryFilters::excludeFinished($query);
        TaskQueryFilters::excludeArchived($query);

        return $query->where(function (Builder $inner): void {
            $inner->where(function (Builder $missing): void {
                $missing->where('description', 'not like', '%event-inquiry:%')
                    ->where('description', 'not like', '%event-participant-count:%')
                    ->where('description', 'not like', '%event-status:%:confirmed%');
            });
        });
    }
}

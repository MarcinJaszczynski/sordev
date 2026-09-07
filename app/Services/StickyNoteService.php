<?php

namespace App\Services;

use App\Models\StickyNote;
use App\Models\User;
use App\Support\StickyNotes\StickyNoteCategory;
use App\Support\Tasks\TaskContextRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StickyNoteService
{
    public function getStack(Model $notable, ?string $category = null): Collection
    {
        $this->assertSupportedNotable($notable);

        $query = StickyNote::query()
            ->where('notable_type', $notable::class)
            ->where('notable_id', $notable->getKey())
            ->with(['author:id,name', 'editor:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get();
    }

    public function countFor(Model $notable, ?string $category = null): int
    {
        $this->assertSupportedNotable($notable);

        $query = StickyNote::query()
            ->where('notable_type', $notable::class)
            ->where('notable_id', $notable->getKey());

        if ($category) {
            $query->where('category', $category);
        }

        return $query->count();
    }

    /**
     * Etykieta PL: „1 notatka” / „3 notatki” / „5 notatek”.
     */
    public static function countLabel(int $count): string
    {
        $abs = abs($count);
        $mod10 = $abs % 10;
        $mod100 = $abs % 100;

        if ($abs === 1) {
            $word = 'notatka';
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            $word = 'notatki';
        } else {
            $word = 'notatek';
        }

        return $count.' '.$word;
    }

    public function latest(Model $notable): ?StickyNote
    {
        return $this->getStack($notable)->first();
    }

    public function isLatest(StickyNote $note, ?int $latestId = null): bool
    {
        if (! $note->notable_type || ! $note->notable_id) {
            return false;
        }

        if ($latestId !== null) {
            return (int) $latestId === (int) $note->getKey();
        }

        $latestId = StickyNote::query()
            ->where('notable_type', $note->notable_type)
            ->where('notable_id', $note->notable_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        return (int) $latestId === (int) $note->getKey();
    }

    public function canEdit(StickyNote $note, User $user, ?int $latestNoteId = null): bool
    {
        return (int) $note->created_by === (int) $user->getKey()
            && $this->isLatest($note, $latestNoteId);
    }

    public function addNote(Model $notable, User $user, string $body, ?string $category = null): StickyNote
    {
        $this->assertSupportedNotable($notable);

        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Treść karteczki nie może być pusta.');
        }

        return StickyNote::query()->create([
            'notable_type' => $notable::class,
            'notable_id' => $notable->getKey(),
            'body' => $body,
            'category' => StickyNoteCategory::normalize($category),
            'created_by' => $user->getKey(),
        ]);
    }

    public function updateNote(StickyNote $note, User $user, string $body, ?string $category = null): StickyNote
    {
        if (! $this->canEdit($note, $user)) {
            throw new InvalidArgumentException('Możesz edytować tylko swoją najnowszą karteczkę, która nie została nadpisana kolejną.');
        }

        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Treść karteczki nie może być pusta.');
        }

        return DB::transaction(function () use ($note, $user, $body, $category): StickyNote {
            $note->refresh();

            if (! $this->canEdit($note, $user)) {
                throw new InvalidArgumentException('Możesz edytować tylko swoją najnowszą karteczkę, która nie została nadpisana kolejną.');
            }

            $note->fill([
                'body' => $body,
                'category' => StickyNoteCategory::normalize($category ?? $note->category),
                'updated_by' => $user->getKey(),
                'edited_at' => now(),
            ]);
            $note->save();

            return $note->fresh(['author:id,name', 'editor:id,name']);
        });
    }

    protected function assertSupportedNotable(Model $notable): void
    {
        if (! TaskContextRegistry::isSupported($notable::class)) {
            throw new InvalidArgumentException('Ten element nie obsługuje karteczek.');
        }
    }
}

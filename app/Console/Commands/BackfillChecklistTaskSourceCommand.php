<?php

namespace App\Console\Commands;

use App\Enums\TaskSource;
use App\Models\ChecklistTemplate;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillChecklistTaskSourceCommand extends Command
{
    protected $signature = 'tasks:backfill-checklist-source {--dry-run : Pokaż zmiany bez zapisu}';

    protected $description = 'Oznacza istniejące punkty checklisty pilota polem source=pilot_checklist';

    public function handle(): int
    {
        if (! Schema::hasColumn('tasks', 'source')) {
            $this->error('Kolumna tasks.source nie istnieje — uruchom migracje.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $titles = $this->collectChecklistTitles();

        if ($titles === []) {
            $this->warn('Brak tytułów w szablonach checklisty — nic do oznaczenia.');

            return self::SUCCESS;
        }

        $query = Task::query()
            ->where('taskable_type', Event::class)
            ->whereNull('parent_id')
            ->where(function ($inner) use ($titles): void {
                foreach ($titles as $title) {
                    $inner->orWhereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($title)]);
                }
            })
            ->where(function ($inner): void {
                $inner->whereNull('source')
                    ->orWhere('source', '!=', TaskSource::PilotChecklist->value);
            });

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Brak rekordów do aktualizacji.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry-run: oznaczono by {$count} rekordów jako pilot_checklist.");

            return self::SUCCESS;
        }

        $updated = $query->update(['source' => TaskSource::PilotChecklist->value]);

        $this->info("Zaktualizowano {$updated} rekordów checklisty pilota.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function collectChecklistTitles(): array
    {
        return ChecklistTemplate::query()
            ->with('items')
            ->get()
            ->flatMap(fn (ChecklistTemplate $template) => $template->items->pluck('title'))
            ->map(fn (?string $title): string => trim((string) $title))
            ->filter()
            ->unique(fn (string $title): string => mb_strtolower($title))
            ->values()
            ->all();
    }
}

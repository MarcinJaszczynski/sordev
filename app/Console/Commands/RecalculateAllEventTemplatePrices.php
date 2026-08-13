<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EventTemplate;
use App\Services\UnifiedPriceCalculator;
use Illuminate\Console\Command;

class RecalculateAllEventTemplatePrices extends Command
{
    protected $signature = 'event-templates:recalculate-prices {--delete-existing : Usuń istniejące ceny przed zapisem}';

    protected $description = 'Przelicz ceny dla wszystkich szablonów (UnifiedPriceCalculator + EventTemplateCalculationEngine) oraz usuń duplikaty';

    public function handle(UnifiedPriceCalculator $calculator): int
    {
        $deleteExisting = (bool) $this->option('delete-existing');
        $templates = EventTemplate::query()->orderBy('id')->get();
        $total = $templates->count();
        $processed = 0;
        $errors = 0;

        $this->info("Przeliczanie cen dla {$total} szablonów…");

        foreach ($templates as $template) {
            try {
                $calculator->recalculateForTemplate($template, $deleteExisting);
                $processed++;
                $this->info("OK #{$template->id} ({$template->name})");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("Błąd #{$template->id}: {$e->getMessage()}");
            }
        }

        $removed = $calculator->removeDuplicatePrices();
        $this->info("Usunięto duplikaty: {$removed}. Przeliczono: {$processed}/{$total}, błędów: {$errors}.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}

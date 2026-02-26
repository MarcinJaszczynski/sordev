<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventTemplate;
use App\Services\UnifiedPriceCalculator;
use Illuminate\Support\Facades\Log;

class PricingBackfillCommand extends Command
{
    protected $signature = 'pricing:backfill {--template=} {--delete-existing} {--chunk=100}';
    protected $description = 'Przelicza i nadpisuje (upsert) wszystkie ceny przy użyciu UnifiedPriceCalculator';

    public function handle(): int
    {
        $templateId = $this->option('template');
        $deleteExisting = $this->option('delete-existing');
        $chunk = (int)$this->option('chunk');
        $calc = new UnifiedPriceCalculator();

        $query = EventTemplate::query();
        if ($templateId) {
            $query->where('id', $templateId);
        }

        $total = $query->count();
        $this->info("Backfill cen (unified) dla {$total} szablonów (chunk={$chunk})");

        $processed = 0;
        $errors = 0;
        $query->chunk($chunk, function ($templates) use (&$processed, &$errors, $calc, $deleteExisting) {
            foreach ($templates as $t) {
                try {
                    $calc->recalculateForTemplate($t, (bool)$deleteExisting);
                    $processed++;
                    $this->output->write('.');
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('[pricing:backfill] Błąd: ' . $e->getMessage(), ['template_id' => $t->id]);
                    $this->output->write('E');
                }
            }
        });

        $this->newLine();
        $this->info("Zakończono. Przetworzono={$processed}, błędów={$errors}");
        return $errors === 0 ? 0 : 1;
    }
}

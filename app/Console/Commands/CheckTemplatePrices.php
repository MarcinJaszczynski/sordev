<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventTemplate;

class CheckTemplatePrices extends Command
{
    protected $signature = 'check:template-prices {id=2}';
    protected $description = 'Sprawdź ceny w punkach programu szablonu';

    public function handle()
    {
        $templateId = $this->argument('id');
        $template = EventTemplate::with(['programPoints.children'])->find($templateId);

        if (!$template) {
            $this->error("Nie znaleziono szablonu o ID {$templateId}");
            return 1;
        }

        $this->info("Szablon: {$template->name}");
        $this->line("Waluta: {$template->currency}");
        $this->line("");

        foreach ($template->programPoints as $point) {
            $this->line("Punkt: {$point->name}");
            $currencySymbol = $point->currency ? $point->currency->symbol : 'N/A';
            $this->line("  Cena: {$point->unit_price} {$currencySymbol}");
            $this->line("  Dzień: {$point->pivot->day}, Kolejność: {$point->pivot->order}");
            
            foreach ($point->children as $child) {
                $this->line("  └─ Podpunkt: {$child->name}");
                $childCurrencySymbol = $child->currency ? $child->currency->symbol : 'N/A';
                $this->line("     Cena: {$child->unit_price} {$childCurrencySymbol}");
                $this->line("     Kolejność: {$child->pivot->order}");
            }
            $this->line("");
        }

        return 0;
    }
}

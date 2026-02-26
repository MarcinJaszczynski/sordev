<?php

namespace App\Jobs;

use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Services\UnifiedPriceCalculator;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\PriceRecalcProgress;

class RecalculateAllEventTemplatePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1200;

    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $calculator = new UnifiedPriceCalculator();
        $total = EventTemplate::count();

        // Start progress for user
        if ($this->userId) {
            PriceRecalcProgress::start($this->userId, $total);
        }

        // Usuń duplikaty przed przeliczeniem
        $this->removeDuplicatePrices();

        $totalTemplates = 0;
        $totalPricesCreated = 0;
        $totalPricesAfter = 0;
        $errors = 0;

        // Globalna liczba wariantów QTY (używana do per-template oczekiwań)
        $qtyCount = \App\Models\EventTemplateQty::count();

        EventTemplate::query()
            ->orderBy('id')
            ->chunkById(25, function ($templates) use (
                $calculator,
                $qtyCount,
                &$totalTemplates,
                &$totalPricesCreated,
                &$totalPricesAfter,
                &$errors
            ) {
                foreach ($templates as $template) {
                    try {
                        // Dostępne miejsca startowe faktycznie przypisane (availability) oraz filtr starting_place=true
                        $availableStartPlaces = \App\Models\EventTemplateStartingPlaceAvailability::query()
                            ->join('places', 'places.id', '=', 'event_template_starting_place_availability.start_place_id')
                            ->where('event_template_starting_place_availability.event_template_id', $template->id)
                            ->where('event_template_starting_place_availability.available', true)
                            ->where('places.starting_place', true)
                            ->pluck('event_template_starting_place_availability.start_place_id')
                            ->unique()
                            ->values();

                        $placesForTemplate = $availableStartPlaces->count();
                        $expectedVariants = $qtyCount * max($placesForTemplate, 1);

                        $before = EventTemplatePricePerPerson::where('event_template_id', $template->id)->count();
                        $calculator->recalculateForTemplate($template);
                        $after = EventTemplatePricePerPerson::where('event_template_id', $template->id)->count();
                        $totalTemplates++;
                        $totalPricesCreated += max($after - $before, 0);
                        $totalPricesAfter += $after;

                        if ($this->userId) {
                            PriceRecalcProgress::increment($this->userId, 1);
                        }

                        if ($after < $expectedVariants) {
                            Log::warning("[WARNING] Szablon #{$template->id} warianty: actual={$after} expected={$expectedVariants} qtyCount={$qtyCount} placesForTemplate={$placesForTemplate}. Możliwe braki danych (availability, qty, ceny).");
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::error('Recalculate job error for template #' . $template->id . ': ' . $e->getMessage());
                        if ($this->userId) {
                            PriceRecalcProgress::addError($this->userId, 1);
                            PriceRecalcProgress::increment($this->userId, 1);
                        }
                    }
                }
            });

        // Dedupe at the end just in case
        $this->removeDuplicatePrices();

        // Notify requesting user (to database so it’s visible even if page was closed)
        try {
            if ($this->userId) {
                PriceRecalcProgress::finish($this->userId);
            }
            $user = \App\Models\User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title('Przeliczanie cen zakończone')
                    ->body("Szablony: {$totalTemplates}, Nowe rekordy: {$totalPricesCreated}, Razem rekordów po przeliczeniu: {$totalPricesAfter}, Błędów: {$errors}")
                    ->success()
                    ->sendToDatabase($user);

                // Opcjonalnie: e-mail (jeśli user ma e-mail)
                if (!empty($user->email)) {
                    $summary = "Szablony: {$totalTemplates}\nNowe rekordy: {$totalPricesCreated}\nRazem rekordów po przeliczeniu: {$totalPricesAfter}\nBłędów: {$errors}";
                    Mail::raw('Przeliczanie cen zakończone.\n' . $summary, function ($m) use ($user) {
                        $m->to($user->email)->subject('Podsumowanie przeliczania cen');
                    });
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send completion notification: ' . $e->getMessage());
        }
    }

    private function removeDuplicatePrices(): void
    {
        $duplicateGroups = EventTemplatePricePerPerson::select('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $records = EventTemplatePricePerPerson::where([
                'event_template_id' => $group->event_template_id,
                'event_template_qty_id' => $group->event_template_qty_id,
                'currency_id' => $group->currency_id,
                'start_place_id' => $group->start_place_id,
            ])->orderByDesc('id')->get();

            for ($i = 1; $i < $records->count(); $i++) {
                $records[$i]->delete();
            }
        }
    }
}

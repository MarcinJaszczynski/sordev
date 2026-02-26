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

class RecalculateSelectedEventTemplatePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $templateIds;
    public int $userId;
    public bool $force = false;

    public function __construct(array $templateIds, int $userId, bool $force = false)
    {
        $this->templateIds = $templateIds;
        $this->userId = $userId;
        $this->force = $force;
    }

    public function handle(): void
    {
        $calculator = new UnifiedPriceCalculator();
        $totalTemplates = 0;
        $totalPricesCreated = 0;
        $totalPricesAfter = 0;
        $errors = 0;

        foreach ($this->templateIds as $id) {
            $template = EventTemplate::withTrashed()->find($id);
            if (!$template) continue;
            try {
                $before = EventTemplatePricePerPerson::where('event_template_id', $template->id)->count();
                // Jeśli tryb force => przekazujemy flagę deleteExisting do kalkulatora
                $calculator->recalculateForTemplate($template, $this->force);
                // Zaktualizuj postęp
                if ($this->userId) {
                    PriceRecalcProgress::increment($this->userId, 1);
                }
                $after = EventTemplatePricePerPerson::where('event_template_id', $template->id)->count();
                $totalTemplates++;
                $totalPricesCreated += max($after - $before, 0);
                $totalPricesAfter += $after;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Recalculate selected job error for template #' . $template->id . ': ' . $e->getMessage());
                if ($this->userId) {
                    PriceRecalcProgress::addError($this->userId, 1);
                }
            }
        }

        // Notify user
        try {
            if ($this->userId) {
                PriceRecalcProgress::finish($this->userId);
            }
            $user = \App\Models\User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title('Przeliczanie cen - wybrane szablony zakończone')
                    ->body("Szablony: {$totalTemplates}, Nowe rekordy: {$totalPricesCreated}, Razem rekordów po przeliczeniu: {$totalPricesAfter}, Błędów: {$errors}")
                    ->success()
                    ->sendToDatabase($user);

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
}

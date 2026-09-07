<?php

namespace App\Jobs;

use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Services\PriceRecalcProgress;
use App\Services\UnifiedPriceCalculator;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecalculateSelectedEventTemplatePricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** ~75 szablonów × kilka miejsc startowych — zapas względem typowego workera. */
    public int $timeout = 900;

    public array $templateIds;

    public int $userId;

    public bool $force = false;

    public bool $finalize = true;

    public bool $runFinalDedupe = false;

    public function __construct(
        array $templateIds,
        int $userId,
        bool $force = false,
        bool $finalize = true,
        bool $runFinalDedupe = false,
    ) {
        $this->templateIds = $templateIds;
        $this->userId = $userId;
        $this->force = $force;
        $this->finalize = $finalize;
        $this->runFinalDedupe = $runFinalDedupe;
    }

    public function handle(): void
    {
        set_time_limit(0);

        $calculator = new UnifiedPriceCalculator;
        $totalTemplates = 0;
        $totalPricesCreated = 0;
        $totalPricesAfter = 0;
        $errors = 0;

        foreach ($this->templateIds as $id) {
            $template = EventTemplate::withTrashed()->find($id);
            if (! $template) {
                if ($this->userId) {
                    PriceRecalcProgress::increment($this->userId, 1);
                }

                continue;
            }

            try {
                $before = EventTemplatePricePerPerson::where('event_template_id', $template->id)->count();
                $calculator->recalculateForTemplate($template, $this->force);
                $after = EventTemplatePricePerPerson::where('event_template_id', $template->id)->count();
                $totalTemplates++;
                $totalPricesCreated += max($after - $before, 0);
                $totalPricesAfter += $after;

                if ($this->userId) {
                    PriceRecalcProgress::increment($this->userId, 1);
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Recalculate selected job error for template #'.$template->id.': '.$e->getMessage());
                if ($this->userId) {
                    PriceRecalcProgress::addError($this->userId, 1);
                    PriceRecalcProgress::increment($this->userId, 1);
                }
            }
        }

        if (! $this->finalize) {
            return;
        }

        if ($this->runFinalDedupe) {
            $calculator->removeDuplicatePrices();
        }

        try {
            if ($this->userId) {
                PriceRecalcProgress::finish($this->userId);
            }

            $user = \App\Models\User::find($this->userId);
            if ($user) {
                $title = $this->runFinalDedupe
                    ? 'Przeliczanie cen zakończone'
                    : 'Przeliczanie cen - wybrane szablony zakończone';

                Notification::make()
                    ->title($title)
                    ->body("Szablony: {$totalTemplates}, Nowe rekordy: {$totalPricesCreated}, Razem rekordów po przeliczeniu: {$totalPricesAfter}, Błędów: {$errors}")
                    ->success()
                    ->sendToDatabase($user);

                if (! empty($user->email)) {
                    $summary = "Szablony: {$totalTemplates}\nNowe rekordy: {$totalPricesCreated}\nRazem rekordów po przeliczeniu: {$totalPricesAfter}\nBłędów: {$errors}";
                    Mail::raw('Przeliczanie cen zakończone.\n'.$summary, function ($m) use ($user) {
                        $m->to($user->email)->subject('Podsumowanie przeliczania cen');
                    });
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send completion notification: '.$e->getMessage());
        }
    }
}

}





















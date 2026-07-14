<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotifyTfgMonthlyReminderCommand extends Command
{
    protected $signature = 'tfg:notify-monthly-reminder';

    protected $description = 'Przypomnienie o niewysłanych umowach za poprzedni miesiąc (1–14 dzień miesiąca)';

    public function handle(): int
    {
        $day = (int) now()->format('j');
        if ($day > 14) {
            return self::SUCCESS;
        }

        $start = now()->subMonth()->startOfMonth();
        $end = now()->subMonth()->endOfMonth();

        $count = Contract::query()
            ->whereNull('tfg_status')
            ->whereBetween('contract_date', [$start, $end])
            ->count();

        if ($count === 0) {
            $this->info('Brak niewysłanych umów za poprzedni miesiąc.');

            return self::SUCCESS;
        }

        $message = "Do raportu TFG pozostało {$count} umów z poprzedniego miesiąca (termin: 14. dzień).";

        User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->each(function (User $user) use ($message) {
            Notification::make()
                ->title('Przypomnienie TFG')
                ->body($message)
                ->warning()
                ->sendToDatabase($user);
        });

        $this->info($message);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotifyTfgCorrectionDeadlineCommand extends Command
{
    protected $signature = 'tfg:notify-correction-deadlines';

    protected $description = 'Alert o umowach ze statusem Zawarta po terminie 14 dni na korektę';

    public function handle(): int
    {
        $contracts = Contract::query()
            ->where('tfg_status', 'Zawarta')
            ->whereNotNull('tfg_update_deadline_at')
            ->where('tfg_update_deadline_at', '<', now())
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('Brak umów z przekroczonym terminem korekty.');

            return self::SUCCESS;
        }

        $message = 'Przekroczono 14-dniowy termin korekty TFG dla '.$contracts->count().' umów.';

        User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->each(function (User $user) use ($message) {
            Notification::make()
                ->title('Alert TFG — termin korekty')
                ->body($message)
                ->danger()
                ->sendToDatabase($user);
        });

        $this->warn($message);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Database\Seeders\PilotDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class SetupPilotDemoCommand extends Command
{
    protected $signature = 'pilot:setup-demo
        {--migrate : Uruchom php artisan migrate przed konfiguracją}
        {--email='.PilotDemoSeeder::DEFAULT_EMAIL.' : E-mail konta pilota}
        {--password='.PilotDemoSeeder::DEFAULT_PASSWORD.' : Hasło konta pilota}
        {--name='.PilotDemoSeeder::DEFAULT_NAME.' : Imię i nazwisko pilota}';

    protected $description = 'Migracje (opcjonalnie) + konto testowe pilota, rola, przypisanie imprez i rozliczeń do testów portalu /pilot';

    public function handle(): int
    {
        if ($this->option('migrate')) {
            $this->components->info('Uruchamiam migracje…');
            Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());
        } else {
            $pending = $this->countPendingMigrations();

            if ($pending > 0) {
                $this->components->warn("Masz {$pending} oczekujących migracji. Uruchom: php artisan migrate");
                $this->components->warn('Albo: php artisan pilot:setup-demo --migrate');
            }
        }

        if (! Schema::hasTable('event_settlements')) {
            $this->components->error('Brak tabeli event_settlements — uruchom migracje (make migrate-check / php artisan migrate).');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('event_settlements', 'pilot_report_notes')) {
            $this->components->error('Brak kolumn raportu pilota — uruchom: php artisan migrate');
            $this->components->line('Szukana migracja: 2026_06_10_120000_add_pilot_report_fields_to_event_settlements_table');

            return self::FAILURE;
        }

        $result = (new PilotDemoSeeder)->runWithOptions(
            email: (string) $this->option('email'),
            password: (string) $this->option('password'),
            name: (string) $this->option('name'),
        );

        $pilot = $result['pilot'];
        $events = $result['events'];
        $credentials = $result['credentials'];

        $this->newLine();
        $this->components->info('Portal pilota — dane testowe gotowe');
        $this->table(
            ['Pole', 'Wartość'],
            [
                ['URL logowania', rtrim(config('app.url'), '/').'/pilot/login'],
                ['E-mail', $credentials['email']],
                ['Hasło', $credentials['password']],
                ['ID użytkownika', (string) $pilot->id],
                ['Rola', $pilot->hasRole('pilot') ? 'pilot' : 'BRAK'],
            ],
        );

        if ($events->isNotEmpty()) {
            $this->components->info('Przypisane imprezy (assigned_to = pilot):');
            $this->table(
                ['ID', 'Nazwa', 'Start', 'Status'],
                $events->map(fn ($event) => [
                    $event->id,
                    $event->name,
                    $event->start_date?->format('d.m.Y') ?? '—',
                    $event->status,
                ])->all(),
            );

            $first = $events->first();
            $this->line('  Rozliczenie desktop: '.\App\Filament\Pilot\Pages\PilotSettlementPage::settleUrl($first));
            $this->line('  Rozliczenie mobilne:  '.route('pilot.trip.settle', $first));
        } else {
            $this->components->warn('Nie przypisano imprez — dodaj assigned_to ręcznie w panelu admina.');
        }

        $this->newLine();
        $this->line('Legacy pilot (seeder): piotr.zielinski@example.com / zielony2024');
        $this->line('Testy automatyczne: php artisan test --filter=PilotPortalTest');

        return self::SUCCESS;
    }

    protected function countPendingMigrations(): int
    {
        $migrator = app('migrator');
        $migrator->setConnection(config('database.default'));

        if (! $migrator->repositoryExists()) {
            return 0;
        }

        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $migrator->getRepository()->getRan();

        return count(array_diff(array_keys($files), $ran));
    }
}

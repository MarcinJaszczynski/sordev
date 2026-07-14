<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class InstallUfgModuleCommand extends Command
{
    protected $signature = 'ufg:install {--dry-run : Podgląd migracji umów bez zapisu}';

    protected $description = 'Instaluje moduł UFG (tabele + słowniki + migracja event_agreements) na istniejącej bazie';

    public function handle(): int
    {
        $this->info('Baza: '.config('database.connections.mysql.database')
            .' @ '.config('database.connections.mysql.host')
            .':'.config('database.connections.mysql.port'));

        if (! Schema::hasTable('event_agreements')) {
            $this->error('Brak tabeli event_agreements — najpierw uruchom pełną bazę SOR.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('contracts')) {
            $this->info('Tworzenie tabel UFG…');
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_06_01_100000_create_tfg_contracts_tables.php',
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
        } else {
            $this->comment('Tabele UFG już istnieją — pomijam migrację.');
        }

        $this->info('Słowniki TFG…');
        Artisan::call('db:seed', ['--class' => 'TfgDictionarySeeder', '--force' => true]);
        $this->output->write(Artisan::output());

        $this->info('Migracja event_agreements → contracts…');
        $migrateArgs = [];
        if ($this->option('dry-run')) {
            $migrateArgs['--dry-run'] = true;
        }
        Artisan::call('contracts:migrate-from-event-agreements', $migrateArgs);
        $this->output->write(Artisan::output());

        $this->newLine();
        $this->info('Moduł UFG gotowy. Nie uruchamiaj `php artisan migrate` na starej bazie — użyj `php artisan ufg:install`.');

        return self::SUCCESS;
    }
}

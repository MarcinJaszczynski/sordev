<?php

namespace App\Console\Commands;

use App\Support\BackupArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreApplication extends Command
{
    protected $signature = 'app:restore
        {path : Ścieżka do pliku ZIP z kopią zapasową}
        {--components= : Komponenty: db, storage, code, env (domyślnie z manifestu)}
        {--force : Pomiń potwierdzenie}';

    protected $description = 'Przywróć aplikację z kopii zapasowej (archiwum ZIP)';

    public function handle(): int
    {
        set_time_limit(600);

        $zipPath = $this->argument('path');

        if (! file_exists($zipPath)) {
            $this->error("Plik nie istnieje: {$zipPath}");

            return self::FAILURE;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->error('Nie udało się otworzyć pliku ZIP.');

            return self::FAILURE;
        }

        $manifestJson = $zip->getFromName('manifest.json');
        if ($manifestJson === false) {
            $zip->close();
            $this->error('Archiwum nie zawiera manifest.json – to nie jest poprawna kopia zapasowa.');

            return self::FAILURE;
        }

        $manifest = json_decode($manifestJson, true);
        if (! is_array($manifest)) {
            $zip->close();
            $this->error('manifest.json jest uszkodzony.');

            return self::FAILURE;
        }

        $availableComponents = $manifest['components'] ?? [];
        $components = $this->option('components')
            ? BackupArchive::parseComponents($this->option('components'), $availableComponents)
            : $availableComponents;

        if (empty($components)) {
            $zip->close();
            $this->error('Brak komponentów do przywrócenia.');

            return self::FAILURE;
        }

        $this->info('Kopia zapasowa z: '.($manifest['created_at'] ?? '?'));
        $this->info('Komponenty do przywrócenia: '.implode(', ', $components));

        if (! $this->option('force') && ! $this->confirm('Czy na pewno chcesz przywrócić? Operacja nadpisze istniejące dane.')) {
            $this->info('Anulowano.');

            return self::SUCCESS;
        }

        $tmpDir = sys_get_temp_dir().'/app_restore_'.uniqid();
        mkdir($tmpDir, 0755, true);

        $zip->extractTo($tmpDir);
        $zip->close();

        $errors = [];

        if (in_array(BackupArchive::COMPONENT_DB, $components)) {
            $this->info('  → Tworzenie pre-backupu bieżącej bazy...');
            Artisan::call('app:backup', ['--components' => BackupArchive::COMPONENT_DB]);
            $this->info('    '.trim(Artisan::output()));

            $this->info('  → Przywracanie bazy danych...');
            $dbResult = $this->restoreDatabase($tmpDir, $manifest);
            if ($dbResult !== true) {
                $errors[] = $dbResult;
            }
        }

        if (in_array(BackupArchive::COMPONENT_CODE, $components)) {
            $this->info('  → Przywracanie kodu aplikacji...');
            $codeResult = $this->restoreCode($tmpDir);
            if ($codeResult !== true) {
                $errors[] = $codeResult;
            }
        }

        if (in_array(BackupArchive::COMPONENT_STORAGE, $components)) {
            $this->info('  → Przywracanie storage...');
            $storageResult = $this->restoreStorage($tmpDir, $manifest);
            if ($storageResult !== true) {
                $errors[] = $storageResult;
            }
        }

        if (in_array(BackupArchive::COMPONENT_ENV, $components)) {
            $this->info('  → Przywracanie .env...');
            $envResult = $this->restoreEnv($tmpDir);
            if ($envResult !== true) {
                $errors[] = $envResult;
            }
        }

        BackupArchive::deleteDirectory($tmpDir);

        $this->info('  → Czyszczenie cache...');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        if (! empty($errors)) {
            foreach ($errors as $err) {
                $this->warn('  ⚠ '.$err);
            }
            $this->warn('Przywracanie zakończone z ostrzeżeniami.');

            return self::FAILURE;
        }

        $this->info('Przywracanie zakończone pomyślnie.');
        $this->warn('Po przywróceniu kodu uruchom na serwerze: composer install --no-dev && npm ci && npm run build');

        return self::SUCCESS;
    }

    private function restoreDatabase(string $tmpDir, array $manifest): true|string
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            return $this->restoreSqlite($tmpDir, $manifest);
        }

        return $this->restoreMysql($tmpDir);
    }

    private function restoreSqlite(string $tmpDir, array $manifest): true|string
    {
        $dbFile = $manifest['db_file'] ?? 'database.sqlite';
        $sourcePath = $tmpDir.'/'.$dbFile;

        if (! file_exists($sourcePath)) {
            return 'Plik bazy SQLite nie znaleziony w archiwum.';
        }

        $targetPath = config('database.connections.sqlite.database');
        if (! $targetPath) {
            return 'Brak ścieżki docelowej bazy SQLite w konfiguracji.';
        }

        $dir = dirname($targetPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($sourcePath, $targetPath);

        return true;
    }

    private function restoreMysql(string $tmpDir): true|string
    {
        $sqlPath = $tmpDir.'/database.sql';
        if (! file_exists($sqlPath)) {
            return 'Plik database.sql nie znaleziony w archiwum.';
        }

        $mysql = $this->findBinary('mysql');
        if (! $mysql) {
            return 'Nie znaleziono programu mysql. Zainstaluj pakiet mysql-client.';
        }

        $connection = Config::get('database.connections.mysql');
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';
        $database = $connection['database'] ?? '';
        $username = $connection['username'] ?? '';
        $password = $connection['password'] ?? '';

        $cmd = [
            $mysql,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            $database,
        ];

        $env = [];
        if (filled($password)) {
            $env['MYSQL_PWD'] = $password;
        }

        $process = new Process($cmd, null, $env);
        $process->setTimeout(300);
        $process->setInput(file_get_contents($sqlPath));
        $process->run();

        if (! $process->isSuccessful()) {
            return 'mysql import error: '.$process->getErrorOutput();
        }

        return true;
    }

    private function restoreCode(string $tmpDir): true|string
    {
        $sourcePath = $tmpDir.'/code';
        if (! is_dir($sourcePath)) {
            return 'Katalog code/ nie znaleziony w archiwum.';
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getRealPath(), strlen($sourcePath) + 1);
            $target = base_path($relative);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $parent = dirname($target);
                if (! is_dir($parent)) {
                    mkdir($parent, 0755, true);
                }
                copy($item->getRealPath(), $target);
            }
        }

        return true;
    }

    private function restoreStorage(string $tmpDir, array $manifest): true|string
    {
        $sourcePath = $tmpDir.'/storage';
        if (! is_dir($sourcePath)) {
            return 'Katalog storage/ nie znaleziony w archiwum.';
        }

        if (($manifest['storage_scope'] ?? null) === 'full') {
            BackupArchive::restoreTree($sourcePath, storage_path(), false);

            return true;
        }

        // Starsze kopie: pliki z storage/app/public pod ścieżką storage/…
        $publicRoot = storage_path('app/public');
        if (! is_dir($publicRoot)) {
            mkdir($publicRoot, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getRealPath(), strlen($sourcePath) + 1);

            if (str_starts_with(str_replace('\\', '/', $relative), 'app/')) {
                $targetRelative = $relative;
            } else {
                $targetRelative = 'app/public/'.$relative;
            }

            $target = storage_path($targetRelative);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $parent = dirname($target);
                if (! is_dir($parent)) {
                    mkdir($parent, 0755, true);
                }
                copy($item->getRealPath(), $target);
            }
        }

        return true;
    }

    private function restoreEnv(string $tmpDir): true|string
    {
        $sourcePath = $tmpDir.'/dotenv';
        if (! file_exists($sourcePath)) {
            return 'Plik dotenv nie znaleziony w archiwum.';
        }

        copy($sourcePath, base_path('.env'));

        return true;
    }

    private function findBinary(string $name): ?string
    {
        $which = trim((string) shell_exec('which '.escapeshellarg($name).' 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }

        $commonPaths = ['/usr/bin/'.$name, '/usr/local/bin/'.$name, '/usr/local/mysql/bin/'.$name];
        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Installer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use PDO;
use Spatie\Permission\Models\Role;

class InstallController extends Controller
{
    public function index(): View
    {
        return view('install.index', [
            'step' => 1,
            'requirements' => Installer::requirements(),
            'requirementsMet' => Installer::requirementsMet(),
        ]);
    }

    public function databaseForm(): View|RedirectResponse
    {
        if (! Installer::requirementsMet()) {
            return redirect()->route('install.index');
        }

        return view('install.index', [
            'step' => 2,
            'db' => session('install.db', [
                'connection' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => '',
                'username' => '',
                'password' => '',
            ]),
        ]);
    }

    public function databaseStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'string', 'max:10'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $this->testDatabaseConnection(
            $validated['db_host'],
            $validated['db_port'],
            $validated['db_database'],
            $validated['db_username'],
            $validated['db_password'] ?? '',
        );

        session(['install.db' => [
            'host' => $validated['db_host'],
            'port' => $validated['db_port'],
            'database' => $validated['db_database'],
            'username' => $validated['db_username'],
            'password' => $validated['db_password'] ?? '',
        ]]);

        return redirect()->route('install.application');
    }

    public function applicationForm(): View|RedirectResponse
    {
        if (! session()->has('install.db')) {
            return redirect()->route('install.database');
        }

        return view('install.index', [
            'step' => 3,
            'app' => session('install.app', [
                'name' => config('app.name', 'SOR'),
                'url' => url('/'),
            ]),
        ]);
    }

    public function applicationStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
        ]);

        session(['install.app' => [
            'name' => $validated['app_name'],
            'url' => rtrim($validated['app_url'], '/'),
        ]]);

        return redirect()->route('install.admin');
    }

    public function adminForm(): View|RedirectResponse
    {
        if (! session()->has('install.app')) {
            return redirect()->route('install.application');
        }

        return view('install.index', [
            'step' => 4,
        ]);
    }

    public function adminStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $db = session('install.db');
        $app = session('install.app');

        if (! $db || ! $app) {
            return redirect()->route('install.index');
        }

        Installer::writeEnv([
            'APP_NAME' => $app['name'],
            'APP_URL' => $app['url'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
        ]);

        $this->reloadEnvironment();

        Artisan::call('migrate', ['--force' => true]);

        foreach (['super_admin', 'admin', 'user', 'pilot', 'biuro', 'ksiegowosc', 'wlasciciel'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $user = User::query()->create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'status' => 'active',
            'type' => 'admin',
        ]);

        $user->assignRole('super_admin');
        $user->assignRole('admin');

        Installer::markInstalled();

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('storage:link');

        session()->forget(['install.db', 'install.app']);

        return redirect()->route('install.complete');
    }

    public function complete(): View
    {
        return view('install.complete');
    }

    private function testDatabaseConnection(
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
    ): void {
        try {
            new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'db_database' => 'Nie udało się połączyć z bazą: '.$e->getMessage(),
            ]);
        }
    }

    private function reloadEnvironment(): void
    {
        Artisan::call('config:clear');

        if (file_exists(base_path('.env'))) {
            $dotenv = \Dotenv\Dotenv::createMutable(base_path());
            $dotenv->load();
        }

        $db = session('install.db');
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'],
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }
}

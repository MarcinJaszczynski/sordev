<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\PilotDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ConsoleAuditBootstrapCommand extends Command
{
    public const ADMIN_EMAIL = 'console-audit@local';

    public const ADMIN_PASSWORD = 'console-audit';

    protected $signature = 'app:console-audit-bootstrap {--skip-pilot : Nie uruchamiaj pilot:setup-demo}';

    protected $description = 'Przygotuj konta testowe do audytu konsoli (admin + pilot)';

    public function handle(): int
    {
        $admin = User::updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Console Audit',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'status' => 'active',
            ],
        );

        $superAdmin = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole($superAdmin);
        }
        if (! $admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $this->info('Konto audytu admin: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);

        if (! $this->option('skip-pilot')) {
            $this->call('pilot:setup-demo');
            $this->info('Konto pilota: '.PilotDemoSeeder::DEFAULT_EMAIL.' / '.PilotDemoSeeder::DEFAULT_PASSWORD);
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\CurrencyRateSnapshot;
use App\Models\Media;
use App\Models\TfgFeedLog;
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

        $this->ensureAuditSampleRecords($admin);

        if (! $this->option('skip-pilot')) {
            $this->call('pilot:setup-demo');
            $this->info('Konto pilota: '.PilotDemoSeeder::DEFAULT_EMAIL.' / '.PilotDemoSeeder::DEFAULT_PASSWORD);
        }

        return self::SUCCESS;
    }

    protected function ensureAuditSampleRecords(User $admin): void
    {
        $currencyId = Currency::query()->orderBy('id')->value('id');

        if ($currencyId && CurrencyRateSnapshot::query()->doesntExist()) {
            CurrencyRateSnapshot::query()->create([
                'currency_id' => $currencyId,
                'rate' => 1,
                'purchase_rate' => 1,
                'sale_rate' => 1,
                'source' => 'console-audit',
                'rate_date' => now()->toDateString(),
                'created_by' => $admin->id,
            ]);
        }

        if (Media::query()->doesntExist()) {
            Media::query()->create([
                'disk' => 'public',
                'path' => 'console-audit/sample.txt',
                'filename' => 'sample.txt',
                'extension' => 'txt',
                'mime' => 'text/plain',
                'size' => 1,
                'title' => 'Console audit sample',
            ]);
        }

        if (TfgFeedLog::query()->doesntExist()) {
            TfgFeedLog::query()->create([
                'feed_identifier' => 'console-audit',
                'operation_type' => 'export',
                'contracts_count' => 0,
                'sync_status' => 'completed',
            ]);
        }
    }
}

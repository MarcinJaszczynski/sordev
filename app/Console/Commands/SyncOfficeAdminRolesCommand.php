<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\OfficeRolePermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncOfficeAdminRolesCommand extends Command
{
    protected $signature = 'app:sync-office-admin-roles {--dry-run : Tylko pokaż plan bez zapisu}';

    protected $description = 'Ustawia konta właścicielskie (super_admin) i biuro; synchronizuje uprawnienia roli biuro';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'biuro'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $biuro = Role::findByName('biuro');
        $permissionNames = OfficeRolePermissions::names();
        foreach ($permissionNames as $name) {
            Permission::findOrCreate($name);
        }

        if (! $dry) {
            $biuro->syncPermissions(
                Permission::query()->whereIn('name', $permissionNames)->get()
            );
            $this->info('Rola biuro: '.count($permissionNames).' uprawnień operacyjnych.');
        } else {
            $this->line('[dry-run] biuro ← '.count($permissionNames).' permissions');
        }

        foreach (config('executive.owner_emails', []) as $email) {
            $this->ensureOwnerAccount((string) $email, $dry);
        }

        foreach (config('executive.office_emails', []) as $email) {
            $this->ensureOfficeAccount((string) $email, $dry);
        }

        $this->demoteUnexpectedAdmins($dry);

        $marcin = User::query()->where('email', 'm.jaszczynski@gmail.com')->first();
        if ($marcin && $marcin->name !== 'Marcin Jaszczyński') {
            $this->line(($dry ? '[dry-run] ' : '')."Rename {$marcin->name} → Marcin Jaszczyński");
            if (! $dry) {
                $marcin->forceFill(['name' => 'Marcin Jaszczyński'])->save();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info($dry ? 'Dry-run zakończony.' : 'Synchronizacja ról zakończona.');

        return self::SUCCESS;
    }

    private function ensureOwnerAccount(string $email, bool $dry): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user && $email === 'system@bprafa.pl') {
            $this->line(($dry ? '[dry-run] ' : '')."Create system user {$email}");
            if (! $dry) {
                $user = User::query()->create([
                    'name' => 'System',
                    'email' => $email,
                    'password' => Hash::make(Str::password(32)),
                    'email_verified_at' => now(),
                ]);
            }
        }

        if (! $user) {
            $this->warn("Brak konta właścicielskiego: {$email}");

            return;
        }

        $roles = ['super_admin', 'admin'];
        // Zachowaj pilot / client jeśli już są (podglądy portali).
        foreach (['pilot', 'client_participant', 'client_guardian'] as $extra) {
            if ($user->hasRole($extra)) {
                $roles[] = $extra;
            }
        }

        $this->line(($dry ? '[dry-run] ' : '')."Owner {$email} ← ".implode(',', $roles));
        if (! $dry) {
            $user->syncRoles($roles);
        }
    }

    private function ensureOfficeAccount(string $email, bool $dry): void
    {
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->warn("Brak konta biura: {$email}");

            return;
        }

        $this->line(($dry ? '[dry-run] ' : '')."Office {$email} ← biuro (bez admin/super_admin)");
        if (! $dry) {
            $user->syncRoles(['biuro']);
        }
    }

    private function demoteUnexpectedAdmins(bool $dry): void
    {
        $allowed = collect(config('executive.owner_emails', []))
            ->map(fn (string $email): string => strtolower($email))
            ->all();

        $users = User::role(['admin', 'super_admin'])->get();
        foreach ($users as $user) {
            if (in_array(strtolower((string) $user->email), $allowed, true)) {
                continue;
            }

            $this->line(($dry ? '[dry-run] ' : '')."Demote unexpected admin {$user->email} → bez admin/super_admin");
            if ($dry) {
                continue;
            }

            $keep = $user->roles
                ->pluck('name')
                ->reject(fn (string $role): bool => in_array($role, ['admin', 'super_admin'], true))
                ->values()
                ->all();

            if ($keep === []) {
                $keep = ['biuro'];
            }

            $user->syncRoles($keep);
        }
    }
}

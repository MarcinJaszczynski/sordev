<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'm.jaszczynski@gmail.com'],
            [
                'name' => 'Marcin Jaszczyński',
                'password' => Hash::make('1234'),
                'email_verified_at' => now(),
            ]
        );

        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'admin']);
        $user->syncRoles(['super_admin', 'admin']);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            [ 'email' => 'm.jaszczynski@gmail.com' ],
            [
                'name' => 'Michał Jaszczynski',
                'password' => Hash::make('1234'),
                'email_verified_at' => now(),
            ]
        );

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($role);
    }
}

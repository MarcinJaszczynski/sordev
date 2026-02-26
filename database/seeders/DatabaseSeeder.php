<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CurrencySeeder::class,
            TagSeeder::class,
            TaskStatusSeeder::class,
            TaskSeeder::class,
            BusSeeder::class,
            PayerSeeder::class,
            EventTemplateProgramPointSeeder::class,
            EventTemplateSeeder::class,
            EventTemplateQtySeeder::class,
            KategoriaSzablonuSeeder::class,
            HotelRoomSeeder::class,
            ContractorTypesSeeder::class,
            ContractorsSeeder::class,
            ContractorContractorTypeSeeder::class,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'pilot']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'biuro']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'ksiegowosc']);

        // Tworzenie uprawnień dla wszystkich modeli (w tym role i permission)
        $models = [
            'user', 'contractor', 'contact', 'event_template', 'event_template_qty', 'kategoria_szablonu', 'tag', 'task', 'todo_status', 'currency', 'role', 'permission', 'transport_cost', 'markup'
        ];
        $actions = ['view', 'create', 'edit', 'delete'];
        foreach ($models as $model) {
            foreach ($actions as $action) {
                \Spatie\Permission\Models\Permission::firstOrCreate([
                    'name' => $action . ' ' . $model
                ]);
            }
        }

        // Automatyczne przypisywanie ról i uprawnień
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        $adminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());
        $userRole = \Spatie\Permission\Models\Role::where('name', 'user')->first();
        $userRole->syncPermissions([
            'view user', 'edit user', 'view task', 'edit task', 'view event_template', 'view event_template_qty', 'view kategoria_szablonu', 'view tag', 'view contractor', 'view contact', 'view todo_status', 'view currency', 'view transport_cost',
            'view markup'
        ]);
        $pilotRole = \Spatie\Permission\Models\Role::where('name', 'pilot')->first();
        $pilotRole->syncPermissions(['view task', 'view event_template', 'view markup']);
        $biuroRole = \Spatie\Permission\Models\Role::where('name', 'biuro')->first();
        $biuroRole->syncPermissions(['view user', 'edit user', 'view contractor', 'edit contractor', 'view event_template', 'edit event_template', 'view transport_cost', 'edit transport_cost', 'create transport_cost', 'delete transport_cost', 'view markup', 'edit markup', 'create markup', 'delete markup']);
        $ksiegowoscRole = \Spatie\Permission\Models\Role::where('name', 'ksiegowosc')->first();
        $ksiegowoscRole->syncPermissions(['view user', 'view contractor', 'view event_template', 'view currency', 'view transport_cost', 'edit transport_cost', 'view markup']);

        // Przypisz wszystkie uprawnienia do roli admin (na końcu seedera)
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        $adminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());

        // Dodatkowe uprawnienie dla wpisów blogowych
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create blog post']);
        $adminRole->givePermissionTo('create blog post');

        // Stwórz testowego admina jeśli nie istnieje
        $adminUser = \App\Models\User::where('email', 'admin@example.com')->first();
        if (! $adminUser) {
            $adminUser = \App\Models\User::create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
        }
        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }
    }
}

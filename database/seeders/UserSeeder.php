<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pobierz role z bazy danych
        $adminRole = Role::where('name', 'admin')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();

        if (!$adminRole) {
            $this->command->error('Rola "admin" nie została znaleziona. Uruchom najpierw RolePermissionSeeder.');
            return;
        }

        User::updateOrCreate(
            ['email' => 'waldemar.grabowski@hostnet.pl'], // Warunek unikający duplikacji
            [
                'name' => 'Waldemar',
                'email' => 'waldemar.grabowski@hostnet.pl',
                'password' => Hash::make('noYkeT#70'),
                'role_id' => $adminRole->id, // Użyj ID roli zamiast stringa
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'luman0599@gmail.com'],
            [
                'name' => 'Łukasz',
                'email' => 'luman0599@gmail.com',
                'password' => Hash::make('Feniks99'),
                'role_id' => $superAdminRole ? $superAdminRole->id : $adminRole->id, // Super admin lub admin
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}

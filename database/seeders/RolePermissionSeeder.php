<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Utwórz uprawnienia
        $permissions = [
            // Użytkownicy
            ['name' => 'users.view', 'display_name' => 'Przeglądanie użytkowników', 'category' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Tworzenie użytkowników', 'category' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'Edycja użytkowników', 'category' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Usuwanie użytkowników', 'category' => 'users'],
            
            // Szkolenia
            ['name' => 'courses.view', 'display_name' => 'Przeglądanie szkoleń', 'category' => 'courses'],
            ['name' => 'courses.create', 'display_name' => 'Tworzenie szkoleń', 'category' => 'courses'],
            ['name' => 'courses.edit', 'display_name' => 'Edycja szkoleń', 'category' => 'courses'],
            ['name' => 'courses.delete', 'display_name' => 'Usuwanie szkoleń', 'category' => 'courses'],
            
            // Zamówienia
            ['name' => 'orders.view', 'display_name' => 'Przeglądanie zamówień', 'category' => 'orders'],
            ['name' => 'orders.edit', 'display_name' => 'Edycja zamówień', 'category' => 'orders'],
            ['name' => 'orders.delete', 'display_name' => 'Usuwanie zamówień', 'category' => 'orders'],
            
            // Raporty
            ['name' => 'reports.view', 'display_name' => 'Przeglądanie raportów', 'category' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Eksport raportów', 'category' => 'reports'],
            
            // System
            ['name' => 'system.settings', 'display_name' => 'Ustawienia systemu', 'category' => 'system'],
            ['name' => 'system.logs', 'display_name' => 'Logi systemu', 'category' => 'system'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Utwórz role
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Pełny dostęp do systemu',
                'is_system' => true,
                'level' => 4,
                'permissions' => ['*'] // Wszystkie uprawnienia
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Zarządzanie użytkownikami, szkoleniami i zamówieniami',
                'is_system' => true,
                'level' => 3,
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'courses.view', 'courses.create', 'courses.edit', 'courses.delete',
                    'orders.view', 'orders.edit', 'orders.delete',
                    'reports.view', 'reports.export'
                ]
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Zarządzanie szkoleniami i zamówieniami',
                'is_system' => true,
                'level' => 2,
                'permissions' => [
                    'courses.view', 'courses.create', 'courses.edit',
                    'orders.view', 'orders.edit',
                    'reports.view'
                ]
            ],
            [
                'name' => 'user',
                'display_name' => 'Użytkownik',
                'description' => 'Podstawowy dostęp do systemu',
                'is_system' => true,
                'level' => 1,
                'permissions' => [
                    'courses.view',
                    'orders.view'
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            // Przypisz uprawnienia do roli
            if ($permissions[0] === '*') {
                // Super Admin - wszystkie uprawnienia
                $role->permissions()->sync(Permission::all());
            } else {
                $role->permissions()->sync(
                    Permission::whereIn('name', $permissions)->pluck('id')
                );
            }
        }
    }
}

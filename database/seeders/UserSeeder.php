<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'waldemar.grabowski@hostnet.pl'], // Warunek unikający duplikacji
            [
                'name' => 'Waldemar',
                'email' => 'waldemar.grabowski@hostnet.pl',
                'password' => Hash::make('noYkeT#70'),
                'role' => 'admin', // Nadanie roli admina                
            ]
        );

        User::updateOrCreate(
            ['email' => 'luman0599@gmail.com'],
            [
                'name' => 'Łukasz',
                'email' => 'luman0599@gmail.com',
                'password' => Hash::make('Feniks99'),
                'role' => 'admin', // Nadanie roli admina
            ]
        );
    }
}

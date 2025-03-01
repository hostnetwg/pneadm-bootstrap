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
            UserSeeder::class, // Dodajemy nasz nowy seeder
            InstructorSeeder::class, // Dodajemy nasz nowy seeder
            UserSeeder::class,
            CourseSeeder::class,    
            ParticipantSeeder::class, // Nowy seeder uczestników
        ]);
    }
}

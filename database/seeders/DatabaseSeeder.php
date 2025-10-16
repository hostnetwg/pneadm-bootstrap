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
            RolePermissionSeeder::class, // Najpierw role i uprawnienia
            UserSeeder::class, // Potem użytkownicy
            InstructorSeeder::class, // Instruktorzy
            CertificateTemplateSeeder::class, // Szablony zaświadczeń
            // CourseSeeder::class,    
            // ParticipantSeeder::class, // Nowy seeder uczestników
        ]);
    }
}

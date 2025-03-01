<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $courseId = 1; // ID pierwszego kursu

        // Lista uczestników do dodania
        $participants = [
            [
                'first_name' => 'Anna',
                'last_name' => 'Kowalska',
                'email' => 'anna.kowalska@example.com',
                'birth_date' => '1990-05-15',
                'birth_place' => 'Warszawa',
            ],
            [
                'first_name' => 'Jan',
                'last_name' => 'Nowak',
                'email' => 'jan.nowak@example.com',
                'birth_date' => '1985-08-22',
                'birth_place' => 'Kraków',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Wiśniewska',
                'email' => 'maria.wisniewska@example.com',
                'birth_date' => '1992-12-10',
                'birth_place' => 'Gdańsk',
            ],
        ];

        // Dodajemy uczestników do bazy
        foreach ($participants as $participant) {
            DB::table('participants')->insert([
                'course_id' => $courseId,
                'first_name' => $participant['first_name'],
                'last_name' => $participant['last_name'],
                'email' => $participant['email'],
                'birth_date' => $participant['birth_date'],
                'birth_place' => $participant['birth_place'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

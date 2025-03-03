<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $participants = [
            // Uczestnicy kursu ID = 1
            [
                'course_id' => 1,
                'first_name' => 'Piotr',
                'last_name' => 'Mazur',
                'email' => 'piotr.mazur@example.com',
                'birth_date' => '1987-06-14',
                'birth_place' => 'Poznań',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Agnieszka',
                'last_name' => 'Krawczyk',
                'email' => 'agnieszka.krawczyk@example.com',
                'birth_date' => '1991-09-23',
                'birth_place' => 'Łódź',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Tomasz',
                'last_name' => 'Dąbrowski',
                'email' => 'tomasz.dabrowski@example.com',
                'birth_date' => '1985-12-01',
                'birth_place' => 'Gdynia',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Ewa',
                'last_name' => 'Zielińska',
                'email' => 'ewa.zielinska@example.com',
                'birth_date' => '1993-03-19',
                'birth_place' => 'Kraków',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Krzysztof',
                'last_name' => 'Nowicki',
                'email' => 'krzysztof.nowicki@example.com',
                'birth_date' => '1982-05-22',
                'birth_place' => 'Gdańsk',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Anna',
                'last_name' => 'Kowalczyk',
                'email' => 'anna.kowalczyk@example.com',
                'birth_date' => '1995-08-30',
                'birth_place' => 'Katowice',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Mateusz',
                'last_name' => 'Lis',
                'email' => 'mateusz.lis@example.com',
                'birth_date' => '1989-04-12',
                'birth_place' => 'Lublin',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Karolina',
                'last_name' => 'Wójcik',
                'email' => 'karolina.wojcik@example.com',
                'birth_date' => '1998-06-25',
                'birth_place' => 'Bydgoszcz',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Michał',
                'last_name' => 'Szymański',
                'email' => 'michal.szymanski@example.com',
                'birth_date' => '1994-02-17',
                'birth_place' => 'Szczecin',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Barbara',
                'last_name' => 'Górska',
                'email' => 'barbara.gorska@example.com',
                'birth_date' => '1980-07-11',
                'birth_place' => 'Opole',
            ],
            [
                'course_id' => 1,
                'first_name' => 'Łukasz',
                'last_name' => 'Czerwiński',
                'email' => 'lukasz.czerwinski@example.com',
                'birth_date' => '1992-09-03',
                'birth_place' => 'Rzeszów',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Marcin',
                'last_name' => 'Kamiński',
                'email' => 'marcin.kaminski@example.com',
                'birth_date' => '1988-10-05',
                'birth_place' => 'Gdynia',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Magdalena',
                'last_name' => 'Lewandowska',
                'email' => 'magdalena.lewandowska@example.com',
                'birth_date' => '1991-07-15',
                'birth_place' => 'Warszawa',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Paweł',
                'last_name' => 'Zając',
                'email' => 'pawel.zajac@example.com',
                'birth_date' => '1990-06-20',
                'birth_place' => 'Łódź',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Katarzyna',
                'last_name' => 'Górska',
                'email' => 'katarzyna.gorska@example.com',
                'birth_date' => '1986-09-10',
                'birth_place' => 'Wrocław',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Jakub',
                'last_name' => 'Lis',
                'email' => 'jakub.lis@example.com',
                'birth_date' => '1993-11-03',
                'birth_place' => 'Kraków',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Dominika',
                'last_name' => 'Czerwińska',
                'email' => 'dominika.czerwinska@example.com',
                'birth_date' => '1995-02-28',
                'birth_place' => 'Szczecin',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Grzegorz',
                'last_name' => 'Nowak',
                'email' => 'grzegorz.nowak@example.com',
                'birth_date' => '1987-04-14',
                'birth_place' => 'Poznań',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Sylwia',
                'last_name' => 'Mazur',
                'email' => 'sylwia.mazur@example.com',
                'birth_date' => '1992-08-19',
                'birth_place' => 'Katowice',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Andrzej',
                'last_name' => 'Kowalczyk',
                'email' => 'andrzej.kowalczyk@example.com',
                'birth_date' => '1989-12-22',
                'birth_place' => 'Lublin',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Ewelina',
                'last_name' => 'Szymańska',
                'email' => 'ewelina.szymanska@example.com',
                'birth_date' => '1994-05-17',
                'birth_place' => 'Bydgoszcz',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Dawid',
                'last_name' => 'Wójcik',
                'email' => 'dawid.wojcik@example.com',
                'birth_date' => '1991-03-09',
                'birth_place' => 'Opole',
            ],
            [
                'course_id' => 2,
                'first_name' => 'Natalia',
                'last_name' => 'Dąbrowska',
                'email' => 'natalia.dabrowska@example.com',
                'birth_date' => '1997-06-23',
                'birth_place' => 'Rzeszów',
            ],            
        ];

        $groupedParticipants = collect($participants)->groupBy('course_id');

        foreach ($groupedParticipants as $courseId => $participants) {
            foreach ($participants as $index => $participant) {
                DB::table('participants')->insert([
                    'course_id' => $participant['course_id'],
                    'first_name' => $participant['first_name'],
                    'last_name' => $participant['last_name'],
                    'email' => $participant['email'],
                    'birth_date' => $participant['birth_date'],
                    'birth_place' => $participant['birth_place'],
                    'order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
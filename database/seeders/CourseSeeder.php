<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // Tworzymy kurs offline
        $offlineCourseId = DB::table('courses')->insertGetId([
            'title' => 'Szkolenie stacjonarne z AI',
            'description' => 'Zaawansowane szkolenie dotyczące wykorzystania AI w edukacji.',
            'start_date' => Carbon::now()->subDays(7)->setHour(17)->setMinute(0)->setSecond(0), // 7 dni temu, godz. 17:00
            'end_date' => Carbon::now()->subDays(7)->setHour(20)->setMinute(0)->setSecond(0), // 7 dni temu, godz. 20:00
            'type' => 'offline',
            'category' => 'open',
            'instructor_id' => 1, // Można dodać ID instruktora, jeśli istnieje
            'image' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tworzymy lokalizację dla kursu offline
        DB::table('course_locations')->insert([
            'course_id' => $offlineCourseId,
            'location_name' => 'Szkoła Podstawowa nr 1',
            'address' => 'ul. Szkolna 10',            
            'postal_code' => '00-001',
            'post_office' => 'Warszawa',
            'country' => 'Polska',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tworzymy kurs online
        $onlineCourseId = DB::table('courses')->insertGetId([
            'title' => 'Szkolenie online z Canvy',
            'description' => 'Podstawy projektowania w Canva dla nauczycieli.',
            'start_date' => Carbon::now()->subDays(3)->setHour(16)->setMinute(0)->setSecond(0), // 3 dni temu, godz. 16:00
            'end_date' => Carbon::now()->subDays(3)->setHour(19)->setMinute(0)->setSecond(0), // 3 dni temu, godz. 19:00
            'type' => 'online',
            'category' => 'closed',
            'instructor_id' => 1,
            'image' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tworzymy szczegóły kursu online
        DB::table('course_online_details')->insert([
            'course_id' => $onlineCourseId,
            'platform' => 'Zoom',
            'meeting_link' => 'https://zoom.us/j/123456789',
            'meeting_password' => 'haslo123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

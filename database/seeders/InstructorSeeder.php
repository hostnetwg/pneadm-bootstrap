<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Instructor;

class InstructorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Upewniamy się, że instruktorzy nie są duplikowani
        Instructor::updateOrCreate(
            ['email' => 'waldemar.grabowski@hostnet.pl'],
            [
                'title' => '',
                'first_name' => 'Waldemar',
                'last_name' => 'Grabowski',
                'email' => 'waldemar.grabowski@hostnet.pl',
                'phone' => '+ 48 501 654 274',
                'bio' => 'Założyciel i dyrektor NODN Platforma Nowoczesnej Edukacji. Doświadczony nauczyciel informatyki i techniki.',
                'photo' => '',
                'is_active' => true,                
            ]
        );

        Instructor::updateOrCreate(
            ['email' => 'luman0599@gmail.com'],
            [
                'title' => '',
                'first_name' => 'Łukasz',
                'last_name' => 'Grabowski',
                'email' => 'luman0599@gmail.com',
                'phone' => '+48 510 396 579',
                'bio' => 'mgr inż. informatyki, programista, pasjonat nowych technologii.',
                'photo' => '',
                'is_active' => true,                
            ]
        );

        Instructor::updateOrCreate(
            ['email' => 'roman.lorens@gmail.com'],
            [
                'title' => 'dr',
                'first_name' => 'Roman',
                'last_name' => 'Lorens',
                'email' => 'roman.lorens@gmail.com',
                'phone' => '+48 606 911 443',
                'bio' => 'Ekspert ds. awansu zawodowego nauczycieli, konsultant ds. organizacji i zarządzania oświatą. Ambasador marki MAC Technologie Grupa MAC S.A., akredytowany trener i autor publikacji z zakresu zarządzania oświatą. Posiada wieloletnie doświadczenie w szkoleniach dla dyrektorów i nauczycieli, cieszący się doskonałymi recenzjami uczestników.',
                'photo' => '',
                'is_active' => true,                
            ]
        );        
        Instructor::updateOrCreate(
            ['email' => 'anna_wojkowska@wp.pl'],
            [
                'title' => '',
                'first_name' => 'Anna',
                'last_name' => 'Wojkowska',
                'email' => 'anna_wojkowska@wp.pl',
                'phone' => '+48 690 996 609',
                'bio' => 'Absolwentka Uniwersytetu Śląskiego z zakresu matematyki i informatyki oraz Studiów Podyplomowych z zakresu Zarządzania Zasobami Ludzkimi. Certyfikowany trener oświaty. Certyfikowany mediator szkolny. Trener wspomagania szkół i przedszkoli. Specjalista w zakresie kompetencji kluczowych. Dyrektor szkoły, nauczyciel matematyki i informatyki. Od sześciu lat jako praktyk prowadzi szkolenia i kursy związane z podnoszeniem jakości pracy szkoły. Pasjonatka szkoły bez ocen, stawiająca relacje na pierwszym miejscu w swojej pracy.',
                'photo' => '',
                'is_active' => true,                
            ]
        );
        Instructor::updateOrCreate(
            ['email' => 'donatad@interia.pl'],
            [
                'title' => '',
                'first_name' => 'Donata',
                'last_name' => 'Dzimińska',
                'email' => 'donatad@interia.pl',
                'phone' => '+48 692 963 882',
                'bio' => 'Ddoświadczona certyfikowana trenerka z 25-letnim stażem pracy w roli nauczyciela, doradcy zawodowego i wykładowcy. Pani Donata specjalizuje się w technologii informacyjnej, pomocy psychologiczno-pedagogicznej oraz doradztwie zawodowym. Ukończyła liczne studia podyplomowe, m.in. z zakresu doradztwa zawodowego, zarządzania zasobami ludzkimi oraz edukacji włączającej, a także posiada szerokie doświadczenie w pracy z uczniami oraz nauczycielami.',
                'photo' => '',
                'is_active' => true,                
            ]
        );   
        Instructor::updateOrCreate(
            ['email' => 'spcholerzynmk@gmail.com'],
            [
                'title' => '',
                'first_name' => 'Marzena',
                'last_name' => 'Kącik',
                'email' => 'spcholerzynmk@gmail.com',
                'phone' => '+48 517 484 987',
                'bio' => 'Nauczyciel dyplomowany matematyki, informatyki i fizyki, licencjonowany trener GeoGebry oraz egzaminator ósmoklasisty i maturalny z matematyki. Autorka artykułów z zakresu wykorzystania TIK na lekcjach matematyki, od lat prowadzi warsztaty i szkolenia w tym zakresie.',
                'photo' => '',
                'is_active' => true,                
            ]
        ); 
        Instructor::updateOrCreate(
            ['email' => 'matmar77@op.pl'],
            [
                'title' => '',
                'first_name' => 'Mateusz',
                'last_name' => 'Marciniak',
                'email' => 'matmar77@op.pl',
                'phone' => '+48 605 350 015',
                'bio' => 'Doświadczony dyrektor placówek oświatowych, nauczyciel historii i certyfikowany trener biznesu. Specjalizuje się w zarządzaniu zespołem, motywacji oraz kompetencjach miękkich. Posiada bogate doświadczenie w organizacji i prowadzeniu szkoleń, w tym na temat przepisów prawa dotyczących oświaty, takich jak WOPFU i IPET. Koordynator projektów szkoleniowych dla JST, z umiejętnością dostosowywania programów do indywidualnych potrzeb organizacji.',
                'photo' => '',
                'is_active' => true,                
            ]
        ); 
        Instructor::updateOrCreate(
            ['email' => 'jola.kuropatwa@op.pl'],
            [
                'title' => '',
                'first_name' => 'Jolanta',
                'last_name' => 'Kuropatwa',
                'email' => 'jola.kuropatwa@op.pl',
                'phone' => '+48 505 057 513',
                'bio' => 'Nnauczycielka z 30-letnim stażem, posiadająca również 17-letnie doświadczenie na stanowiskach zarządzania oświatą (dyrektor oraz wicedyrektor).
Jednak to jej praca w kuratorium oświaty, gdzie pełniła funkcje wizytatora i wicekuratora, przynosi jej unikalne spojrzenie na nadzór pedagogiczny. Ta bogata mieszanka doświadczeń sprawia, że Jolanta jest ekspertką w dziedzinie prawa oświatowego i doskonalenia jakości pracy szkoły.',
                'photo' => '',
                'is_active' => true,                
            ]
        );  
        Instructor::updateOrCreate(
            ['email' => 'jan.kowalski@hostnet.pl'],
            [
                'title' => '',                   
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'email' => 'jan.kowalski@hostnet.pl',
                'phone' => '+48 123 456 789',
                'bio' => 'Przykładowy instruktor.',
                'photo' => '',
                'is_active' => true,                
            ]
        );                                                       
    }
}

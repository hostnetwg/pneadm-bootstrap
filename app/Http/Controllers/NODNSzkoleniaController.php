<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NODNSzkoleniaController extends Controller
{
    public function index()
    {
        // Pobieramy dane szkoleń wraz z liczbą uczestników powiązanych w `NODN_zaswiadczenia`
        $szkolenia = DB::connection('mysql_certgen')->table('NODN_szkolenia_lista as s')
            ->select(
                's.id',
                's.nazwa',
                's.zakres',
                's.termin',
                's.online',                
                DB::raw('(SELECT COUNT(*) FROM NODN_zaswiadczenia WHERE id_szkolenia = s.id) as participants_count')
            )
            ->orderByDesc('s.termin') // Sortowanie według daty malejąco
            ->get();

        return view('archiwum.certgen_szkolenia.index', compact('szkolenia'));
    }
    public function exportToCourses()
    {
        // Pobieramy szkolenia z certgen
        $szkolenia = DB::connection('mysql_certgen')->table('NODN_szkolenia_lista')->get();
    
        $importedCount = 0;
        $skippedCount = 0;
    
        foreach ($szkolenia as $szkolenie) {
            // Sprawdzamy, czy kurs już istnieje w bazie docelowej
            $exists = DB::connection('mysql')->table('courses')
                ->where('id_old', $szkolenie->id)
                ->where('source_id_old', 'certgen_NODN')
                ->exists();
    
            if ($exists) {
                $skippedCount++;
                continue;
            }
    
            // Oczyszczanie wartości `nazwa` i `zakres` z HTML
            $title = strip_tags($szkolenie->nazwa);
            $description = strip_tags($szkolenie->zakres);
    
            // Ustawiamy wartość `type` zgodnie z ENUM('online', 'offline')
            $courseType = ($szkolenie->online == 1) ? 'online' : 'offline';
    
            // Wstawienie nowego kursu do `courses`
            $courseId = DB::connection('mysql')->table('courses')->insertGetId([
                'title' => $title, // Oczyszczona nazwa
                'description' => $description, // Oczyszczony opis
                'start_date' => $szkolenie->termin,
                'end_date' => \Carbon\Carbon::parse($szkolenie->termin)->addHours(2)->format('Y-m-d H:i:s'),
                'is_paid' => 1,
                'type' => $courseType, // Poprawna wartość ENUM
                'category' => 'closed',
                'instructor_id' => 1,
                'is_active' => 1,
                'id_old' => $szkolenie->id,
                'source_id_old' => 'certgen_NODN',
            ]);
    
            // ✅ Jeśli kurs jest online, zapisujemy do `course_online_details`
            if ($szkolenie->online == 1) {
                DB::connection('mysql')->table('course_online_details')->insert([
                    'course_id' => $courseId,
                    'platform' => 'ClickMeeting',
                    'meeting_link' => null,  // Brak linku
                    'meeting_password' => null // Brak hasła
                ]);
            } 
            // ✅ Jeśli kurs jest offline, pobieramy dane z `NODN_szkola` na podstawie `id_szkoly`
            else {
                $szkola = DB::connection('mysql_certgen')
                    ->table('NODN_szkola')
                    ->where('id', $szkolenie->id_szkoly)
                    ->first();
                
                if ($szkola) {
                    DB::connection('mysql')->table('course_locations')->insert([
                        'course_id' => $courseId,
                        'location_name' => $szkola->nazwa,
                        'postal_code' => $szkola->kod,
                        'post_office' => $szkola->poczta,
                        'address' => $szkola->adres,
                        'country' => 'Polska',
                    ]);
                }
            }
    
            $importedCount++;
        }
    
        // Komunikat zwrotny
        $message = "Zaimportowano <b>{$importedCount}</b> kursów.";
        if ($skippedCount > 0) {
            $message .= " <br>Pominięto <b>{$skippedCount}</b>, ponieważ już istnieją.";
        }
    
        return redirect()->route('archiwum.certgen_szkolenia.index')->with('success', $message);
    }
    
    
    
}

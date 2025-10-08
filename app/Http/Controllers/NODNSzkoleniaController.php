<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


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
            ->orderByDesc('s.id') // Sortowanie według daty malejąco
            ->get();

        return view('archiwum.certgen_szkolenia.index', compact('szkolenia'));
    }
/*
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
*/    
/**/

    public function exportCourse($id)
    {
        // Pobieramy kurs z bazy źródłowej
        $szkolenie = DB::connection('mysql_certgen')->table('NODN_szkolenia_lista')->find($id);

        if (!$szkolenie) {
            return redirect()->route('archiwum.certgen_szkolenia.index')->with('error', 'Szkolenie nie zostało znalezione.');
        }

        // Sprawdzenie, czy kurs już istnieje w bazie docelowej
        $exists = DB::connection('mysql')->table('courses')
            ->where('id_old', $szkolenie->id)
            ->where('source_id_old', 'certgen_NODN')
            ->exists();

        if ($exists) {
            return redirect()->route('archiwum.certgen_szkolenia.index')->with('error', 'Kurs już istnieje w bazie docelowej.');
        }

        // Oczyszczanie wartości `nazwa` i `zakres` z HTML
        $title = strip_tags($szkolenie->nazwa);
        $description = strip_tags($szkolenie->zakres);

        // Ustawienie `type` zgodnie z ENUM('online', 'offline')
        $courseType = ($szkolenie->online == 1) ? 'online' : 'offline';

        // Wstawienie nowego kursu do `courses`
        $courseId = DB::connection('mysql')->table('courses')->insertGetId([
            'title' => $title, 
            'description' => $description, 
            'start_date' => $szkolenie->termin,
            'end_date' => \Carbon\Carbon::parse($szkolenie->termin)->addHours(2)->format('Y-m-d H:i:s'),
            'is_paid' => 1,
            'type' => $courseType, 
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
                'meeting_link' => null, 
                'meeting_password' => null
            ]);
        } 
        // ✅ Jeśli kurs jest offline, pobieramy dane z `NODN_szkola`
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

        return redirect()->route('archiwum.certgen_szkolenia.index')->with('success', "Kurs '{$title}' został zaimportowany.");
    }


/**/


    public function exportParticipants($id)
    {
        // Pobieramy kurs z bazy źródłowej
        $szkolenie = DB::connection('mysql_certgen')->table('NODN_szkolenia_lista')->find($id);
    
        if (!$szkolenie) {
            return redirect()->route('archiwum.certgen_szkolenia.index')->with('error', 'Szkolenie nie zostało znalezione.');
        }
    
        // Sprawdzenie czy kurs istnieje w bazie docelowej na podstawie id_old i source_id_old
        $course = DB::connection('mysql')->table('courses')
            ->where('id_old', $szkolenie->id)
            ->where('source_id_old', 'certgen_NODN')
            ->first();
    
        if (!$course) {
            return redirect()->route('archiwum.certgen_szkolenia.index')->with('error', 'Kurs nie został znaleziony w bazie docelowej.');
        }
    
        // Pobieramy uczestników z tabeli `NODN_zaswiadczenia`, którzy są powiązani z kursem
        $uczestnicy = DB::connection('mysql_certgen')->table('NODN_zaswiadczenia')
            ->where('id_szkolenia', $szkolenie->id)
            ->get();
    
        $importedCount = 0;
        $skippedCount = 0;
        $skippedIds = []; // Lista ID pominiętych uczestników
    
        foreach ($uczestnicy as $uczestnik) {
            // Konwersja danych do porównywania (trymowanie, małe litery)
            $firstName = strtolower(trim($uczestnik->imie));
            $lastName = strtolower(trim($uczestnik->nazwisko));
            $birthDate = ($uczestnik->ur_data && $uczestnik->ur_data !== '0000-00-00') ? Carbon::parse($uczestnik->ur_data)->format('Y-m-d') : null;
            $birthPlace = !empty($uczestnik->ur_miejsce) ? $uczestnik->ur_miejsce : null;
    
            // Sprawdzamy, czy uczestnik już istnieje w bazie docelowej
            $participant = DB::connection('mysql')->table('participants')
                ->where('course_id', $course->id)
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName])
                ->when($birthDate, function ($query) use ($birthDate) {
                    return $query->where('birth_date', $birthDate);
                })
                ->first();
    
            // Jeśli uczestnik nie istnieje, dodajemy go
            if (!$participant) {
                $participantId = DB::connection('mysql')->table('participants')->insertGetId([
                    'course_id' => $course->id,
                    'first_name' => ucfirst($firstName),
                    'last_name' => ucfirst($lastName),
                    'birth_date' => $birthDate,
                    'birth_place' => $birthPlace,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
    
                $importedCount++;
            } else {
                $participantId = $participant->id;
                $skippedIds[] = $uczestnik->id;
                $skippedCount++;
            }
    
            // Pobieramy numer certyfikatu i usuwamy podwójne ukośniki
            $certificateNumber = trim($uczestnik->numer . '/' . ltrim($uczestnik->numer_cd, '/')); // Usunięcie pierwszego "/" z numer_cd
    
            // Sprawdzamy, czy certyfikat już istnieje dla tego uczestnika i kursu
            $certificateExists = DB::connection('mysql')->table('certificates')
                ->where('participant_id', $participantId)
                ->where('course_id', $course->id)
                ->where('certificate_number', $certificateNumber)
                ->exists();
    
            // Jeśli certyfikat nie istnieje, dodajemy go
            if (!$certificateExists && !empty($uczestnik->numer) && !empty($uczestnik->numer_cd)) {
                DB::connection('mysql')->table('certificates')->insert([
                    'participant_id' => $participantId,
                    'course_id' => $course->id,
                    'certificate_number' => $certificateNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    
        // Tworzymy komunikat podsumowujący
        if (!empty($skippedIds)) {
            $skippedString = implode(', ', $skippedIds);
            $message = "Zaimportowano {$importedCount} uczestników dla kursu '{$szkolenie->nazwa}'.<br>"
                     . "Pominięto {$skippedCount} uczestników, ponieważ już istnieją.<br>"
                     . "ID pominiętych rekordów: {$skippedString}.";
        } else {
            $message = "Zaimportowano wszystkich uczestników ({$importedCount}) dla kursu '{$szkolenie->nazwa}' wraz z certyfikatami.";
        }
    
        return redirect()->route('archiwum.certgen_szkolenia.index')->with('success', $message);
    }

/*-------*/

    public function exportSelectedCourses(Request $request)
    {
        $selectedIds = $request->input('selected_courses', []);

        if (empty($selectedIds)) {
            return redirect()->route('archiwum.certgen_szkolenia.index')->with('error', 'Nie wybrano żadnych kursów do eksportu.');
        }

        $importedCount = 0;
        $skippedCount = 0;

        $szkolenia = DB::connection('mysql_certgen')
            ->table('NODN_szkolenia_lista')
            ->whereIn('id', $selectedIds)
            ->get();

        foreach ($szkolenia as $szkolenie) {
            $exists = DB::connection('mysql')->table('courses')
                ->where('id_old', $szkolenie->id)
                ->where('source_id_old', 'certgen_NODN')
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            $title = strip_tags($szkolenie->nazwa);
            $description = strip_tags($szkolenie->zakres);
            $courseType = ($szkolenie->online == 1) ? 'online' : 'offline';

            $courseId = DB::connection('mysql')->table('courses')->insertGetId([
                'title' => $title,
                'description' => $description,
                'start_date' => $szkolenie->termin,
                'end_date' => \Carbon\Carbon::parse($szkolenie->termin)->addHours(2)->format('Y-m-d H:i:s'),
                'is_paid' => 1,
                'type' => $courseType,
                'category' => 'closed',
                'instructor_id' => 1,
                'is_active' => 1,
                'id_old' => $szkolenie->id,
                'source_id_old' => 'certgen_NODN',
            ]);

            if ($szkolenie->online == 1) {
                DB::connection('mysql')->table('course_online_details')->insert([
                    'course_id' => $courseId,
                    'platform' => 'ClickMeeting',
                    'meeting_link' => null,
                    'meeting_password' => null
                ]);
            } else {
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

        $message = "Zaimportowano <b>{$importedCount}</b> kursów.";
        if ($skippedCount > 0) {
            $message .= " <br>Pominięto <b>{$skippedCount}</b>, ponieważ już istnieją.";
        }

        return redirect()->route('archiwum.certgen_szkolenia.index')->with('success', $message);
    }


/*=======*/
    
    
}

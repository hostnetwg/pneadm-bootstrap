<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\DataCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DataCompletionController extends Controller
{
    protected DataCompletionService $service;

    public function __construct(DataCompletionService $service)
    {
        $this->service = $service;
    }

    /**
     * Widok "Test" - bezpieczne testowanie
     */
    public function test(Request $request)
    {
        // Pobierz tylko kursy certgen_Publigo
        $courses = Course::where('source_id_old', 'certgen_Publigo')
            ->orderBy('start_date', 'desc')
            ->with('instructor')
            ->paginate(20);

        // Dla każdego kursu pobierz statystyki
        $courses->getCollection()->transform(function($course) {
            $stats = $this->service->getCourseStatistics($course->id);
            $courseArray = $course->toArray();
            $courseArray['stats'] = $stats;
            return $courseArray;
        });

        return view('data-completion.test', compact('courses'));
    }

    /**
     * Widok "Zbierz" - produkcyjny
     */
    public function collect(Request $request)
    {
        // Pobierz globalne statystyki
        $globalStats = $this->service->getGlobalStatistics();

        // Pobierz tylko kursy certgen_Publigo
        $courses = Course::where('source_id_old', 'certgen_Publigo')
            ->orderBy('start_date', 'desc')
            ->with('instructor')
            ->paginate(20);

        // Dla każdego kursu pobierz statystyki
        $courses->getCollection()->transform(function($course) {
            $stats = $this->service->getCourseStatistics($course->id);
            $courseArray = $course->toArray();
            $courseArray['stats'] = $stats;
            return $courseArray;
        });

        return view('data-completion.collect', compact('courses', 'globalStats'));
    }

    /**
     * Symulacja wysyłki dla testu (bez realnej wysyłki)
     */
    public function simulateTest(Request $request, int $courseId)
    {
        $request->validate([
            'test_email' => 'nullable|email',
        ]);

        $course = Course::findOrFail($courseId);
        
        if ($course->source_id_old !== 'certgen_Publigo') {
            return back()->withErrors(['error' => 'Ten kurs nie jest kursem certgen_Publigo']);
        }

        $testEmail = $request->input('test_email');
        $participants = $this->service->findParticipantsWithMissingData($courseId);

        // Filtruj po test_email jeśli podano
        if ($testEmail) {
            $participants = $participants->filter(function($p) use ($testEmail) {
                return strtolower(trim($p['email'])) === strtolower(trim($testEmail));
            });
        }

        // Pobierz pierwszy przykład dla podglądu
        $exampleParticipant = $participants->first();

        return view('data-completion.test-simulation', [
            'course' => $course,
            'participants' => $participants,
            'exampleParticipant' => $exampleParticipant,
            'testEmail' => $testEmail,
        ]);
    }

    /**
     * Wysyłka pojedynczego maila testowego
     */
    public function sendTestEmail(Request $request, int $courseId)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        $course = Course::findOrFail($courseId);
        
        if ($course->source_id_old !== 'certgen_Publigo') {
            return back()->withErrors(['error' => 'Ten kurs nie jest kursem certgen_Publigo']);
        }

        try {
            $result = $this->service->sendRequestsForCourse(
                $courseId,
                true, // test mode
                $request->input('test_email')
            );

            if ($result['sent'] > 0) {
                return back()->with('success', 'Wysłano testowy email na adres: waldemar.grabowski@hostnet.pl (oryginalny adres: ' . $request->input('test_email') . ')');
            } else {
                $errorMsg = 'Nie udało się wysłać testowego emaila.';
                if (count($result['errors']) > 0) {
                    $errorMsg .= ' Błąd: ' . $result['errors'][0]['error'];
                } else {
                    $errorMsg .= ' Sprawdź czy uczestnik spełnia kryteria (ma braki w danych i email jest w bazie).';
                }
                return back()->withErrors(['error' => $errorMsg]);
            }
        } catch (\Exception $e) {
            Log::error('Błąd wysyłki testowego emaila', [
                'course_id' => $courseId,
                'test_email' => $request->input('test_email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors(['error' => 'Wystąpił błąd podczas wysyłki: ' . $e->getMessage()]);
        }
    }

    /**
     * Wysyłka próśb dla kursu (produkcyjna)
     */
    public function sendForCourse(Request $request, int $courseId)
    {
        $course = Course::findOrFail($courseId);
        
        if ($course->source_id_old !== 'certgen_Publigo') {
            return back()->withErrors(['error' => 'Ten kurs nie jest kursem certgen_Publigo']);
        }

        // Sprawdź czy użytkownik chce wymusić ponowną wysyłkę
        $forceResend = $request->has('force_resend') && $request->input('force_resend') === '1';

        try {
            $result = $this->service->sendRequestsForCourse($courseId, false, null, $forceResend);

            $message = "Wysłano {$result['sent']} próśb.";
            if ($result['resent'] > 0) {
                $message .= " Wysłano ponownie {$result['resent']} próśb.";
            }
            if ($result['skipped'] > 0) {
                $message .= " Pominięto {$result['skipped']} (już wysłano wcześniej i nie można wysłać ponownie).";
            }
            if (count($result['errors']) > 0) {
                $message .= " Błędy: " . count($result['errors']);
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Błąd wysyłki próśb dla kursu', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors(['error' => 'Wystąpił błąd podczas wysyłki: ' . $e->getMessage()]);
        }
    }

    /**
     * Odświeża statystyki dla kursów certgen_Publigo i BD:Certgen-education
     */
    public function refreshBDCertgenEducationStats(Request $request)
    {
        try {
            // Odśwież statystyki dla certgen_Publigo (główne kursy na stronie)
            $publigoStats = $this->service->refreshCertgenPubligoStatistics();
            
            // Odśwież statystyki dla BD:Certgen-education
            $bdStats = $this->service->refreshBDCertgenEducationStatistics();
            
            return back()->with('success', 
                "Odświeżono statystyki dla kursów certgen_Publigo i BD:Certgen-education. " .
                "certgen_Publigo: {$publigoStats['total_stats']['total_courses']} kursów, " .
                "{$publigoStats['total_stats']['participants_with_completed_data']} uczestników z uzupełnionymi danymi. " .
                "BD:Certgen-education: {$bdStats['total_stats']['total_courses']} kursów, " .
                "{$bdStats['total_stats']['participants_with_completed_data']} uczestników z uzupełnionymi danymi."
            );
        } catch (\Exception $e) {
            Log::error('Błąd odświeżania statystyk', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors(['error' => 'Wystąpił błąd podczas odświeżania statystyk: ' . $e->getMessage()]);
        }
    }

    /**
     * Widok "Sprawdź konflikty"
     */
    public function conflicts(Request $request)
    {
        // Pobierz dostępne typy szkoleń (source_id_old) do filtra
        $sourceTypes = Course::select('source_id_old')
            ->distinct()
            ->whereNotNull('source_id_old')
            ->where('source_id_old', '!=', '')
            ->orderBy('source_id_old')
            ->pluck('source_id_old');

        $filterSourceId = $request->input('source_id', 'certgen_Publigo');

        $conflicts = $this->service->getEmailConflicts($filterSourceId);

        return view('data-completion.conflicts', compact('conflicts', 'sourceTypes', 'filterSourceId'));
    }

    /**
     * Ujednolica dane uczestnika (konflikty)
     */
    public function unifyConflict(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
        ]);

        try {
            $updated = $this->service->unifyParticipantData(
                $request->input('email'),
                $request->input('first_name'),
                $request->input('last_name')
            );

            return back()->with('success', "Pomyślnie ujednolicono dane dla {$updated} rekordów uczestnika: {$request->input('first_name')} {$request->input('last_name')}");
        } catch (\Exception $e) {
            Log::error('Błąd podczas ujednolicania konfliktu', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Wystąpił błąd podczas aktualizacji danych: ' . $e->getMessage()]);
        }
    }
}

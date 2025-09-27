<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\Course;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Survey::with(['course', 'instructor', 'importedBy']);

        // Filtrowanie według kursu
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filtrowanie według instruktora
        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }

        // Wyszukiwanie
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('course', function($courseQuery) use ($searchTerm) {
                      $courseQuery->where('title', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        $surveys = $query->orderBy('imported_at', 'desc')->paginate(15);

        // Pobierz listę kursów i instruktorów dla filtrów
        $courses = Course::orderBy('title')->get();
        $instructors = \App\Models\Instructor::orderBy('first_name')->get();

        return view('surveys.index', compact('surveys', 'courses', 'instructors'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $courses = Course::orderBy('title')->get();
        $instructors = \App\Models\Instructor::orderBy('first_name')->get();
        
        return view('surveys.create', compact('courses', 'instructors'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'instructor_id' => 'nullable|exists:instructors,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => 'required|string|max:100',
            'survey_file' => 'nullable|file|mimes:csv,xlsx,xls|max:10240' // 10MB max
        ]);

        try {
            DB::beginTransaction();

            $surveyData = [
                'course_id' => $request->course_id,
                'instructor_id' => $request->instructor_id,
                'title' => $request->title,
                'description' => $request->description,
                'source' => $request->source,
                'imported_at' => now(),
                'imported_by' => Auth::id(),
                'total_responses' => 0
            ];

            // Jeśli przesłano plik, zapisz go (używając tej samej logiki co SurveyImportController)
            if ($request->hasFile('survey_file')) {
                $file = $request->file('survey_file');
                $originalFileName = $file->getClientOriginalName();
                $fileName = $request->course_id . '_' . $originalFileName;
                $path = $file->storeAs('surveys/imports', $fileName, 'private');
                
                $surveyData['original_file_path'] = $path;
            }

            $survey = Survey::create($surveyData);

            // Jeśli przesłano plik CSV, automatycznie zaimportuj dane
            if ($request->hasFile('survey_file') && $request->file('survey_file')->getClientOriginalExtension() === 'csv') {
                $this->importSurveyData($survey, $request->file('survey_file'));
            }

            DB::commit();

            $message = 'Ankieta została utworzona pomyślnie.';
            if ($request->hasFile('survey_file') && $request->file('survey_file')->getClientOriginalExtension() === 'csv') {
                $message .= ' Dane z pliku CSV zostały automatycznie zaimportowane.';
            }

            return redirect()->route('surveys.show', $survey->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Błąd tworzenia ankiety: ' . $e->getMessage());
            
            return back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas tworzenia ankiety: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Survey $survey)
    {
        $survey->load(['course', 'instructor', 'importedBy', 'questions', 'responses']);
        
        // Pobierz statystyki
        $stats = $survey->getResponseStats();
        $averageRating = $survey->getAverageRating();
        $groupedQuestions = $survey->getGroupedQuestions();
        
        return view('surveys.show', compact('survey', 'stats', 'averageRating', 'groupedQuestions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Survey $survey)
    {
        $courses = Course::orderBy('title')->get();
        $instructors = \App\Models\Instructor::orderBy('first_name')->get();
        
        return view('surveys.edit', compact('survey', 'courses', 'instructors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Survey $survey)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'instructor_id' => 'nullable|exists:instructors,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => 'required|string|max:100'
        ]);

        $survey->update([
            'course_id' => $request->course_id,
            'instructor_id' => $request->instructor_id,
            'title' => $request->title,
            'description' => $request->description,
            'source' => $request->source
        ]);

        return redirect()->route('surveys.show', $survey->id)
            ->with('success', 'Ankieta została zaktualizowana pomyślnie.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Survey $survey)
    {
        $courseId = $survey->course_id;
        
        $survey->delete();

        return redirect()->route('courses.show', $courseId)
            ->with('success', 'Ankieta została usunięta pomyślnie.');
    }

    /**
     * Pokaż ankiety dla konkretnego kursu
     */
    public function courseSurveys(Course $course)
    {
        $surveys = $course->surveys()
            ->with(['instructor', 'importedBy'])
            ->orderBy('imported_at', 'desc')
            ->get();

        return view('surveys.course-surveys', compact('course', 'surveys'));
    }

    /**
     * Pokaż formularz wyboru pytań do raportu
     */
    public function showReportForm(Survey $survey)
    {
        $survey->load(['course', 'instructor', 'questions']);
        $groupedQuestions = $survey->getGroupedQuestions();
        
        return view('surveys.report-form', compact('survey', 'groupedQuestions'));
    }

    /**
     * Generuj raport PDF dla ankiety
     */
    public function generateReport(Request $request, Survey $survey)
    {
        $request->validate([
            'selected_questions' => 'required|array|min:1',
            'selected_questions.*' => 'exists:survey_questions,id'
        ]);

        $survey->load(['course', 'instructor', 'questions', 'responses']);

        // Filtruj pytania według wyboru użytkownika
        $selectedQuestionIds = $request->input('selected_questions', []);
        $selectedQuestions = $survey->questions->whereIn('id', $selectedQuestionIds);
        $groupedQuestions = $survey->getGroupedQuestions();

        $stats = $survey->getResponseStats();
        $averageRating = $survey->getAverageRating();

        // Generuj PDF
        return $this->generatePdfReport($survey, $selectedQuestions, $stats, $averageRating, $groupedQuestions);
    }

    /**
     * Generuj PDF raportu
     */
    private function generatePdfReport($survey, $selectedQuestions, $stats, $averageRating, $groupedQuestions)
    {
        // Użyj dompdf do generowania PDF
        $html = view('surveys.report', compact('survey', 'stats', 'averageRating', 'selectedQuestions', 'groupedQuestions'))->render();
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'raport_ankiety_' . $survey->id . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return $dompdf->stream($filename, [
            'Attachment' => true,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * Pobierz oryginalny plik CSV ankiety
     */
    public function downloadOriginalFile(Survey $survey)
    {
        if (!$survey->original_file_path) {
            return back()->with('error', 'Brak pliku do pobrania.');
        }

        try {
            if (\Storage::disk('private')->exists($survey->original_file_path)) {
                $filename = basename($survey->original_file_path);
                return \Storage::disk('private')->download($survey->original_file_path, $filename);
            } else {
                return back()->with('error', 'Plik nie istnieje na serwerze.');
            }
        } catch (\Exception $e) {
            \Log::error('Błąd pobierania pliku CSV: ' . $e->getMessage());
            return back()->with('error', 'Wystąpił błąd podczas pobierania pliku.');
        }
    }

    /**
     * Usuń oryginalny plik CSV ankiety
     */
    public function deleteOriginalFile(Survey $survey)
    {
        if (!$survey->original_file_path) {
            return back()->with('error', 'Brak pliku do usunięcia.');
        }

        try {
            // Usuń plik z dysku
            if (\Storage::disk('private')->exists($survey->original_file_path)) {
                \Storage::disk('private')->delete($survey->original_file_path);
            }

            // Usuń ścieżkę z bazy danych
            $survey->update(['original_file_path' => null]);

            return back()->with('success', 'Oryginalny plik CSV został usunięty.');

        } catch (\Exception $e) {
            \Log::error('Błąd usuwania pliku CSV: ' . $e->getMessage());
            return back()->with('error', 'Wystąpił błąd podczas usuwania pliku.');
        }
    }

    /**
     * Wyszukaj szkolenie na podstawie daty z pliku
     */
    public function searchCourse(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = $request->input('date');

        try {
            // Wyszukaj wszystkie szkolenia na podstawie daty
            $query = Course::with('instructor')
                ->where(function($q) use ($date) {
                    $q->whereDate('start_date', $date)
                      ->orWhereDate('end_date', $date);
                });

            $courses = $query->get();

            if ($courses->count() > 0) {
                // Jeśli jest tylko jedno szkolenie, zwróć je bezpośrednio
                if ($courses->count() === 1) {
                    $course = $courses->first();
                    return response()->json([
                        'success' => true,
                        'single_course' => true,
                        'course' => [
                            'id' => $course->id,
                            'title' => $course->title,
                            'start_date' => $course->start_date ? $course->start_date->format('d.m.Y') : null,
                            'end_date' => $course->end_date ? $course->end_date->format('d.m.Y') : null
                        ],
                        'instructor' => $course->instructor ? [
                            'id' => $course->instructor->id,
                            'name' => $course->instructor->getFullTitleNameAttribute()
                        ] : null
                    ]);
                } else {
                    // Jeśli jest więcej szkoleń, zwróć listę do wyboru
                    return response()->json([
                        'success' => true,
                        'multiple_courses' => true,
                        'message' => 'Znaleziono ' . $courses->count() . ' szkoleń na datę ' . \Carbon\Carbon::parse($date)->format('d.m.Y'),
                        'courses' => $courses->map(function($course) {
                            return [
                                'id' => $course->id,
                                'title' => $course->title,
                                'start_date' => $course->start_date ? $course->start_date->format('d.m.Y H:i') : null,
                                'end_date' => $course->end_date ? $course->end_date->format('d.m.Y H:i') : null,
                                'instructor' => $course->instructor ? [
                                    'id' => $course->instructor->id,
                                    'name' => $course->instructor->getFullTitleNameAttribute()
                                ] : null
                            ];
                        })
                    ]);
                }
            } else {
                // Jeśli nie znaleziono szkolenia na podaną datę, pokaż szkolenia z podobnych dat
                $similarCourses = Course::with('instructor')
                    ->where(function($q) use ($date) {
                        // Szukaj w zakresie ±7 dni od podanej daty
                        $q->whereBetween('start_date', [
                            \Carbon\Carbon::parse($date)->subDays(7),
                            \Carbon\Carbon::parse($date)->addDays(7)
                        ])
                        ->orWhereBetween('end_date', [
                            \Carbon\Carbon::parse($date)->subDays(7),
                            \Carbon\Carbon::parse($date)->addDays(7)
                        ]);
                    })
                    ->limit(5)
                    ->get();

                if ($similarCourses->count() > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nie znaleziono szkolenia na datę ' . \Carbon\Carbon::parse($date)->format('d.m.Y') . '. Szkolenia w podobnym okresie:',
                        'suggestions' => $similarCourses->map(function($course) {
                            return [
                                'id' => $course->id,
                                'title' => $course->title,
                                'start_date' => $course->start_date ? $course->start_date->format('d.m.Y') : null,
                                'end_date' => $course->end_date ? $course->end_date->format('d.m.Y') : null
                            ];
                        })
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nie znaleziono żadnego szkolenia na datę ' . \Carbon\Carbon::parse($date)->format('d.m.Y')
                    ]);
                }
            }

        } catch (\Exception $e) {
            \Log::error('Błąd wyszukiwania szkolenia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Wystąpił błąd podczas wyszukiwania szkolenia'
            ], 500);
        }
    }

    /**
     * Importuj dane ankiety z pliku CSV
     */
    private function importSurveyData(Survey $survey, $file)
    {
        // Przetwórz plik CSV
        $csvData = $this->parseCsvFile($file);
        
        if (empty($csvData)) {
            throw new \Exception('Plik CSV jest pusty lub nieprawidłowy.');
        }

        // Pobierz nagłówki (pytania)
        $headers = array_keys($csvData[0]);
        $questions = [];

        // Utwórz pytania
        foreach ($headers as $index => $header) {
            if ($header === 'Sygnatura czasowa') {
                continue; // Pomijamy kolumnę z czasem
            }

            $questionType = $this->detectQuestionType($header, $csvData);
            
            $question = SurveyQuestion::create([
                'survey_id' => $survey->id,
                'question_text' => $header,
                'question_type' => $questionType,
                'question_order' => $index,
                'options' => $this->extractOptions($header, $csvData, $questionType)
            ]);

            $questions[] = $question;
        }

        // Przetwórz odpowiedzi
        $responseCount = 0;
        foreach ($csvData as $row) {
            $responseData = [];
            $submittedAt = null;

            foreach ($row as $question => $answer) {
                if ($question === 'Sygnatura czasowa') {
                    $submittedAt = $this->parseTimestamp($answer);
                    continue;
                }

                if (!empty($answer)) {
                    $responseData[$question] = $this->cleanAnswer($answer);
                }
            }

            if (!empty($responseData) && $submittedAt) {
                SurveyResponse::create([
                    'survey_id' => $survey->id,
                    'response_data' => $responseData,
                    'submitted_at' => $submittedAt
                ]);
                $responseCount++;
            }
        }

        // Zaktualizuj liczbę odpowiedzi
        $survey->update(['total_responses' => $responseCount]);
    }

    /**
     * Parsuj plik CSV
     */
    private function parseCsvFile($file): array
    {
        $data = [];
        $handle = fopen($file->getPathname(), 'r');
        
        if ($handle === false) {
            throw new \Exception('Nie można otworzyć pliku CSV.');
        }

        $headers = fgetcsv($handle, 0, ',', '"');
        
        if ($headers === false) {
            fclose($handle);
            throw new \Exception('Nie można odczytać nagłówków z pliku CSV.');
        }

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * Wykryj typ pytania na podstawie nagłówka i danych
     */
    private function detectQuestionType(string $header, array $data): string
    {
        // Sprawdź czy to pytanie ratingowe (1-5)
        $sampleAnswers = array_slice(array_column($data, $header), 0, 10);
        $numericAnswers = array_filter($sampleAnswers, function($answer) {
            return is_numeric($answer) && $answer >= 1 && $answer <= 5;
        });

        if (count($numericAnswers) >= count($sampleAnswers) * 0.8) {
            return 'rating';
        }

        // Sprawdź czy to pytanie z opcjami (zawiera nawiasy kwadratowe)
        if (strpos($header, '[') !== false && strpos($header, ']') !== false) {
            return 'multiple_choice';
        }

        // Sprawdź czy to pytanie jednokrotnego wyboru
        $uniqueAnswers = array_unique(array_filter($sampleAnswers, function($answer) {
            return !empty(trim($answer));
        }));
        
        if (count($uniqueAnswers) <= 15 && count($uniqueAnswers) >= 2) {
            // Sprawdź czy odpowiedzi wyglądają jak opcje wyboru
            $choicePatterns = [
                '/^(tak|nie)$/i',
                '/^(bardzo dobry|dobry|średni|słaby|bardzo słaby)$/i',
                '/^(zdecydowanie tak|raczej tak|nie mam zdania|raczej nie|zdecydowanie nie)$/i',
                '/^(zawsze|często|czasami|rzadko|nigdy)$/i',
                '/^(bardzo zadowolony|zadowolony|neutralny|niezadowolony|bardzo niezadowolony)$/i',
                '/^(portal|facebook|instagram|linkedin|twitter|youtube|tiktok)$/i',
                '/^(dyrekcja|szkoła|nauczyciel|kolega|koleżanka|przełożony|szef)$/i',
                '/^(e-mail|mail|wiadomość|newsletter|strona internetowa|www)$/i',
                '/^(radio|telewizja|gazeta|czasopismo|ulotka|plakat)$/i',
                '/^(inne|inny|inna|inne źródło|inne miejsce)$/i'
            ];
            
            $matchesChoicePattern = false;
            foreach ($choicePatterns as $pattern) {
                $matches = 0;
                foreach ($uniqueAnswers as $answer) {
                    if (preg_match($pattern, trim($answer))) {
                        $matches++;
                    }
                }
                if ($matches >= count($uniqueAnswers) * 0.6) {
                    $matchesChoicePattern = true;
                    break;
                }
            }
            
            if ($matchesChoicePattern) {
                return 'single_choice';
            }
        }

        // Sprawdź czy to pytanie z datą/czasem
        $dateAnswers = array_filter($sampleAnswers, function($answer) {
            return !empty($answer) && (strtotime($answer) !== false || preg_match('/\d{4}\/\d{2}\/\d{2}/', $answer));
        });

        if (count($dateAnswers) >= count($sampleAnswers) * 0.8) {
            return 'date';
        }

        // Domyślnie tekst
        return 'text';
    }

    /**
     * Wyciągnij opcje dla pytań wielokrotnego wyboru
     */
    private function extractOptions(string $header, array $data, string $questionType): ?array
    {
        if ($questionType !== 'multiple_choice') {
            return null;
        }

        $options = [];
        $sampleAnswers = array_slice(array_column($data, $header), 0, 20);
        
        foreach ($sampleAnswers as $answer) {
            if (!empty($answer)) {
                // Podziel odpowiedzi po średniku lub przecinku
                $parts = preg_split('/[;,]/', $answer);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (!empty($part) && !in_array($part, $options)) {
                        $options[] = $part;
                    }
                }
            }
        }

        return !empty($options) ? $options : null;
    }

    /**
     * Parsuj timestamp z Google Forms
     */
    private function parseTimestamp(string $timestamp): ?\DateTime
    {
        try {
            // Google Forms używa formatu: "2025/08/18 1:56:47 PM EEST"
            $timestamp = str_replace(' EEST', '', $timestamp);
            $timestamp = str_replace(' CEST', '', $timestamp);
            
            return new \DateTime($timestamp);
        } catch (\Exception $e) {
            return now();
        }
    }

    /**
     * Oczyść odpowiedź
     */
    private function cleanAnswer(string $answer): string
    {
        return trim($answer);
    }
}

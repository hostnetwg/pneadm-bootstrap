<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'source' => 'required|string|max:100'
        ]);

        $survey = Survey::create([
            'course_id' => $request->course_id,
            'instructor_id' => $request->instructor_id,
            'title' => $request->title,
            'description' => $request->description,
            'source' => $request->source,
            'imported_at' => now(),
            'imported_by' => Auth::id(),
            'total_responses' => 0
        ]);

        return redirect()->route('surveys.show', $survey->id)
            ->with('success', 'Ankieta została utworzona pomyślnie.');
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
}

<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Services\Certificate\CourseSeriesCertificateSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseSeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $series = CourseSeries::withCount('courses')->orderBy('sort_order')->orderBy('name')->get();

        return view('courses.series.index', compact('series'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $certificateTemplates = $this->certificateTemplatesForSeries();

        return view('courses.series.create', compact('certificateTemplates'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'certificate_format' => 'nullable|string|max:255',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
        ]);

        $this->normalizeCertificateDefaults($validated);

        // Generowanie sluga z nazwy
        $validated['slug'] = Str::slug($validated['name']);

        // Sprawdzenie unikalności sluga (proste zabezpieczenie, można rozbudować)
        if (CourseSeries::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] .= '-'.time();
        }

        // Obsługa przesyłania obrazka
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('course_series', 'public');
        } else {
            unset($validated['image']);
        }

        CourseSeries::create($validated);

        return redirect()->route('courses.series.index')->with('success', 'Seria została utworzona.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CourseSeries $series)
    {
        // Pobierz kursy przypisane do serii (już posortowane przez relację w modelu)
        $courses = $series->courses;

        // Pobierz ID kursów już przypisanych do serii
        $assignedCourseIds = $courses->pluck('id')->toArray();

        // Pobierz wszystkie kursy do wyboru (do formularza dodawania w widoku show)
        // Sortowanie po dacie rozpoczęcia od najnowszych, z instruktorem i wszystkimi potrzebnymi polami
        // Wykluczamy kursy już przypisane do serii
        $allCourses = Course::with('instructor')
            ->whereNotIn('id', $assignedCourseIds)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'title', 'start_date', 'instructor_id', 'is_active', 'is_paid', 'type', 'category', 'source_id_old']);

        // Pobierz opcje dla source_id_old
        $sourceIdOldOptions = Course::whereNotNull('source_id_old')
            ->where('source_id_old', '!=', '')
            ->distinct()
            ->orderBy('source_id_old')
            ->pluck('source_id_old');

        $series->load('certificateTemplate');

        return view('courses.series.show', compact('series', 'courses', 'allCourses', 'sourceIdOldOptions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CourseSeries $series)
    {
        $certificateTemplates = $this->certificateTemplatesForSeries($series);

        return view('courses.series.edit', compact('series', 'certificateTemplates'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseSeries $series)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'remove_image' => 'nullable|string',
            'certificate_format' => 'nullable|string|max:255',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
        ]);

        $this->normalizeCertificateDefaults($validated);

        // Aktualizacja sluga tylko jeśli zmieniła się nazwa (opcjonalnie)
        if ($series->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name']);
            if (CourseSeries::where('slug', $validated['slug'])->where('id', '!=', $series->id)->exists()) {
                $validated['slug'] .= '-'.time();
            }
        }

        // Usunięcie obrazka, jeśli użytkownik zaznaczył "Usuń obrazek"
        if ($request->has('remove_image')) {
            if ($series->image && Storage::disk('public')->exists($series->image)) {
                Storage::disk('public')->delete($series->image);
            }
            $validated['image'] = null;
        }

        // Obsługa przesyłania nowego obrazka
        if ($request->hasFile('image')) {
            // Usunięcie poprzedniego obrazka, jeśli istnieje
            if ($series->image && Storage::disk('public')->exists($series->image)) {
                Storage::disk('public')->delete($series->image);
            }

            // Zapis nowego obrazka
            $validated['image'] = $request->file('image')->store('course_series', 'public');
        } else {
            // Jeśli nie przesyłamy nowego obrazka i nie usuwamy, zachowujemy stary
            unset($validated['image']);
        }

        $series->update($validated);

        return redirect()->route('courses.series.index')->with('success', 'Seria została zaktualizowana.');
    }

    /**
     * Update courses assigned to the series.
     */
    public function updateCourses(Request $request, CourseSeries $series, CourseSeriesCertificateSettings $seriesCertificateSettings)
    {
        $previousCourseIds = $series->courses()->pluck('courses.id')->all();

        // Oczekujemy formatu: ['courses' => [id1, id2, id3]] (kolejność ma znaczenie)
        $newCourseIds = [];
        if ($request->has('courses')) {
            $syncData = [];
            foreach ($request->input('courses', []) as $index => $courseId) {
                $courseId = (int) $courseId;
                if ($courseId <= 0) {
                    continue;
                }
                $syncData[$courseId] = ['order_in_series' => $index + 1];
                $newCourseIds[] = $courseId;
            }
            $series->courses()->sync($syncData);
        } else {
            // Jeśli tablica pusta, usuwamy wszystkie przypisania
            $series->courses()->detach();
        }

        $seriesCertificateSettings->applyMembershipChange($series->fresh(), $previousCourseIds, $newCourseIds);

        return redirect()->route('courses.series.show', $series)->with('success', 'Lista kursów została zaktualizowana.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseSeries $series)
    {
        $series->delete();

        return redirect()->route('courses.series.index')->with('success', 'Seria została usunięta.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function normalizeCertificateDefaults(array &$validated): void
    {
        $format = trim((string) ($validated['certificate_format'] ?? ''));
        $validated['certificate_format'] = $format === '' ? null : $format;
        $validated['certificate_template_id'] = $validated['certificate_template_id'] ?: null;
    }

    private function certificateTemplatesForSeries(?CourseSeries $series = null): Collection
    {
        return CertificateTemplate::query()
            ->where(function ($query) use ($series) {
                $query->where('is_active', true);
                if ($series?->certificate_template_id) {
                    $query->orWhere('id', $series->certificate_template_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}

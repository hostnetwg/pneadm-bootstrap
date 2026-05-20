<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\OnlineCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnlineCoursesController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = OnlineCourse::query()->with('instructor')->withCount(['modules', 'enrollments'])->latest('id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('slug', 'like', '%'.$q.'%')
                    ->orWhere('legacy_publigo_product_id', 'like', '%'.$q.'%');
            });
        }

        $courses = $query->paginate(20)->withQueryString();

        return view('online-courses.index', compact('courses', 'q'));
    }

    public function create(): View
    {
        $instructors = Instructor::query()->orderBy('last_name')->orderBy('first_name')->get();

        return view('online-courses.create', compact('instructors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $data = $this->validatedCourseData($request, null);

        $course = OnlineCourse::query()->create($data);
        $this->persistUploadedOnlineCourseImage($request, $course);

        return redirect()->route('online-courses.index')->with('success', 'Kurs online został utworzony.');
    }

    public function show(OnlineCourse $online_course): View
    {
        $online_course->loadCount('enrollments');
        $online_course->load(['modules.lessons']);

        return view('online-courses.show', ['course' => $online_course]);
    }

    public function edit(OnlineCourse $online_course): View
    {
        $instructors = Instructor::query()->orderBy('last_name')->orderBy('first_name')->get();
        $online_course->load(['modules.lessons.embeds', 'modules.lessons.resourceLinks']);

        return view('online-courses.edit', ['course' => $online_course, 'instructors' => $instructors]);
    }

    public function update(Request $request, OnlineCourse $online_course): RedirectResponse
    {
        $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $data = $this->validatedCourseData($request, $online_course);

        if ($request->has('remove_image')) {
            $this->deleteOnlineCourseImageFile($online_course);
            $data['image'] = null;
        }

        if ($request->hasFile('image')) {
            $path = $this->saveOnlineCourseImageUpload($request, $online_course);
            if ($path !== null) {
                $data['image'] = $path;
            }
        }

        $online_course->update($data);

        return redirect()->route('online-courses.edit', $online_course)->with('success', 'Zmiany zapisano.');
    }

    public function destroy(OnlineCourse $online_course): RedirectResponse
    {
        $online_course->delete();

        return redirect()->route('online-courses.index')->with('success', 'Kurs został przeniesiony do kosza (soft delete).');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCourseData(Request $request, ?OnlineCourse $existing = null): array
    {
        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'offer_description_html' => ['nullable', 'string'],
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'is_active' => ['sometimes', 'boolean'],
            'visible_in_dashboard' => ['sometimes', 'boolean'],
            'internal_notes' => ['nullable', 'string'],
            'legacy_publigo_product_id' => ['nullable', 'string', 'max:190'],
        ]);

        $validated['slug'] = trim((string) ($validated['slug'] ?? ''));
        if ($validated['slug'] === '') {
            $validated['slug'] = OnlineCourse::generateUniqueSlug($validated['title']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
            if ($validated['slug'] === '') {
                $validated['slug'] = OnlineCourse::generateUniqueSlug($validated['title']);
            }
        }

        $slugQuery = OnlineCourse::query()->where('slug', $validated['slug']);
        if ($existing !== null) {
            $slugQuery->where('id', '!=', $existing->id);
        }
        if ($slugQuery->exists()) {
            $validated['slug'] = OnlineCourse::generateUniqueSlug($validated['slug'].'-'.$validated['title']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['visible_in_dashboard'] = $request->boolean('visible_in_dashboard');

        foreach (['description', 'offer_description_html', 'internal_notes'] as $nullable) {
            if (! array_key_exists($nullable, $validated)) {
                $validated[$nullable] = null;
            }
        }

        return $validated;
    }

    public function reorderModules(Request $request, OnlineCourse $online_course): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $expectedIds = $online_course->modules()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $order = array_values(array_unique(array_map('intval', $validated['order'])));

        if ($order === [] && $expectedIds === []) {
            return $this->reorderModulesResponse($request, $online_course, 'Brak modułów do uporządkowania.');
        }

        if (count($order) !== count($expectedIds) || array_diff($order, $expectedIds) !== []) {
            return $this->reorderModulesResponse($request, $online_course, 'Nieprawidłowa lista modułów kursu.', 422);
        }

        DB::transaction(function () use ($order, $online_course) {
            foreach ($order as $position => $moduleId) {
                $online_course->modules()->whereKey($moduleId)->update(['sort_order' => $position]);
            }
        });

        return $this->reorderModulesResponse($request, $online_course, 'Kolejność modułów zapisana.');
    }

    private function reorderModulesResponse(Request $request, OnlineCourse $online_course, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        $key = $status >= 400 ? 'error' : 'success';

        return redirect()->route('online-courses.edit', $online_course)->with($key, $message);
    }

    private function persistUploadedOnlineCourseImage(Request $request, OnlineCourse $course): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $path = $this->saveOnlineCourseImageUpload($request, $course);
        if ($path !== null) {
            $course->update(['image' => $path]);
        }
    }

    /**
     * Zapisuje plik z żądania i zwraca ścieżkę względną na dysku public; usuwa poprzedni plik kursu.
     */
    private function saveOnlineCourseImageUpload(Request $request, OnlineCourse $course): ?string
    {
        $file = $request->file('image');
        if (! $file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg';
        $randomSuffix = substr(md5(uniqid((string) mt_rand(), true)), 0, 6);
        $imageFileName = "online_course_{$course->id}_{$randomSuffix}.{$extension}";
        $imagePath = $file->storeAs('online-courses/images', $imageFileName, 'public');

        if (! $imagePath) {
            Log::error('Błąd zapisu grafiki kursu online', [
                'online_course_id' => $course->id,
                'filename' => $imageFileName,
            ]);

            return null;
        }

        if ($course->image && Storage::disk('public')->exists($course->image)) {
            Storage::disk('public')->delete($course->image);
        }

        return $imagePath;
    }

    private function deleteOnlineCourseImageFile(OnlineCourse $course): void
    {
        if ($course->image && Storage::disk('public')->exists($course->image)) {
            Storage::disk('public')->delete($course->image);
        }
    }
}

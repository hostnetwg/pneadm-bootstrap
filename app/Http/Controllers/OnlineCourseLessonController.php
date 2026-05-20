<?php

namespace App\Http\Controllers;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseLesson;
use App\Models\OnlineCourseLessonEmbed;
use App\Models\OnlineCourseLessonResourceLink;
use App\Models\OnlineCourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnlineCourseLessonController extends Controller
{
    public function create(OnlineCourse $online_course, OnlineCourseModule $module): View
    {
        abort_unless($module->online_course_id === $online_course->id, 404);

        return view('online-courses.lessons.create', compact('online_course', 'module'));
    }

    public function store(Request $request, OnlineCourse $online_course, OnlineCourseModule $module): RedirectResponse
    {
        abort_unless($module->online_course_id === $online_course->id, 404);

        $validated = $this->validatedLesson($request);

        $max = (int) $module->lessons()->max('sort_order');

        DB::transaction(function () use ($module, $validated, $max, $request) {
            $created = OnlineCourseLesson::query()->create([
                'online_course_module_id' => $module->id,
                'title' => $validated['title'],
                'body_html' => $validated['body_html'],
                'is_published' => $request->boolean('is_published'),
                'sort_order' => $max + 1,
            ]);
            $this->syncEmbedsAndLinks($created, $request);
        });

        return redirect()->route('online-courses.edit', $online_course)->with('success', 'Lekcja dodana.');
    }

    public function edit(OnlineCourse $online_course, OnlineCourseModule $module, OnlineCourseLesson $lesson): View
    {
        abort_unless($module->online_course_id === $online_course->id, 404);
        abort_unless($lesson->online_course_module_id === $module->id, 404);

        $lesson->load(['embeds', 'resourceLinks']);

        return view('online-courses.lessons.edit', compact('online_course', 'module', 'lesson'));
    }

    public function update(Request $request, OnlineCourse $online_course, OnlineCourseModule $module, OnlineCourseLesson $lesson): RedirectResponse
    {
        abort_unless($module->online_course_id === $online_course->id, 404);
        abort_unless($lesson->online_course_module_id === $module->id, 404);

        $validated = $this->validatedLesson($request);

        DB::transaction(function () use ($lesson, $validated, $request) {
            $lesson->update([
                'title' => $validated['title'],
                'body_html' => $validated['body_html'],
                'is_published' => $request->boolean('is_published'),
            ]);
            $lesson->embeds()->delete();
            $lesson->resourceLinks()->delete();
            $this->syncEmbedsAndLinks($lesson, $request);
        });

        return redirect()->route('online-courses.lessons.edit', [$online_course, $module, $lesson])->with('success', 'Lekcja zapisana.');
    }

    public function destroy(OnlineCourse $online_course, OnlineCourseModule $module, OnlineCourseLesson $lesson): RedirectResponse
    {
        abort_unless($module->online_course_id === $online_course->id, 404);
        abort_unless($lesson->online_course_module_id === $module->id, 404);
        $lesson->delete();

        return redirect()->route('online-courses.edit', $online_course)->with('success', 'Lekcja usunięta.');
    }

    /**
     * Kolejność lekcji w modułach oraz przenoszenie między modułami (JSON).
     *
     * @param  array{modules: array<int, array{id: int, lesson_ids: array<int, int>}>}  $validated
     */
    public function reorder(Request $request, OnlineCourse $online_course): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.id' => ['required', 'integer'],
            'modules.*.lesson_ids' => ['present', 'array'],
            'modules.*.lesson_ids.*' => ['integer'],
        ]);

        $courseModuleIds = $online_course->modules()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allLessonIds = OnlineCourseLesson::query()
            ->whereIn('online_course_module_id', $courseModuleIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $seenLessonIds = [];

        foreach ($validated['modules'] as $row) {
            $moduleId = (int) $row['id'];
            if (! in_array($moduleId, $courseModuleIds, true)) {
                return $this->reorderLessonsResponse($request, $online_course, 'Nieprawidłowy moduł w strukturze kursu.', 422);
            }

            foreach ($row['lesson_ids'] as $lessonId) {
                $lessonId = (int) $lessonId;
                if (! in_array($lessonId, $allLessonIds, true)) {
                    return $this->reorderLessonsResponse($request, $online_course, 'Nieprawidłowa lekcja w strukturze kursu.', 422);
                }
                if (in_array($lessonId, $seenLessonIds, true)) {
                    return $this->reorderLessonsResponse($request, $online_course, 'Ta sama lekcja występuje więcej niż raz.', 422);
                }
                $seenLessonIds[] = $lessonId;
            }
        }

        if (count($seenLessonIds) !== count($allLessonIds)) {
            return $this->reorderLessonsResponse($request, $online_course, 'Struktura musi zawierać wszystkie lekcje kursu.', 422);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['modules'] as $row) {
                $moduleId = (int) $row['id'];
                foreach ($row['lesson_ids'] as $position => $lessonId) {
                    OnlineCourseLesson::query()->whereKey((int) $lessonId)->update([
                        'online_course_module_id' => $moduleId,
                        'sort_order' => $position,
                    ]);
                }
            }
        });

        return $this->reorderLessonsResponse($request, $online_course, 'Kolejność lekcji zapisana.');
    }

    private function reorderLessonsResponse(Request $request, OnlineCourse $online_course, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        $key = $status >= 400 ? 'error' : 'success';

        return redirect()->route('online-courses.edit', $online_course)->with($key, $message);
    }

    /**
     * @return array{title:string,body_html:?string}
     */
    private function validatedLesson(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
        ]);
    }

    private function syncEmbedsAndLinks(OnlineCourseLesson $lesson, Request $request): void
    {
        $embeds = $request->input('embeds', []);
        if (! is_array($embeds)) {
            $embeds = [];
        }

        $order = 0;
        foreach ($embeds as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['video_url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $platform = $row['platform'] ?? 'vimeo';
            if (! in_array($platform, ['youtube', 'vimeo', 'other'], true)) {
                $platform = 'vimeo';
            }
            OnlineCourseLessonEmbed::query()->create([
                'online_course_lesson_id' => $lesson->id,
                'video_url' => $url,
                'platform' => $platform,
                'title' => isset($row['title']) ? trim((string) $row['title']) : null,
                'sort_order' => $order++,
            ]);
        }

        $links = $request->input('resource_links', []);
        if (! is_array($links)) {
            $links = [];
        }

        $order = 0;
        foreach ($links as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            OnlineCourseLessonResourceLink::query()->create([
                'online_course_lesson_id' => $lesson->id,
                'url' => $url,
                'title' => isset($row['title']) ? trim((string) $row['title']) : null,
                'sort_order' => $order++,
            ]);
        }
    }
}

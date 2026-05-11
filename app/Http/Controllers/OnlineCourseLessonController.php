<?php

namespace App\Http\Controllers;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseLesson;
use App\Models\OnlineCourseLessonEmbed;
use App\Models\OnlineCourseLessonResourceLink;
use App\Models\OnlineCourseModule;
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
            $lesson = OnlineCourseLesson::query()->create([
                'online_course_module_id' => $module->id,
                'title' => $validated['title'],
                'body_html' => $validated['body_html'],
                'is_published' => $request->boolean('is_published'),
                'sort_order' => $max + 1,
            ]);
            $this->syncEmbedsAndLinks($lesson, $request);
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

<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseOnlineDetails;
use App\Services\CourseLiveResourceBarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseLiveController extends Controller
{
    public function __construct(
        private readonly CourseLiveResourceBarService $resourceBar,
    ) {}

    public function show(Request $request, int $id): View|JsonResponse
    {
        $course = $this->findCourse($id);
        $state = $this->resourceBar->panelState($course);

        if ($request->wantsJson()) {
            return response()->json($state);
        }

        return view('courses.live', [
            'course' => $course,
            'state' => $state,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = max(1, min((int) $request->input('limit', 30), 100));
        $includeArchived = filter_var($request->input('include_archived', false), FILTER_VALIDATE_BOOLEAN);
        $applyArchivedFilter = ! $includeArchived && $q === '';
        $now = now();

        $excludeId = (int) $request->input('exclude_id', 0);

        $query = Course::query()
            ->with('instructor:id,title,first_name,last_name')
            ->select('id', 'id_old', 'title', 'start_date', 'end_date', 'instructor_id');

        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        if ($q !== '') {
            $query->whereMatchesAdminSelectSearch($q);
        }

        if ($applyArchivedFilter) {
            $query->where(function ($w) use ($now) {
                $w->where('start_date', '>=', $now)
                    ->orWhere(function ($w2) use ($now) {
                        $w2->whereNotNull('end_date')
                            ->where('start_date', '<=', $now)
                            ->where('end_date', '>=', $now);
                    });
            });
        }

        $today = $now->format('Y-m-d');
        $courses = $query
            ->orderByRaw('start_date IS NULL')
            ->orderByRaw('CASE WHEN start_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderByRaw('CASE WHEN start_date >= ? THEN start_date END ASC', [$today])
            ->orderByRaw('CASE WHEN start_date <  ? THEN start_date END DESC', [$today])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $tz = (string) config('app.timezone');

        return response()->json([
            'items' => $courses->map(function (Course $course) use ($tz) {
                return [
                    'value' => (string) $course->id,
                    'id' => (int) $course->id,
                    'id_hash' => '#'.$course->id,
                    'id_old' => (string) ($course->id_old ?? ''),
                    'title_text' => $course->plainTitle(''),
                    'title_html' => (string) $course->title,
                    'start_date' => $course->start_date ? $course->start_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
                    'end_date' => $course->end_date ? $course->end_date->copy()->timezone($tz)->format('Y-m-d H:i') : null,
                    'status' => $course->getLifecycleStatus(),
                    'instructor' => $course->instructor
                        ? trim(($course->instructor->title ? $course->instructor->title.' ' : '').$course->instructor->first_name.' '.$course->instructor->last_name)
                        : '',
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $course = $this->findCourse($id);
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails) {
            $message = 'To szkolenie nie ma jeszcze danych online. Ustaw ClickMeeting / osadzony pokój w edycji szkolenia.';

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $message], 422);
            }

            return redirect()
                ->route('courses.edit', $course->id)
                ->with('error', $message);
        }

        $validated = $request->validate([
            'live_bar_attendance_enabled' => ['required', 'boolean'],
            'live_bar_certificate_enabled' => ['required', 'boolean'],
            'live_bar_materials_enabled' => ['required', 'boolean'],
            'live_bar_survey_enabled' => ['required', 'boolean'],
        ]);

        $ready = $this->resourceBar->panelState($course)['resources'];
        $validated['live_bar_attendance_enabled'] = $validated['live_bar_attendance_enabled']
            && (bool) ($ready['attendance']['ready'] ?? false);
        $validated['live_bar_certificate_enabled'] = $validated['live_bar_certificate_enabled']
            && (bool) ($ready['certificate']['ready'] ?? false);
        $validated['live_bar_materials_enabled'] = $validated['live_bar_materials_enabled']
            && (bool) ($ready['materials']['ready'] ?? false);
        $validated['live_bar_survey_enabled'] = $validated['live_bar_survey_enabled']
            && (bool) ($ready['survey']['ready'] ?? false);

        $details->fill($validated);
        $details->save();

        $course->unsetRelation('onlineDetails');
        $state = $this->resourceBar->panelState($course->fresh(['onlineDetails', 'fileLinks', 'surveyLinks']));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'state' => $state,
            ]);
        }

        return redirect()
            ->route('courses.live', $course->id)
            ->with('success', 'Belka na transmisji zapisana.');
    }

    public function updateOffer(Request $request, int $id): JsonResponse
    {
        $course = $this->findCourse($id);
        $details = $course->onlineDetails;
        if (! $details instanceof CourseOnlineDetails) {
            return response()->json([
                'ok' => false,
                'error' => 'To szkolenie nie ma jeszcze danych online. Ustaw ClickMeeting / osadzony pokój w edycji szkolenia.',
            ], 422);
        }

        $validated = $request->validate([
            'live_offer_course_id' => [
                'nullable',
                'integer',
                'exists:courses,id',
                Rule::notIn([(int) $course->id]),
            ],
            'live_offer_enabled' => ['required', 'boolean'],
        ]);

        $offerCourseId = isset($validated['live_offer_course_id'])
            ? (int) $validated['live_offer_course_id']
            : null;
        if ($offerCourseId === 0) {
            $offerCourseId = null;
        }

        $enabled = (bool) $validated['live_offer_enabled'];
        if ($enabled && $offerCourseId === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Najpierw wybierz szkolenie, które chcesz pokazać uczestnikom.',
            ], 422);
        }

        $details->live_offer_course_id = $offerCourseId;
        $details->live_offer_enabled = $enabled && $offerCourseId !== null;
        $details->save();

        $course->unsetRelation('onlineDetails');

        return response()->json([
            'ok' => true,
            'state' => $this->resourceBar->panelState($course->fresh(['onlineDetails', 'fileLinks', 'surveyLinks'])),
        ]);
    }

    private function findCourse(int $id): Course
    {
        return Course::query()
            ->with(['onlineDetails', 'fileLinks', 'surveyLinks'])
            ->findOrFail($id);
    }
}

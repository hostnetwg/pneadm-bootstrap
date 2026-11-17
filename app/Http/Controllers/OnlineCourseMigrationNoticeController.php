<?php

namespace App\Http\Controllers;

use App\Services\Mail\SystemMailDiagnostics;
use App\Services\OnlineCourseMigrationNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnlineCourseMigrationNoticeController extends Controller
{
    public function index(Request $request, OnlineCourseMigrationNoticeService $notices): View
    {
        $filters = $this->filters($request);
        $people = $notices->people($filters['course_id'], $filters['q'], $filters['direction']);
        $rows = collect($people->items());

        return view('online-courses.migration-notice.index', [
            'people' => $people,
            'filters' => $filters,
            'courses' => $notices->filterCourses(),
            'sendStats' => $notices->sendStatsFor($rows),
            'accounts' => $notices->accountsFor($rows->pluck('email')),
            'sampleWithoutAccount' => $notices->samplePreview(false),
            'sampleWithAccount' => $notices->samplePreview(true),
            'unsentCount' => $notices->unsentCount($filters['course_id'], $filters['q']),
            'mailSystemConfig' => SystemMailDiagnostics::currentConfig(),
        ]);
    }

    public function preview(Request $request, OnlineCourseMigrationNoticeService $notices): JsonResponse
    {
        $this->releaseSessionLock($request);
        $result = $notices->previewForEmail((string) $request->query('email', ''));
        $http = (int) ($result['http_code'] ?? 500);
        unset($result['http_code']);

        return response()->json($result, $http);
    }

    public function unsentCount(Request $request, OnlineCourseMigrationNoticeService $notices): JsonResponse
    {
        $this->releaseSessionLock($request);
        $filters = $this->filters($request);

        return response()->json([
            'unsent_count' => $notices->unsentCount($filters['course_id'], $filters['q']),
        ]);
    }

    public function send(Request $request, OnlineCourseMigrationNoticeService $notices): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        try {
            $result = $notices->sendToEmail($data['email'], Auth::id());
        } catch (\Throwable $e) {
            return $this->back($request)->with('error', 'Nie udało się wysłać e-maila: '.$e->getMessage());
        }

        if (! ($result['success'] ?? false)) {
            return $this->back($request)->with('error', $result['error'] ?? 'Nie udało się wysłać e-maila.');
        }

        return $this->back($request)->with(
            'success',
            'E-mail o przeniesieniu kursów został wysłany na adres '.$result['email'].'.'
        );
    }

    public function sendBulk(Request $request, OnlineCourseMigrationNoticeService $notices): RedirectResponse
    {
        $filters = $this->filters($request);
        $result = $notices->sendBulk($filters['course_id'], $filters['q'], Auth::id());

        if (! ($result['success'] ?? false)) {
            return $this->back($request)->with('info', $result['error'] ?? 'Brak osób do wysyłki.');
        }

        return $this->back($request)->with(
            'success',
            'Zlecono wysyłkę '.$result['queued'].' e-maili o przeniesieniu kursów na pnedu.pl. Wysyłka odbywa się w tle.'
        );
    }

    public function batchStatus(Request $request, OnlineCourseMigrationNoticeService $notices): JsonResponse
    {
        $this->releaseSessionLock($request);

        return response()->json($notices->batchStatus());
    }

    public function cancelBatch(OnlineCourseMigrationNoticeService $notices): JsonResponse
    {
        $result = $notices->cancelBatch();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 404);
    }

    /**
     * @return array{q: string, course_id: ?int, direction: string}
     */
    private function filters(Request $request): array
    {
        $courseId = $request->input('course_id', $request->query('course_id'));
        $courseId = is_numeric($courseId) ? (int) $courseId : null;

        return [
            'q' => trim((string) $request->input('q', $request->query('q', ''))),
            'course_id' => $courseId > 0 ? $courseId : null,
            'direction' => $request->input('direction', $request->query('direction')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function back(Request $request): RedirectResponse
    {
        return redirect()->route('online-courses.migration-notice.index', $this->filters($request));
    }

    private function releaseSessionLock(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->save();
        }
    }
}

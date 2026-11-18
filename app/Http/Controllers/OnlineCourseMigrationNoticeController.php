<?php

namespace App\Http\Controllers;

use App\Services\Mail\SystemMailDiagnostics;
use App\Services\OnlineCourseMigrationNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnlineCourseMigrationNoticeController extends Controller
{
    public function index(Request $request, OnlineCourseMigrationNoticeService $notices): View
    {
        $filters = $this->filters($request);
        $people = $notices->people($filters['course_id'], $filters['q'], $filters['account'], $filters['direction'], $filters['course_count'], $filters['sent']);
        $rows = collect($people->items());

        return view('online-courses.migration-notice.index', [
            'people' => $people,
            'filters' => $filters,
            'courses' => $notices->filterCourses(),
            'sendStats' => $notices->sendStatsFor($rows),
            'accounts' => $notices->accountsFor($rows->pluck('email')),
            'sampleWithoutAccount' => $notices->samplePreview(false),
            'sampleWithAccount' => $notices->samplePreview(true),
            'unsentCount' => $notices->unsentCount($filters['course_id'], $filters['q'], $filters['account'], $filters['course_count'], $filters['sent']),
            'batch' => $notices->batchStatus(),
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
            'unsent_count' => $notices->unsentCount($filters['course_id'], $filters['q'], $filters['account'], $filters['course_count'], $filters['sent']),
            'resend' => $filters['sent'] === 'yes',
            'sent' => $filters['sent'],
        ]);
    }

    public function send(Request $request, OnlineCourseMigrationNoticeService $notices): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        try {
            $result = $notices->sendToEmail($data['email'], Auth::id());
        } catch (\Throwable $e) {
            $message = 'Nie udało się wysłać e-maila: '.$e->getMessage();

            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => $message], 422)
                : $this->back($request)->with('error', $message);
        }

        if (! ($result['success'] ?? false)) {
            $message = $result['error'] ?? 'Nie udało się wysłać e-maila.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => $message], 422)
                : $this->back($request)->with('error', $message);
        }

        if ($request->expectsJson()) {
            $filters = $this->filters($request);
            $email = (string) $result['email'];
            $stats = $notices->sendStatsFor(Collection::make([(object) ['email' => $email]]));
            $stat = $stats[$email] ?? null;
            $sentCount = (int) ($stat['sent_count'] ?? 0);
            $sentLabel = ($stat['last_sent_at'] ?? null)
                ? $stat['last_sent_at']->timezone('Europe/Warsaw')->format('d.m.Y H:i').' ('.$sentCount.')'
                : null;

            return response()->json([
                'success' => true,
                'email' => $email,
                'sent_count' => $sentCount,
                'sent_label' => $sentLabel,
                'unsent_count' => $notices->unsentCount($filters['course_id'], $filters['q'], $filters['account'], $filters['course_count'], $filters['sent']),
            ]);
        }

        return $this->back($request)->with(
            'success',
            'E-mail o przeniesieniu kursów został wysłany na adres '.$result['email'].'.'
        );
    }

    public function sendBulk(Request $request, OnlineCourseMigrationNoticeService $notices): RedirectResponse|JsonResponse
    {
        $filters = $this->filters($request);
        try {
            $result = $notices->sendBulk($filters['course_id'], $filters['q'], $filters['account'], Auth::id(), $filters['course_count'], $filters['sent']);
        } catch (\Throwable $e) {
            report($e);
            $result = [
                'success' => false,
                'error' => 'Nie udało się zlecić wysyłki. Odśwież stronę i spróbuj ponownie.',
                'queued' => 0,
            ];
        }

        if ($request->expectsJson()) {
            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        }

        if (! ($result['success'] ?? false)) {
            return $this->back($request)->with('info', $result['error'] ?? 'Brak osób do wysyłki.');
        }

        return $this->back($request)->with(
            'success',
            'Zlecono wysyłkę '.$result['queued'].' e-maili o przeniesieniu kursów na pnedu.pl. Postęp jest na tej stronie.'
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
     * @return array{q: string, course_id: ?int, account: string, direction: string, course_count: ?int, sent: string}
     */
    private function filters(Request $request): array
    {
        $courseId = $request->input('course_id', $request->query('course_id'));
        $courseId = is_numeric($courseId) ? (int) $courseId : null;
        $account = (string) $request->input('account', $request->query('account', 'all'));
        $sent = (string) $request->input('sent', $request->query('sent', 'all'));
        $courseCount = $request->input('course_count', $request->query('course_count'));
        $courseCount = is_numeric($courseCount) ? (int) $courseCount : null;

        return [
            'q' => trim((string) $request->input('q', $request->query('q', ''))),
            'course_id' => $courseId > 0 ? $courseId : null,
            'account' => in_array($account, ['yes', 'no'], true) ? $account : 'all',
            'sent' => in_array($sent, ['yes', 'no'], true) ? $sent : 'all',
            'direction' => $request->input('direction', $request->query('direction')) === 'asc' ? 'asc' : 'desc',
            'course_count' => ($courseCount !== null && $courseCount >= 1 && $courseCount <= 99) ? $courseCount : null,
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

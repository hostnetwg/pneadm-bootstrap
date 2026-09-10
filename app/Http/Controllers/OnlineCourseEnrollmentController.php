<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use App\Services\Certificate\CertificateGeneratorService;
use App\Services\Certificate\OnlineCourseCertificateIssueService;
use App\Services\Mail\SystemMailDiagnostics;
use App\Services\OnlineCourseEnrollmentListQuery;
use App\Services\OnlineCourseEnrollmentPlatformMigrationMailService;
use App\Services\OnlineCourseEnrollmentPneduAccountLookup;
use App\Services\OnlineCourseEnrollmentPubligoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlineCourseEnrollmentController extends Controller
{
    public function index(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseEnrollmentListQuery $listQuery,
        OnlineCourseEnrollmentPneduAccountLookup $pneduAccountLookup,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): View {
        $filters = $this->listFilters($request);
        $enrollments = $listQuery->paginate($online_course, $filters);
        $pneduUsersByEmail = $pneduAccountLookup->byEmails($enrollments->getCollection()->pluck('email'));
        $accessSources = $online_course->enrollments()
            ->whereNotNull('access_source')
            ->where('access_source', '!=', '')
            ->distinct()
            ->orderBy('access_source')
            ->pluck('access_source');
        $filtersActive = $filters['q'] !== ''
            || $filters['access'] !== 'all'
            || $filters['pnedu'] !== 'all'
            || $filters['source'] !== 'all'
            || $filters['certificate'] !== 'all'
            || $filters['mail'] !== 'all';
        $migrationMailStats = $migrationMail->courseStats($online_course);
        $migrationMailStatusByEnrollmentId = $migrationMail->statusByEnrollmentIds(
            $online_course,
            $enrollments->getCollection()->pluck('id')
        );
        $mailSystemConfig = SystemMailDiagnostics::currentConfig();

        return view('online-courses.enrollments.index', compact(
            'online_course',
            'enrollments',
            'pneduUsersByEmail',
            'filters',
            'accessSources',
            'filtersActive',
            'migrationMailStats',
            'migrationMailStatusByEnrollmentId',
            'mailSystemConfig'
        ));
    }

    public function create(OnlineCourse $online_course): View
    {
        return view('online-courses.enrollments.create', compact('online_course'));
    }

    public function store(Request $request, OnlineCourse $online_course): RedirectResponse
    {
        $data = $this->validated($request);

        OnlineCourseEnrollment::query()->updateOrCreate(
            [
                'online_course_id' => $online_course->id,
                'email' => $data['email'],
            ],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'access_expires_at' => $data['access_expires_at'],
                'access_source' => $data['access_source'],
                'legacy_publigo_user_id' => $data['legacy_publigo_user_id'],
                'notes' => $data['notes'],
            ]
        );

        return redirect()->route('online-courses.enrollments.index', $online_course)->with('success', 'Przypisanie dostępu zapisane (dodano lub zaktualizowano).');
    }

    public function edit(OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): View
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        return view('online-courses.enrollments.edit', compact('online_course', 'enrollment'));
    }

    public function update(Request $request, OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        $data = $this->validated($request);

        if ($data['email'] !== $enrollment->email
            && OnlineCourseEnrollment::query()
                ->where('online_course_id', $online_course->id)
                ->where('email', $data['email'])
                ->exists()
        ) {
            return redirect()->back()->withInput()->withErrors(['email' => 'Ten adres jest już przypisany do tego kursu.']);
        }

        $enrollment->update($data);

        return redirect()->route('online-courses.enrollments.index', $online_course)->with('success', 'Przypisanie zaktualizowane.');
    }

    public function destroy(OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);
        $enrollment->delete();

        return redirect()->route('online-courses.enrollments.index', $online_course)->with('success', 'Dostęp został usunięty.');
    }

    public function import(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseEnrollmentPubligoImportService $importService
    ): RedirectResponse {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'skip_expired' => ['sometimes', 'boolean'],
        ]);

        $skipExpired = $request->boolean('skip_expired');

        try {
            $result = $importService->importUploadedFile(
                $online_course,
                $request->file('csv_file'),
                $skipExpired
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('error', 'Błąd podczas importu: '.$e->getMessage());
        }

        $flashKey = $result['imported'] > 0 ? 'success' : 'info';

        return redirect()
            ->route('online-courses.enrollments.index', $online_course)
            ->with($flashKey, $importService->flashMessage($result));
    }

    public function previewPlatformMigrationEmail(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseEnrollment $enrollment,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): JsonResponse {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);
        $this->releaseSessionLock($request);

        $result = $migrationMail->previewForEnrollment($online_course, $enrollment);
        $http = (int) ($result['http_code'] ?? 500);
        unset($result['http_code']);

        return response()->json($result, $http);
    }

    public function previewPlatformMigrationEmailsBulk(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): JsonResponse {
        $this->releaseSessionLock($request);
        $mode = (string) $request->query('mode', 'unsent');
        if (! in_array($mode, ['unsent', 'resend_all'], true)) {
            $mode = 'unsent';
        }

        $result = $migrationMail->previewBulk($online_course, $mode);
        $http = (int) ($result['http_code'] ?? 500);
        unset($result['http_code']);

        return response()->json($result, $http);
    }

    public function sendPlatformMigrationEmail(
        OnlineCourse $online_course,
        OnlineCourseEnrollment $enrollment,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): RedirectResponse {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        try {
            $result = $migrationMail->sendToEnrollment($online_course, $enrollment, Auth::id(), true);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Nie udało się wysłać e-maila: '.$e->getMessage());
        }

        if (! ($result['success'] ?? false)) {
            return redirect()->back()->with('error', $result['error'] ?? 'Nie udało się wysłać e-maila.');
        }

        return redirect()->back()->with(
            'success',
            'E-mail o przeniesieniu kursu na pnedu.pl został wysłany na adres '.$result['email'].'.'
        );
    }

    public function sendPlatformMigrationEmailsBulk(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): RedirectResponse {
        $request->validate([
            'mode' => ['required', 'string', 'in:unsent,resend_all'],
        ]);

        $result = $migrationMail->sendBulk($online_course, $request->string('mode')->toString(), Auth::id());
        if (! ($result['success'] ?? false)) {
            return redirect()->back()->with('info', $result['error'] ?? 'Brak osób do wysyłki.');
        }

        return redirect()->back()->with(
            'success',
            'Zlecono wysyłkę '.$result['queued'].' e-maili o przeniesieniu kursu na pnedu.pl. Wysyłka odbywa się w tle — pasek postępu pokazuje aktualny stan.'
        );
    }

    public function platformMigrationEmailBatchStatus(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): JsonResponse {
        $this->releaseSessionLock($request);

        return response()->json($migrationMail->batchStatus($online_course));
    }

    public function cancelPlatformMigrationEmailBatch(
        OnlineCourse $online_course,
        OnlineCourseEnrollmentPlatformMigrationMailService $migrationMail
    ): JsonResponse {
        $result = $migrationMail->cancelBatch($online_course);
        $status = ($result['success'] ?? false) ? 200 : 404;

        return response()->json($result, $status);
    }

    public function storeCertificate(OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        try {
            $certificate = app(OnlineCourseCertificateIssueService::class)->issueForAdmin($enrollment);

            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('success', "Certyfikat nr {$certificate->certificate_number} został zapisany.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('error', $e->getMessage());
        }
    }

    public function generateCertificate(OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): RedirectResponse|BinaryFileResponse
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        $certificate = Certificate::query()
            ->where('online_course_enrollment_id', $enrollment->id)
            ->first();

        if (! $certificate) {
            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('error', 'Certyfikat dla tego dostępu nie został znaleziony. Najpierw kliknij „Generuj”.');
        }

        try {
            app(CertificateGeneratorService::class)->generatePdfForEnrollment($enrollment->id, [
                'save_to_storage' => true,
                'cache' => false,
            ]);
        } catch (\Throwable $e) {
            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('error', 'Błąd generowania PDF: '.$e->getMessage());
        }

        $certificate->refresh();
        if (empty($certificate->file_path)) {
            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('error', 'Nie udało się zapisać pliku PDF na serwerze.');
        }

        $relativePath = Str::replaceFirst('storage/', '', $certificate->file_path);
        if (! Storage::disk('public')->exists($relativePath)) {
            return redirect()
                ->route('online-courses.enrollments.index', $online_course)
                ->with('error', 'Plik PDF nie istnieje na serwerze.');
        }

        $downloadFileName = 'zaswiadczenie_'.str_replace('/', '-', $certificate->certificate_number).'.pdf';

        return response()->download(storage_path('app/public/'.$relativePath), $downloadFileName);
    }

    /**
     * @return array{email:string,first_name:?string,last_name:?string,phone:?string,access_expires_at:?\Carbon\Carbon,access_source:string,legacy_publigo_user_id:?string,notes:?string}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns'],
            'first_name' => ['required', 'string', 'max:190'],
            'last_name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'access_expires_at' => ['nullable', 'date'],
            'access_source' => ['nullable', 'string', 'max:190'],
            'legacy_publigo_user_id' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['email'] = OnlineCourseEnrollment::normalizeEmail($validated['email']) ?? '';
        $validated['access_source'] = trim((string) ($validated['access_source'] ?? '')) ?: 'manual';
        $validated['access_expires_at'] = $validated['access_expires_at'] ?? null;
        $phone = trim((string) ($validated['phone'] ?? ''));
        $legacyId = trim((string) ($validated['legacy_publigo_user_id'] ?? ''));

        return [
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $phone === '' ? null : $phone,
            'access_expires_at' => $validated['access_expires_at'],
            'access_source' => $validated['access_source'],
            'legacy_publigo_user_id' => $legacyId === '' ? null : $legacyId,
            'notes' => $validated['notes'],
        ];
    }

    /**
     * @return array{q:string,access:string,pnedu:string,source:string,certificate:string,mail:string,sort:string,dir:string}
     */
    private function listFilters(Request $request): array
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) > 190) {
            $q = mb_substr($q, 0, 190);
        }

        $access = (string) $request->input('access', 'all');
        if (! in_array($access, ['all', 'unlimited', 'active', 'expired'], true)) {
            $access = 'all';
        }

        $pnedu = (string) $request->input('pnedu', 'all');
        if (! in_array($pnedu, ['all', 'yes', 'no'], true)) {
            $pnedu = 'all';
        }

        $source = trim((string) $request->input('source', ''));
        if (mb_strlen($source) > 190) {
            $source = mb_substr($source, 0, 190);
        }
        if ($source === '') {
            $source = 'all';
        }

        $certificate = (string) $request->input('certificate', 'all');
        if (! in_array($certificate, ['all', 'yes', 'no'], true)) {
            $certificate = 'all';
        }

        $mail = (string) $request->input('mail', 'all');
        if (! in_array($mail, ['all', 'sent', 'unsent'], true)) {
            $mail = 'all';
        }

        $sort = (string) $request->input('sort', 'email');
        if (! in_array($sort, OnlineCourseEnrollmentListQuery::SORTS, true)) {
            $sort = 'email';
        }

        $dir = (string) $request->input('dir', 'asc');

        return [
            'q' => $q,
            'access' => $access,
            'pnedu' => $pnedu,
            'source' => $source,
            'certificate' => $certificate,
            'mail' => $mail,
            'sort' => $sort,
            'dir' => $dir === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function releaseSessionLock(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->save();
        }
    }
}

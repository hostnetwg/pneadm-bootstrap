<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Participant;
use App\Models\PneduUser;
use App\Services\Admin\PneduUserAdminService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PneduUsersController extends Controller
{
    private const PER_PAGE = 25;

    /** @var list<string> */
    private const SORT_COLUMNS = [
        'id',
        'email',
        'created_at',
        'first_name',
        'last_name',
        'birth_date',
        'email_verified_at',
        'email_undeliverable_at',
    ];

    public function __construct(
        private readonly PneduUserAdminService $pneduUserAdmin,
    ) {}

    public function index(Request $request): View
    {
        if (! auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        $this->normalizeListQuery($request);

        $filters = $this->validatedFilters($request);

        session(['pnedu_users_list_query' => $request->query()]);

        $query = PneduUser::query();
        if ($filters['trashed'] === 'with') {
            $query->withTrashed();
        } elseif ($filters['trashed'] === 'only') {
            $query->onlyTrashed();
        }
        $this->pneduUserAdmin->applyListFilters($query, $filters);

        $sort = $filters['sort'];
        $dir = $filters['dir'];
        $query->orderBy($sort, $dir);

        if ($sort !== 'id') {
            $query->orderBy('id', $dir);
        }

        $users = $query->paginate(self::PER_PAGE)->withQueryString();
        $paidEnrollmentEmails = $this->pneduUserAdmin->paidEnrollmentEmailSetForEmails($users->getCollection()->pluck('email'));

        return view('admin.pnedu-users.index', [
            'users' => $users,
            'filters' => $filters,
            'stats' => $this->pneduUserAdmin->listStats(),
            'paidEnrollmentEmails' => array_fill_keys($paidEnrollmentEmails, true),
            'adminService' => $this->pneduUserAdmin,
            'deliverabilityAvailable' => $this->pneduUserAdmin->deliverabilityColumnsAvailable(),
        ]);
    }

    public function show(PneduUser $pnedu_user): View
    {
        if (! auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        $participations = $this->participationsForPneduEmail($pnedu_user->email);
        [$participationsPaidCount, $participationsFreeCount] = $this->participationPaidFreeCounts($participations);
        $hasPaidEnrollment = $this->pneduUserAdmin->userHasPaidCourseEnrollment($pnedu_user);
        $relatedFormOrders = $this->pneduUserAdmin->relatedFormOrdersForEmail($pnedu_user->email);

        return view('admin.pnedu-users.show', [
            'user' => $pnedu_user,
            'participations' => $participations,
            'participationsPaidCount' => $participationsPaidCount,
            'participationsFreeCount' => $participationsFreeCount,
            'hasPaidEnrollment' => $hasPaidEnrollment,
            'relatedFormOrders' => $relatedFormOrders,
            'adminService' => $this->pneduUserAdmin,
        ]);
    }

    public function sendVerificationEmail(PneduUser $pnedu_user): RedirectResponse
    {
        $this->authorizePneduUserManage();

        if ($pnedu_user->hasVerifiedEmail()) {
            return back()->with('error', 'Ten adres e-mail jest już zweryfikowany.');
        }

        if ($pnedu_user->hasUndeliverableEmail()) {
            return back()->with('error', 'Adres ma aktywną flagę niedostarczalności (bounce). Najpierw poproś użytkownika o poprawę e-mail w profilu lub wyczyść flagę po ustaleniach.');
        }

        $pnedu_user->sendEmailVerificationNotification();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: wysłano link weryfikacji e-mail (admin)',
            'Administracyjnie wysłano wiadomość weryfikacyjną na adres '.$pnedu_user->email.' (ID użytkownika: '.$pnedu_user->id.').',
            [
                'new_values' => [
                    'pnedu_user_id' => $pnedu_user->id,
                    'email' => $pnedu_user->email,
                ],
            ]
        );

        return back()->with('success', 'Wysłano wiadomość weryfikacyjną na adres '.$pnedu_user->email.'.');
    }

    public function clearUndeliverableFlag(Request $request, PneduUser $pnedu_user): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $request->validate([
            'confirm_clear_undeliverable' => ['accepted'],
        ], [
            'confirm_clear_undeliverable.accepted' => 'Zaznacz potwierdzenie wyczyszczenia flagi niedostarczalności.',
        ]);

        if (! $pnedu_user->hasUndeliverableEmail()) {
            return back()->with('error', 'To konto nie ma aktywnej flagi niedostarczalności e-mail.');
        }

        $previousReason = $pnedu_user->email_undeliverable_reason;
        $pnedu_user->clearEmailDeliverabilityFlags();
        $pnedu_user->save();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: wyczyszczono flagę niedostarczalności e-mail',
            'Administracyjnie wyczyszczono flagę niedostarczalności dla użytkownika ID '.$pnedu_user->id.' ('.$pnedu_user->email.'), poprzedni powód: '.($previousReason ?: 'brak').'.',
            [
                'new_values' => [
                    'pnedu_user_id' => $pnedu_user->id,
                    'email' => $pnedu_user->email,
                ],
            ]
        );

        return back()->with('success', 'Flaga niedostarczalności e-mail została wyczyszczona.');
    }

    public function sendPasswordReset(PneduUser $pnedu_user): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $status = Password::broker('pnedu_users')->sendResetLink(
            ['email' => $pnedu_user->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            ActivityLog::logCustom(
                'Użytkownik pnedu.pl: wysłano reset hasła (e-mail)',
                'Wysłano wiadomość z linkiem resetu hasła na adres '.$pnedu_user->email.' (ID użytkownika: '.$pnedu_user->id.').',
                [
                    'new_values' => [
                        'pnedu_user_id' => $pnedu_user->id,
                        'email' => $pnedu_user->email,
                    ],
                ]
            );

            return back()->with('success', 'Wysłano e-mail z linkiem resetu hasła (pnedu.pl).');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('error', 'Zbyt wiele prób. Odczekaj chwilę przed ponownym wysłaniem linku resetu.');
        }

        if ($status === Password::INVALID_USER) {
            return back()->with('error', 'Nie znaleziono użytkownika o tym adresie e-mail w bazie pnedu.');
        }

        return back()->with('error', 'Nie udało się wysłać linku resetu hasła. Spróbuj ponownie.');
    }

    public function setPassword(Request $request, PneduUser $pnedu_user): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $validated = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $pnedu_user->password = $validated['password'];
        $pnedu_user->save();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: ustawienie hasła z panelu',
            'Hasło zostało ustawione administracyjnie dla użytkownika ID '.$pnedu_user->id.' ('.$pnedu_user->email.').',
            [
                'new_values' => [
                    'pnedu_user_id' => $pnedu_user->id,
                    'email' => $pnedu_user->email,
                ],
            ]
        );

        return back()->with('success', 'Nowe hasło zostało zapisane.');
    }

    /**
     * Wygeneruj nowe hasło dla użytkownika pnedu.pl (do wklejenia w szablonie wiadomości z danymi dostępowymi).
     */
    public function generateAccessCredentialsPassword(PneduUser $pnedu_user): JsonResponse
    {
        $this->authorizePneduUserManage();

        $plain = Str::password(20);

        $pnedu_user->forceFill([
            'password' => $plain,
        ])->save();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: wygenerowano hasło (szablon wiadomości dostępowej)',
            'Wygenerowano i zapisano nowe hasło dla użytkownika ID '.$pnedu_user->id.' ('.$pnedu_user->email.') — treść przekazana w odpowiedzi JSON do panelu (log nie zawiera hasła).',
            [
                'new_values' => [
                    'pnedu_user_id' => $pnedu_user->id,
                    'email' => $pnedu_user->email,
                ],
            ]
        );

        return response()->json(['password' => $plain]);
    }

    public function verifyEmail(Request $request, PneduUser $pnedu_user): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $request->validate([
            'confirm_verify' => ['accepted'],
        ], [
            'confirm_verify.accepted' => 'Zaznacz potwierdzenie, aby ręcznie zweryfikować adres e-mail.',
        ]);

        if ($pnedu_user->email_verified_at !== null) {
            return back()->with('error', 'Ten adres e-mail jest już zweryfikowany.');
        }

        $pnedu_user->forceFill(['email_verified_at' => now()])->save();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: ręczna weryfikacja e-maila',
            'Ręcznie zweryfikowano e-mail użytkownika ID '.$pnedu_user->id.' ('.$pnedu_user->email.').',
            [
                'new_values' => [
                    'pnedu_user_id' => $pnedu_user->id,
                    'email' => $pnedu_user->email,
                    'email_verified_at' => $pnedu_user->email_verified_at?->toIso8601String(),
                ],
            ]
        );

        return back()->with('success', 'Adres e-mail został oznaczony jako zweryfikowany.');
    }

    public function destroy(Request $request, PneduUser $pnedu_user): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $request->validate([
            'confirm_delete' => ['accepted'],
        ], [
            'confirm_delete.accepted' => 'Zaznacz potwierdzenie, aby usunąć konto.',
        ]);

        if ($pnedu_user->trashed()) {
            return redirect()
                ->route('admin.pnedu-users.index', session('pnedu_users_list_query', []))
                ->with('error', 'To konto jest już usunięte (soft delete).');
        }

        $userId = $pnedu_user->id;
        $userEmail = $pnedu_user->email;

        $pnedu_user->remember_token = null;
        $pnedu_user->save();
        $pnedu_user->delete();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: usunięcie konta (soft delete)',
            'Zdezaktywowano konto użytkownika ID '.$userId.' ('.$userEmail.') — ustawiono deleted_at w bazie pnedu.',
            [
                'new_values' => [
                    'pnedu_user_id' => $userId,
                    'email' => $userEmail,
                ],
            ]
        );

        return redirect()
            ->route('admin.pnedu-users.index', session('pnedu_users_list_query', []))
            ->with('success', 'Konto pnedu.pl ('.$userEmail.') zostało usunięte (soft delete). Adres e-mail może zostać użyty do ponownej rejestracji.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $user = PneduUser::withTrashed()->findOrFail($id);
        if (! $user->trashed()) {
            return back()->with('error', 'To konto nie jest usunięte.');
        }

        $email = $user->email;
        try {
            $user->restore();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return back()->with('error', 'Nie można przywrócić konta, bo istnieje już aktywne konto z adresem '.$email.'.');
            }

            throw $e;
        }

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: przywrócenie konta',
            'Przywrócono konto użytkownika ID '.$user->id.' ('.$email.').',
            [
                'new_values' => [
                    'pnedu_user_id' => $user->id,
                    'email' => $email,
                ],
            ]
        );

        return back()->with('success', 'Konto pnedu.pl ('.$email.') zostało przywrócone.');
    }

    public function forceDelete(Request $request, int $id): RedirectResponse
    {
        $this->authorizePneduUserManage();

        $user = PneduUser::withTrashed()->findOrFail($id);
        if (! $user->trashed()) {
            return back()->with('error', 'Najpierw usuń konto standardowo, a dopiero potem trwale.');
        }

        $email = $user->email;
        $userId = $user->id;
        $user->forceDelete();

        ActivityLog::logCustom(
            'Użytkownik pnedu.pl: trwałe usunięcie konta',
            'Trwale usunięto konto użytkownika ID '.$userId.' ('.$email.') z bazy pnedu.',
            [
                'old_values' => [
                    'pnedu_user_id' => $userId,
                    'email' => $email,
                ],
            ]
        );

        return back()->with('success', 'Konto pnedu.pl ('.$email.') zostało trwale usunięte.');
    }

    private function authorizePneduUserManage(): void
    {
        if (! auth()->user()->hasPermission('users.edit')) {
            abort(403, 'Brak uprawnień do zarządzania użytkownikami pnedu.pl.');
        }
    }

    /**
     * Pozycje z tabeli {@see Participant} (PNEADM), dopasowane po znormalizowanym e-mailu.
     *
     * @return Collection<int, Participant>
     */
    private function participationsForPneduEmail(?string $email): Collection
    {
        $norm = Participant::normalizeEmail($email);
        if ($norm === null) {
            return collect();
        }

        return Participant::query()
            ->withTrashed()
            ->with(['course' => fn ($q) => $q->withTrashed()])
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->get()
            ->sortByDesc(fn (Participant $p) => $p->course?->start_date?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Liczba rekordów uczestnictwa dla szkoleń płatnych / bezpłatnych (według {@see Course::is_paid}).
     *
     * @return array{0: int, 1: int} [paid, free]
     */
    private function participationPaidFreeCounts(Collection $participations): array
    {
        $paid = 0;
        $free = 0;

        foreach ($participations as $participant) {
            $course = $participant->course;
            if ($course === null) {
                continue;
            }

            if ($course->is_paid) {
                $paid++;
            } else {
                $free++;
            }
        }

        return [$paid, $free];
    }

    private function normalizeListQuery(Request $request): void
    {
        foreach (['email', 'name', 'registered_from', 'registered_to', 'trashed', 'undeliverable_reason'] as $key) {
            if ($request->query($key) === '') {
                $request->query->remove($key);
            }
        }
    }

    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date'],
            'verified' => ['nullable', 'in:all,yes,no'],
            'deliverability' => ['nullable', 'in:all,undeliverable,deliverable'],
            'undeliverable_reason' => ['nullable', 'in:permanent_bounce,complaint'],
            'has_paid' => ['nullable', 'in:all,yes,no'],
            'trashed' => ['nullable', 'in:active,with,only'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORT_COLUMNS)],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        if (! empty($data['registered_from']) && ! empty($data['registered_to'])) {
            $from = Carbon::parse($data['registered_from'])->startOfDay();
            $to = Carbon::parse($data['registered_to'])->endOfDay();
            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'registered_to' => 'Data „do” nie może być wcześniejsza niż data „od”.',
                ]);
            }
        }

        $sort = in_array($data['sort'] ?? '', self::SORT_COLUMNS, true)
            ? $data['sort']
            : 'created_at';
        $dir = ($data['dir'] ?? '') === 'asc' ? 'asc' : 'desc';

        return [
            'email' => isset($data['email']) ? trim($data['email']) : null,
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'registered_from' => $data['registered_from'] ?? null,
            'registered_to' => $data['registered_to'] ?? null,
            'verified' => in_array($data['verified'] ?? '', ['yes', 'no'], true) ? $data['verified'] : 'all',
            'deliverability' => in_array($data['deliverability'] ?? '', ['undeliverable', 'deliverable'], true)
                ? $data['deliverability']
                : 'all',
            'undeliverable_reason' => $data['undeliverable_reason'] ?? null,
            'has_paid' => in_array($data['has_paid'] ?? '', ['yes', 'no'], true) ? $data['has_paid'] : 'all',
            'trashed' => in_array($data['trashed'] ?? '', ['with', 'only'], true) ? $data['trashed'] : 'active',
            'sort' => $sort,
            'dir' => $dir,
        ];
    }
}

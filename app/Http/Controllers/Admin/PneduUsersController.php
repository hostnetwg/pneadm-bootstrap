<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PneduUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PneduUsersController extends Controller
{
    private const PER_PAGE = 25;

    /** @var list<string> */
    private const SORT_COLUMNS = ['id', 'email', 'created_at', 'first_name', 'last_name', 'birth_date', 'email_verified_at'];

    public function index(Request $request): View
    {
        if (! auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        $this->normalizeListQuery($request);

        $filters = $this->validatedFilters($request);

        session(['pnedu_users_list_query' => $request->query()]);

        $query = PneduUser::query();
        $this->applyFilters($query, $filters);

        $sort = $filters['sort'];
        $dir = $filters['dir'];
        $query->orderBy($sort, $dir);

        if ($sort !== 'id') {
            $query->orderBy('id', $dir);
        }

        $users = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.pnedu-users.index', [
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    public function show(PneduUser $pnedu_user): View
    {
        if (! auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        return view('admin.pnedu-users.show', ['user' => $pnedu_user]);
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

    private function authorizePneduUserManage(): void
    {
        if (! auth()->user()->hasPermission('users.edit')) {
            abort(403, 'Brak uprawnień do zarządzania użytkownikami pnedu.pl.');
        }
    }

    private function normalizeListQuery(Request $request): void
    {
        foreach (['email', 'name', 'registered_from', 'registered_to'] as $key) {
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
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /**
     * @param  array{email: ?string, name: ?string, registered_from: ?string, registered_to: ?string, verified: string, sort: string, dir: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['email'] !== null && $filters['email'] !== '') {
            $term = '%'.addcslashes($filters['email'], '%_\\').'%';
            $query->where('email', 'like', $term);
        }

        if ($filters['name'] !== null && $filters['name'] !== '') {
            $term = '%'.addcslashes($filters['name'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereRaw(
                        "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                        [$term]
                    );
            });
        }

        if (! empty($filters['registered_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['registered_from'])->startOfDay());
        }
        if (! empty($filters['registered_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['registered_to'])->endOfDay());
        }

        if ($filters['verified'] === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($filters['verified'] === 'no') {
            $query->whereNull('email_verified_at');
        }
    }
}

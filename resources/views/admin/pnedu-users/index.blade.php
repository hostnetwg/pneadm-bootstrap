@php
    $sort = $filters['sort'];
    $dir = $filters['dir'];
    $sortLink = function (string $column) use ($sort, $dir) {
        if ($sort === $column) {
            $newDir = $dir === 'asc' ? 'desc' : 'asc';
        } else {
            $newDir = in_array($column, ['email', 'first_name', 'last_name'], true) ? 'asc' : 'desc';
        }

        return route('admin.pnedu-users.index', array_merge(
            request()->except('page'),
            ['sort' => $column, 'dir' => $newDir]
        ));
    };
    $sortIcon = function (string $column) use ($sort, $dir) {
        if ($sort !== $column) {
            return '';
        }

        return $dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
    };
    $sortIconClass = function (string $column) use ($sort, $dir) {
        if ($sort !== $column) {
            return 'bi-arrow-down-up text-muted';
        }

        return ($dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down').' text-primary';
    };
    $quickFilterUrl = function (array $overrides = []) use ($filters) {
        return route('admin.pnedu-users.index', array_merge(
            [
                'sort' => $filters['sort'],
                'dir' => $filters['dir'],
                'trashed' => 'active',
            ],
            $overrides
        ));
    };
    $isQuickActive = function (array $expected) use ($filters) {
        foreach ($expected as $key => $value) {
            if (($filters[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-people me-2"></i>
            Użytkownicy pnedu.pl
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid" style="max-width: 1400px;">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                </div>
            @endif

            <p class="text-muted small mb-3">
                Konta zarejestrowane na stronie pnedu.pl (baza <code>pnedu</code>, tabela <code>users</code>).
                @if(! empty($loginTrackingAvailable))
                    <span class="d-block mt-1">
                        <strong>Ostatnie logowanie</strong> i <strong>liczba wejść</strong> — nowa sesja na pnedu.pl
                        (formularz lub „zapamiętaj mnie”), bez liczenia kolejnych podstron w tej samej sesji.
                    </span>
                @endif
            </p>

            @if(empty($loginTrackingAvailable))
                <div class="alert alert-warning py-2 mb-3" role="alert">
                    Brak kolumn <code>last_login_at</code> / <code>login_count</code> w bazie pnedu —
                    uruchom migracje na pnedu.pl (<code>php artisan migrate</code>), aby śledzić logowania.
                </div>
            @endif

            @if(empty($deliverabilityAvailable))
                <div class="alert alert-warning py-2 mb-3" role="alert">
                    Kolumny <code>email_undeliverable_*</code> nie są jeszcze w bazie pnedu — uruchom migracje na pnedu.pl
                    (<code>php artisan migrate</code>). Statystyki bounce będą niedostępne do czasu migracji.
                </div>
            @elseif($stats['undeliverable'] > 0)
                <div class="alert alert-danger py-2 mb-3" role="alert">
                    <i class="bi bi-envelope-x me-1" aria-hidden="true"></i>
                    <strong>{{ $stats['undeliverable'] }}</strong> kont ma niedostarczalny e-mail (bounce/skarga).
                    @if($stats['undeliverable_paid'] > 0)
                        W tym <strong>{{ $stats['undeliverable_paid'] }}</strong> z płatnym szkoleniem — priorytet kontaktu.
                    @endif
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-danger h-100 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-danger small fw-semibold text-uppercase">Niedostarczalne</div>
                            <div class="fs-3 fw-bold text-dark">{{ $stats['undeliverable'] }}</div>
                            <div class="small text-muted">bounce / skarga SES</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-warning h-100 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-warning-emphasis small fw-semibold text-uppercase">Niezweryfikowane</div>
                            <div class="fs-3 fw-bold text-dark">{{ $stats['unverified'] }}</div>
                            <div class="small text-muted">bez kliknięcia w link</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-info h-100 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-info-emphasis small fw-semibold text-uppercase">Czekają na link</div>
                            <div class="fs-3 fw-bold text-dark">{{ $stats['unverified_deliverable'] }}</div>
                            <div class="small text-muted">bez bounce — np. spam / oferty</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-secondary h-100 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-secondary small fw-semibold text-uppercase">Niedost. (7 dni)</div>
                            <div class="fs-3 fw-bold text-dark">{{ $stats['undeliverable_recent_7d'] }}</div>
                            <div class="small text-muted">nowe bounce w tygodniu</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="small text-muted align-self-center me-1">Szybkie filtry:</span>
                <a href="{{ $quickFilterUrl(['verified' => 'no', 'deliverability' => 'all', 'has_paid' => 'all']) }}"
                   class="btn btn-sm {{ $isQuickActive(['verified' => 'no', 'deliverability' => 'all', 'has_paid' => 'all', 'trashed' => 'active']) && $filters['undeliverable_reason'] === null ? 'btn-warning' : 'btn-outline-warning' }}">
                    Niezweryfikowane ({{ $stats['unverified'] }})
                </a>
                <a href="{{ $quickFilterUrl(['verified' => 'no', 'deliverability' => 'deliverable', 'has_paid' => 'all']) }}"
                   class="btn btn-sm {{ $isQuickActive(['verified' => 'no', 'deliverability' => 'deliverable', 'has_paid' => 'all', 'trashed' => 'active']) ? 'btn-info' : 'btn-outline-info' }}">
                    Czekają na link ({{ $stats['unverified_deliverable'] }})
                </a>
                <a href="{{ $quickFilterUrl(['verified' => 'all', 'deliverability' => 'undeliverable', 'has_paid' => 'all']) }}"
                   class="btn btn-sm {{ $isQuickActive(['verified' => 'all', 'deliverability' => 'undeliverable', 'has_paid' => 'all', 'trashed' => 'active']) && $filters['undeliverable_reason'] === null ? 'btn-danger' : 'btn-outline-danger' }}">
                    Niedostarczalne ({{ $stats['undeliverable'] }})
                </a>
                <a href="{{ $quickFilterUrl(['verified' => 'all', 'deliverability' => 'undeliverable', 'has_paid' => 'yes']) }}"
                   class="btn btn-sm {{ $isQuickActive(['deliverability' => 'undeliverable', 'has_paid' => 'yes', 'trashed' => 'active']) ? 'btn-danger' : 'btn-outline-danger' }}">
                    Niedost. + płatne ({{ $stats['undeliverable_paid'] }})
                </a>
                <a href="{{ $quickFilterUrl(['verified' => 'no', 'deliverability' => 'all', 'has_paid' => 'yes']) }}"
                   class="btn btn-sm {{ $isQuickActive(['verified' => 'no', 'has_paid' => 'yes', 'trashed' => 'active']) ? 'btn-outline-dark' : 'btn-outline-secondary' }}">
                    Niezweryf. + płatne ({{ $stats['unverified_paid'] }})
                </a>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Filtry i wyszukiwanie</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.pnedu-users.index') }}" class="row g-3 align-items-end">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

                        <div class="col-md-4">
                            <label for="filter-email" class="form-label small text-muted mb-1">E-mail</label>
                            <input type="text" name="email" id="filter-email" class="form-control form-control-sm"
                                   value="{{ old('email', $filters['email']) }}" placeholder="Fragment adresu">
                        </div>
                        <div class="col-md-4">
                            <label for="filter-name" class="form-label small text-muted mb-1">Imię lub nazwisko</label>
                            <input type="text" name="name" id="filter-name" class="form-control form-control-sm"
                                   value="{{ old('name', $filters['name']) }}" placeholder="Fragment imienia / nazwiska">
                        </div>
                        <div class="col-md-4">
                            <label for="filter-verified" class="form-label small text-muted mb-1">E-mail zweryfikowany</label>
                            <select name="verified" id="filter-verified" class="form-select form-select-sm">
                                <option value="all" @selected($filters['verified'] === 'all')>Wszystkie</option>
                                <option value="yes" @selected($filters['verified'] === 'yes')>Tak</option>
                                <option value="no" @selected($filters['verified'] === 'no')>Nie</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter-deliverability" class="form-label small text-muted mb-1">Dostarczalność e-mail</label>
                            <select name="deliverability" id="filter-deliverability" class="form-select form-select-sm">
                                <option value="all" @selected($filters['deliverability'] === 'all')>Wszystkie</option>
                                <option value="deliverable" @selected($filters['deliverability'] === 'deliverable')>Bez bounce (czeka / OK)</option>
                                <option value="undeliverable" @selected($filters['deliverability'] === 'undeliverable')>Niedostarczalne</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter-undeliverable-reason" class="form-label small text-muted mb-1">Powód niedostarczalności</label>
                            <select name="undeliverable_reason" id="filter-undeliverable-reason" class="form-select form-select-sm">
                                <option value="" @selected($filters['undeliverable_reason'] === null)>Wszystkie</option>
                                <option value="permanent_bounce" @selected($filters['undeliverable_reason'] === 'permanent_bounce')>Trwały bounce</option>
                                <option value="complaint" @selected($filters['undeliverable_reason'] === 'complaint')>Skarga (complaint)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter-has-paid" class="form-label small text-muted mb-1">Płatne szkolenie</label>
                            <select name="has_paid" id="filter-has-paid" class="form-select form-select-sm">
                                <option value="all" @selected($filters['has_paid'] === 'all')>Wszystkie</option>
                                <option value="yes" @selected($filters['has_paid'] === 'yes')>Tak</option>
                                <option value="no" @selected($filters['has_paid'] === 'no')>Nie</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter-trashed" class="form-label small text-muted mb-1">Status konta</label>
                            <select name="trashed" id="filter-trashed" class="form-select form-select-sm">
                                <option value="active" @selected($filters['trashed'] === 'active')>Tylko aktywne</option>
                                <option value="with" @selected($filters['trashed'] === 'with')>Aktywne i usunięte</option>
                                <option value="only" @selected($filters['trashed'] === 'only')>Tylko usunięte</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter-from" class="form-label small text-muted mb-1">Rejestracja od</label>
                            <input type="date" name="registered_from" id="filter-from" class="form-control form-control-sm"
                                   value="{{ old('registered_from', $filters['registered_from']) }}">
                        </div>
                        <div class="col-md-4">
                            <label for="filter-to" class="form-label small text-muted mb-1">Rejestracja do</label>
                            <input type="date" name="registered_to" id="filter-to" class="form-control form-control-sm"
                                   value="{{ old('registered_to', $filters['registered_to']) }}">
                            @error('registered_to')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search me-1"></i> Szukaj
                            </button>
                            <a href="{{ route('admin.pnedu-users.index') }}" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">
                                        <a href="{{ $sortLink('id') }}" class="text-decoration-none text-dark">
                                            ID @if($sortIcon('id'))<i class="bi {{ $sortIcon('id') }}"></i>@endif
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="{{ $sortLink('email') }}" class="text-decoration-none text-dark">
                                            E-mail @if($sortIcon('email'))<i class="bi {{ $sortIcon('email') }}"></i>@endif
                                        </a>
                                    </th>
                                    <th scope="col">Status e-mail</th>
                                    <th scope="col">
                                        <a href="{{ $sortLink('first_name') }}" class="text-decoration-none text-dark" title="Sort po imieniu">
                                            Imię @if($sortIcon('first_name'))<i class="bi {{ $sortIcon('first_name') }}"></i>@endif
                                        </a>
                                        /
                                        <a href="{{ $sortLink('last_name') }}" class="text-decoration-none text-dark" title="Sort po nazwisku">
                                            Nazwisko @if($sortIcon('last_name'))<i class="bi {{ $sortIcon('last_name') }}"></i>@endif
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="{{ $sortLink('birth_date') }}" class="text-decoration-none text-dark" title="Sort po dacie urodzenia">
                                            Urodzenie @if($sortIcon('birth_date'))<i class="bi {{ $sortIcon('birth_date') }}"></i>@endif
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="{{ $sortLink('email_verified_at') }}" class="text-decoration-none text-dark">
                                            Zweryfikowany @if($sortIcon('email_verified_at'))<i class="bi {{ $sortIcon('email_verified_at') }}"></i>@endif
                                        </a>
                                    </th>
                                    <th scope="col">
                                        <a href="{{ $sortLink('created_at') }}" class="text-decoration-none text-dark">
                                            Rejestracja @if($sortIcon('created_at'))<i class="bi {{ $sortIcon('created_at') }}"></i>@endif
                                        </a>
                                    </th>
                                    @if(! empty($loginTrackingAvailable))
                                        <th scope="col">
                                            <span class="d-inline-flex align-items-center gap-1 flex-wrap">
                                                <span title="Ostatnia nowa sesja na pnedu.pl">Ostatnie logowanie</span>
                                                <a href="{{ $sortLink('last_login_at') }}"
                                                   class="text-decoration-none lh-1"
                                                   title="Sortuj według ostatniego logowania"
                                                   aria-label="Sortuj według ostatniego logowania">
                                                    <i class="bi {{ $sortIconClass('last_login_at') }}"></i>
                                                </a>
                                            </span>
                                        </th>
                                        <th scope="col" class="text-center">
                                            <span class="d-inline-flex align-items-center justify-content-center gap-1 flex-wrap">
                                                <span title="Liczba nowych sesji (wejść)">Wejścia</span>
                                                <a href="{{ $sortLink('login_count') }}"
                                                   class="text-decoration-none lh-1"
                                                   title="Sortuj według liczby wejść"
                                                   aria-label="Sortuj według liczby wejść">
                                                    <i class="bi {{ $sortIconClass('login_count') }}"></i>
                                                </a>
                                            </span>
                                        </th>
                                    @endif
                                    <th scope="col" class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    @php
                                        $normEmail = strtolower(trim($user->email));
                                        $hasPaid = isset($paidEnrollmentEmails[$normEmail]);
                                    @endphp
                                    <tr>
                                        <td class="text-muted">{{ $user->id }}</td>
                                        <td>
                                            {{ $user->email }}
                                            @if($user->trashed())
                                                <span class="badge text-bg-danger ms-1">Usunięte</span>
                                            @endif
                                            @if($hasPaid)
                                                <span class="badge text-bg-dark ms-1" title="Ma zapis na płatne szkolenie">Płatne</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(! empty($deliverabilityAvailable) && $user->hasUndeliverableEmail())
                                                <span class="badge text-bg-danger" title="{{ $adminService->undeliverableReasonLabel($user->email_undeliverable_reason) }}">
                                                    Bounce
                                                </span>
                                                <span class="d-block text-muted small">{{ $user->email_undeliverable_at?->format('Y-m-d H:i') }}</span>
                                            @elseif(! $user->email_verified_at)
                                                <span class="badge text-bg-warning text-dark">Czeka na link</span>
                                            @else
                                                <span class="badge text-bg-success">OK</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->full_name }}</td>
                                        <td class="align-middle py-2">
                                            <div class="text-muted lh-sm" style="font-size: 0.8125rem;">
                                                <div>{{ $user->birth_date?->format('d.m.Y') ?? '—' }}</div>
                                                <div>{{ $user->birth_place ?: '—' }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($user->email_verified_at)
                                                <span class="badge text-bg-success">Tak</span>
                                                <span class="text-muted small">{{ $user->email_verified_at->format('Y-m-d H:i') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">Nie</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">
                                            {{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                        @if(! empty($loginTrackingAvailable))
                                            <td class="text-muted small">
                                                {{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-bg-light text-dark border">{{ (int) ($user->login_count ?? 0) }}</span>
                                            </td>
                                        @endif
                                        <td class="text-end">
                                            @if($user->trashed())
                                                <form method="POST" action="{{ route('admin.pnedu-users.restore', $user->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Przywróć
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.pnedu-users.force-delete', $user->id) }}" class="d-inline" onsubmit="return confirm('Trwale usunąć konto pnedu.pl {{ $user->email }}? Tej operacji nie można cofnąć.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Usuń trwale
                                                    </button>
                                                </form>
                                            @else
                                                <a href="{{ route('admin.pnedu-users.show', $user) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Podgląd
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ! empty($loginTrackingAvailable) ? 10 : 8 }}" class="text-center text-muted py-4">
                                            Brak użytkowników spełniających kryteria.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($users->hasPages())
                    <div class="card-footer bg-white border-top-0 py-3">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

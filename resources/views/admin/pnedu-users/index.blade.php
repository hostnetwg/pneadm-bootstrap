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
            <p class="text-muted small mb-3">
                Konta zarejestrowane na stronie pnedu.pl (baza <code>pnedu</code>, tabela <code>users</code>).
            </p>

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
                                    <th scope="col" class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr>
                                        <td class="text-muted">{{ $user->id }}</td>
                                        <td>{{ $user->email }}</td>
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
                                        <td class="text-end">
                                            <a href="{{ route('admin.pnedu-users.show', $user) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Podgląd
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
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

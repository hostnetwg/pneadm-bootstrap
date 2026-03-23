<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-person-badge me-2"></i>
                Użytkownik pnedu.pl
            </h2>
            <a href="{{ route('admin.pnedu-users.index', session('pnedu_users_list_query', [])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>
                Powrót do listy
            </a>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container" style="max-width: 720px;">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Dane konta</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">ID</dt>
                        <dd class="col-sm-8"><span class="badge bg-secondary">{{ $user->id }}</span></dd>

                        <dt class="col-sm-4 text-muted">Imię</dt>
                        <dd class="col-sm-8">{{ $user->first_name ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Nazwisko</dt>
                        <dd class="col-sm-8">{{ $user->last_name ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Data urodzenia</dt>
                        <dd class="col-sm-8">{{ $user->birth_date?->format('Y-m-d') ?? '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Miejsce urodzenia</dt>
                        <dd class="col-sm-8">{{ $user->birth_place ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">E-mail</dt>
                        <dd class="col-sm-8">
                            <a href="mailto:{{ $user->email }}" class="text-decoration-none">{{ $user->email }}</a>
                        </dd>

                        <dt class="col-sm-4 text-muted">E-mail zweryfikowany</dt>
                        <dd class="col-sm-8">
                            @if($user->email_verified_at)
                                <span class="badge text-bg-success">Tak</span>
                                <span class="text-muted ms-1">{{ $user->email_verified_at->format('Y-m-d H:i:s') }}</span>
                            @else
                                <span class="badge text-bg-secondary">Nie</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4 text-muted">Data rejestracji</dt>
                        <dd class="col-sm-8 text-muted">{{ $user->created_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Ostatnia aktualizacja</dt>
                        <dd class="col-sm-8 text-muted">{{ $user->updated_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

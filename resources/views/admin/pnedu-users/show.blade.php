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

            <div class="card shadow-sm mb-4">
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

            @if(auth()->user()->hasPermission('users.edit'))
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning bg-opacity-25 py-3">
                        <h5 class="mb-0 text-dark"><i class="bi bi-shield-exclamation me-2"></i>Wsparcie i bezpieczeństwo</h5>
                        <small class="text-muted">Wymaga uprawnienia „Edycja użytkowników”. Akcje są zapisywane w logu aktywności.</small>
                    </div>
                    <div class="card-body">
                        <div class="mb-4 pb-4 border-bottom">
                            <h6 class="fw-semibold">Reset hasła (e-mail)</h6>
                            <p class="text-muted small mb-2">
                                Wysyła standardową wiadomość Laravel z linkiem do formularza resetu na stronie pnedu.pl
                                (<code>{{ rtrim(config('services.pnedu_frontend_url'), '/') }}/reset-password/…</code>).
                            </p>
                            <form method="POST" action="{{ route('admin.pnedu-users.send-password-reset', $user) }}"
                                  onsubmit="return confirm('Wysłać e-mail z linkiem resetu hasła na adres {{ e($user->email) }}?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-envelope me-1"></i> Wyślij link resetu hasła
                                </button>
                            </form>
                        </div>

                        <div class="mb-4 pb-4 border-bottom">
                            <h6 class="fw-semibold">Ustaw hasło z panelu</h6>
                            <p class="text-muted small mb-2">Nadpisuje hasło w bazie pnedu (użyj tylko gdy jest to uzasadnione procedurą).</p>
                            <form method="POST" action="{{ route('admin.pnedu-users.set-password', $user) }}"
                                  class="row g-2 align-items-end"
                                  onsubmit="return confirm('Nadpisać hasło tego użytkownika nowym hasłem z formularza?');">
                                @csrf
                                <div class="col-md-5">
                                    <label class="form-label small mb-0" for="password">Nowe hasło</label>
                                    <input type="password" name="password" id="password" class="form-control form-control-sm" required autocomplete="new-password">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-0" for="password_confirmation">Powtórz hasło</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control form-control-sm" required autocomplete="new-password">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-warning btn-sm w-100">Zapisz hasło</button>
                                </div>
                            </form>
                            @error('password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <h6 class="fw-semibold">Ręczna weryfikacja e-maila</h6>
                            @if($user->email_verified_at)
                                <p class="text-muted small mb-0">Adres jest już zweryfikowany — akcja niedostępna.</p>
                            @else
                                <p class="text-muted small mb-2">Oznacza adres jako zweryfikowany (np. po weryfikacji poza systemem).</p>
                                <form method="POST" action="{{ route('admin.pnedu-users.verify-email', $user) }}">
                                    @csrf
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="confirm_verify" id="confirm_verify" value="1" required>
                                        <label class="form-check-label small" for="confirm_verify">
                                            Potwierdzam ręczną weryfikację adresu e-mail <strong>{{ $user->email }}</strong> dla tego konta.
                                        </label>
                                    </div>
                                    @error('confirm_verify')
                                        <div class="text-danger small mb-2">{{ $message }}</div>
                                    @enderror
                                    <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Oznaczyć ten e-mail jako zweryfikowany?');">
                                        <i class="bi bi-check2-circle me-1"></i> Zweryfikuj e-mail
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

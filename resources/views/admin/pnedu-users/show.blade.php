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
        <div class="container" style="max-width: 960px;">
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

                        <dt class="col-sm-4 text-muted">Udział w szkoleniach płatnych</dt>
                        <dd class="col-sm-8"><span class="fw-semibold">{{ $participationsPaidCount }}</span></dd>

                        <dt class="col-sm-4 text-muted">Udział w szkoleniach bezpłatnych</dt>
                        <dd class="col-sm-8"><span class="fw-semibold">{{ $participationsFreeCount }}</span></dd>
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
                            <form method="post"
                                  id="pnedu-send-password-reset-form"
                                  action="{{ route('admin.pnedu-users.send-password-reset', ['pnedu_user' => $user->getKey()]) }}">
                                @csrf
                            </form>
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#pneduSendPasswordResetModal">
                                <i class="bi bi-envelope me-1"></i> Wyślij link resetu hasła
                            </button>

                            <div class="modal fade" id="pneduSendPasswordResetModal" tabindex="-1" aria-labelledby="pneduSendPasswordResetModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2 class="modal-title fs-5" id="pneduSendPasswordResetModalLabel">Potwierdź wysyłkę</h2>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-2">Wysłać e-mail z linkiem resetu hasła na adres <strong>{{ $user->email }}</strong>?</p>
                                            <p class="text-muted small mb-0">
                                                Użytkownik otrzyma wiadomość z linkiem do ustawienia nowego hasła na stronie pnedu.pl.
                                            </p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                                            <button type="submit" form="pnedu-send-password-reset-form" class="btn btn-primary">
                                                <i class="bi bi-envelope me-1"></i> Wyślij link resetu hasła
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @include('admin.pnedu-users.partials.access-credentials-email-modal')

                        <div class="mb-4 pb-4 border-bottom">
                            <h6 class="fw-semibold">Ustaw hasło z panelu</h6>
                            <p class="text-muted small mb-2">Nadpisuje hasło w bazie pnedu (użyj tylko gdy jest to uzasadnione procedurą).</p>
                            <form method="post"
                                  id="pnedu-user-set-password-form"
                                  action="{{ route('admin.pnedu-users.set-password', ['pnedu_user' => $user->getKey()]) }}"
                                  class="row g-2 align-items-end"
                                  onsubmit="return confirm('Nadpisać hasło tego użytkownika nowym hasłem z formularza?');">
                                @csrf
                                <div class="col-md-5">
                                    <label class="form-label small mb-0" for="pnedu_admin_set_password">Nowe hasło</label>
                                    <input type="password" name="password" id="pnedu_admin_set_password" class="form-control form-control-sm" required autocomplete="new-password">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-0" for="pnedu_admin_set_password_confirmation">Powtórz hasło</label>
                                    <input type="password" name="password_confirmation" id="pnedu_admin_set_password_confirmation" class="form-control form-control-sm" required autocomplete="new-password">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit"
                                            class="btn btn-warning btn-sm w-100"
                                            formaction="{{ route('admin.pnedu-users.set-password', ['pnedu_user' => $user->getKey()]) }}"
                                            formmethod="post">
                                        Zapisz hasło
                                    </button>
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
                                <form method="post" action="{{ route('admin.pnedu-users.verify-email', ['pnedu_user' => $user->getKey()]) }}">
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

                        <div class="pt-4 mt-4 border-top border-danger">
                            <h6 class="fw-semibold text-danger mb-2">
                                <i class="bi bi-trash3 me-1"></i>Usunięcie konta (pnedu.pl)
                            </h6>
                            <p class="text-muted small mb-3">
                                Deaktywuje konto tak jak przy usunięciu przez użytkownika: <strong>soft delete</strong> (<code>deleted_at</code> w bazie pnedu),
                                brak możliwości logowania. Adres e-mail może zostać ponownie użyty przy nowej rejestracji.
                                Uprawnienie: edycja użytkowników — akcja trafia do logu aktywności.
                            </p>
                            <form method="post"
                                  action="{{ route('admin.pnedu-users.destroy', ['pnedu_user' => $user->getKey()]) }}"
                                  onsubmit="return confirm('Na pewno usunąć to konto pnedu.pl? Operacji nie cofniesz z tego panelu (wymagany dostęp do bazy / procedura odzyskiwania).');">
                                @csrf
                                @method('DELETE')
                                <div class="form-check mb-3">
                                    <input class="form-check-input @error('confirm_delete') is-invalid @enderror"
                                           type="checkbox"
                                           name="confirm_delete"
                                           id="pnedu_user_confirm_delete"
                                           value="1"
                                           required>
                                    <label class="form-check-label small" for="pnedu_user_confirm_delete">
                                        Potwierdzam usunięcie (soft delete) konta <strong>{{ $user->email }}</strong> (ID {{ $user->id }}).
                                    </label>
                                    @error('confirm_delete')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash3 me-1"></i>Usuń konto
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <h5 class="mb-1"><i class="bi bi-mortarboard me-2"></i>Szkolenia (uczestnictwo)</h5>
                    <small class="text-muted mb-0 d-block">
                        Wpisy z tabeli uczestników w PNEADM — dopasowanie po adresie e-mail (ta sama wartość co na koncie pnedu; bez rozróżniania małych/wielkich liter).
                    </small>
                </div>
                <div class="card-body">
                    @if($participations->isEmpty())
                        <p class="text-muted small mb-0">Brak zarejestrowanego uczestnictwa dla tego adresu e-mail.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Data rozpoczęcia</th>
                                        <th scope="col">Szkolenie</th>
                                        <th scope="col" class="text-center">Wpisy</th>
                                        <th scope="col">Dostęp do</th>
                                        <th scope="col" class="text-end">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($participations as $participant)
                                        @php
                                            $course = $participant->course;
                                            $courseTitlePlain = $course
                                                ? strip_tags(html_entity_decode((string) $course->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                                                : '—';
                                        @endphp
                                        <tr>
                                            <td class="text-nowrap">
                                                @if($course?->start_date)
                                                    {{ $course->start_date->format('d.m.Y H:i') }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($course)
                                                    @if($course->trashed())
                                                        <span class="badge bg-secondary text-wrap">Szkolenie w koszu</span>
                                                    @endif
                                                    <span title="{{ $courseTitlePlain }}">{{ \Illuminate\Support\Str::limit($courseTitlePlain, 80) }}</span>
                                                    <span class="text-muted small">#{{ $course->id }}</span>
                                                @else
                                                    <span class="text-muted">Brak szkolenia (ID {{ $participant->course_id }})</span>
                                                @endif
                                            </td>
                                            <td class="text-center text-nowrap">
                                                @if($participant->trashed())
                                                    <span class="badge text-bg-secondary">Archiwum</span>
                                                @else
                                                    <span class="badge text-bg-success">Aktywny</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap small">
                                                @if($participant->access_expires_at)
                                                    {{ $participant->access_expires_at->format('d.m.Y H:i') }}
                                                @else
                                                    <span class="text-muted">Bez wygaśnięcia</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if($course)
                                                    <a href="{{ route('courses.show', $course->id) }}" class="btn btn-outline-primary btn-sm">
                                                        Szczegóły
                                                    </a>
                                                    @unless($participant->trashed())
                                                        <a href="{{ route('participants.edit', ['course' => $course->id, 'participant' => $participant->id]) }}"
                                                           class="btn btn-outline-secondary btn-sm">
                                                            Uczestnik
                                                        </a>
                                                    @endunless
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

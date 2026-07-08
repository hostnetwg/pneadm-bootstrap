<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Dostępy: {{ $online_course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('online-courses.enrollments.create', $online_course) }}" class="btn btn-primary">Dodaj dostęp</a>
                <a href="{{ route('online-courses.edit', $online_course) }}" class="btn btn-outline-secondary">Treść kursu</a>
                @if(!$online_course->certificate_template_id)
                    <span class="text-muted small ms-2">
                        <i class="bi bi-info-circle"></i> Aby wydawać zaświadczenia, przypisz szablon w edycji kursu online.
                    </span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>E-mail</th>
                            <th>Imię i nazwisko</th>
                            <th>Wygasa</th>
                            <th>Źródło</th>
                            <th>Nr zaświadczenia</th>
                            <th>Zaświadczenie</th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollments as $e)
                            <tr>
                                <td>{{ $e->email }}</td>
                                <td>{{ trim(($e->first_name ?? '').' '.($e->last_name ?? '')) ?: '—' }}</td>
                                <td>{{ $e->access_expires_at ? $e->access_expires_at->format('Y-m-d H:i') . ' UTC' : 'bezterminowo' }}</td>
                                <td>{{ $e->access_source }}</td>
                                <td>
                                    @if ($e->certificate)
                                        <a href="{{ route('online-courses.enrollments.certificate.generate', [$online_course, $e]) }}">
                                            {{ $e->certificate->certificate_number }}
                                        </a>
                                        @if(!empty($e->certificate->file_path))
                                            <a href="{{ route('certificates.download-pdf', $e->certificate) }}" class="text-success ms-1 text-decoration-none" title="Pobierz plik PDF z serwera (bez generowania)">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                        @endif
                                        @php
                                            $downloadCount = (int) ($e->certificate->download_count ?? 0);
                                            $lastDownloadedAt = $e->certificate->last_downloaded_at ?? null;
                                        @endphp
                                        <div class="mt-1">
                                            <span class="badge {{ $downloadCount > 0 ? 'bg-success' : 'bg-secondary' }}"
                                                  title="{{ $downloadCount > 0 && $lastDownloadedAt ? 'Ostatnie pobranie: ' . $lastDownloadedAt->format('d.m.Y H:i') : '' }}">
                                                Pobrane: {{ $downloadCount > 0 ? 'TAK' : 'NIE' }}@if($downloadCount > 0 && $lastDownloadedAt) ({{ $lastDownloadedAt->format('d.m.Y H:i') }})@endif
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if ($e->certificate)
                                            <button type="button" class="btn btn-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteCertificateModal{{ $e->certificate->id }}">
                                                <i class="bi bi-trash"></i> Usuń
                                            </button>
                                            @if(!empty($e->certificate->file_path))
                                                <form id="deleteCertificatePdfForm{{ $e->certificate->id }}" action="{{ route('certificates.delete-pdf', $e->certificate) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="button"
                                                            class="btn btn-outline-warning btn-sm"
                                                            title="Usuwa plik PDF, zachowuje zaświadczenie – potem wygeneruj ponownie"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#formConfirmModal"
                                                            data-confirm-title="Usuń plik PDF"
                                                            data-confirm-message="Usunąć tylko plik PDF tego zaświadczenia? Numer zaświadczenia zostanie zachowany – potem możesz wygenerować plik ponownie (np. po poprawce danych)."
                                                            data-confirm-form="#deleteCertificatePdfForm{{ $e->certificate->id }}"
                                                            data-confirm-btn-class="btn-warning"
                                                            data-confirm-btn-text="Usuń PDF"
                                                            data-confirm-header-class="bg-warning text-dark">
                                                        <i class="bi bi-file-earmark-pdf"></i> Usuń PDF
                                                    </button>
                                                </form>
                                            @endif
                                        @elseif($online_course->certificate_template_id)
                                            <a href="{{ route('online-courses.enrollments.certificate.store', [$online_course, $e]) }}" class="btn btn-primary btn-sm">Generuj</a>
                                        @else
                                            <span class="text-muted small">Brak szablonu</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-column gap-1 align-items-end">
                                        <a href="{{ route('online-courses.enrollments.edit', [$online_course, $e]) }}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                                        <button type="button" class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteEnrollmentModal{{ $e->id }}">
                                            <i class="bi bi-trash"></i> Usuń
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">Brak przypisań.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $enrollments->links() }}
        </div>
    </div>

    @foreach ($enrollments as $e)
        <div class="modal fade" id="deleteEnrollmentModal{{ $e->id }}" tabindex="-1" aria-labelledby="deleteEnrollmentModalLabel{{ $e->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteEnrollmentModalLabel{{ $e->id }}">
                            <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia dostępu
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz usunąć dostęp dla <strong>{{ $e->email }}</strong>?</p>
                        <div class="bg-light p-3 rounded">
                            <ul class="mb-0">
                                <li><strong>Osoba:</strong> {{ trim(($e->first_name ?? '').' '.($e->last_name ?? '')) ?: '—' }}</li>
                                <li><strong>Kurs online:</strong> {{ $online_course->title }}</li>
                            </ul>
                        </div>
                        <p class="text-muted mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            Użytkownik straci dostęp do kursu. Zaświadczenie (jeśli istnieje) pozostaje w systemie.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form action="{{ route('online-courses.enrollments.destroy', [$online_course, $e]) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Usuń dostęp
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($e->certificate)
            <div class="modal fade" id="deleteCertificateModal{{ $e->certificate->id }}" tabindex="-1" aria-labelledby="deleteCertificateModalLabel{{ $e->certificate->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteCertificateModalLabel{{ $e->certificate->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia zaświadczenia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć zaświadczenie <strong>#{{ $e->certificate->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły zaświadczenia:</h6>
                                <ul class="mb-0">
                                    <li><strong>Numer zaświadczenia:</strong> {{ $e->certificate->certificate_number ?? 'Brak numeru' }}</li>
                                    <li><strong>Osoba:</strong> {{ $e->first_name }} {{ $e->last_name }}</li>
                                    <li><strong>E-mail:</strong> {{ $e->email }}</li>
                                    <li><strong>Kurs online:</strong> {{ $online_course->title }}</li>
                                    <li><strong>Data wygenerowania:</strong> {{ $e->certificate->created_at ? $e->certificate->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Zaświadczenie zostanie trwale usunięte z systemu. Ta operacja jest nieodwracalna!
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('certificates.destroy', $e->certificate->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń zaświadczenie
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    @include('participants.partials.form-confirm-modal')
</x-app-layout>

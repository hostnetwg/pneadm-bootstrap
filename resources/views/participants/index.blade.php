<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-semibold fs-4 text-dark mb-1">
                    <i class="fas fa-users me-2"></i>Lista uczestników
                </h2>
                <p class="text-muted mb-0">
                    <strong>{!! $course->title !!}</strong>
                    <span class="ms-2">
                        <i class="fas fa-calendar me-1"></i>
                        {{ date('d.m.Y H:i', strtotime($course->start_date)) }}
                    </span>
                </p>
            </div>
            <div class="text-end">
                <span class="badge bg-primary fs-6">{{ $participants->total() }} uczestników</span>
            </div>
        </div>
    </x-slot>

    <div class="container py-3">
        @if(!empty($certificatePdfGenerationCompletedAt))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Generowanie plików PDF dla tego szkolenia zakończone.</strong> ({{ $certificatePdfGenerationCompletedAt }})
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
            </div>
        @endif
        @if(isset($totalCertificates) && isset($downloadedCertificates))
            <div class="alert alert-light border d-flex align-items-center justify-content-between mb-4" role="alert">
                <div>
                    <i class="fas fa-download me-2"></i>
                    <strong>Pobrania zaświadczeń:</strong>
                    <span class="ms-1">Pobrało <strong>{{ $downloadedCertificates }}</strong> z <strong>{{ $totalCertificates }}</strong> (liczone po rekordach w `certificates`).</span>
                </div>
                <span class="badge {{ $downloadedCertificates > 0 ? 'bg-success' : 'bg-secondary' }} fs-6">
                    {{ $downloadedCertificates }}/{{ $totalCertificates }}
                </span>
            </div>
        @endif
        <div id="generatingPdfsAlert" class="alert alert-info d-none mb-4" role="alert"
             data-progress-url="{{ route('certificates.pdf-generation-progress', $course) }}"
             data-status-url="{{ route('certificates.pdf-generation-status', $course) }}"
             data-cancel-url="{{ route('certificates.cancel-pdf-generation', $course) }}">
            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            <span class="generating-pdfs-text"><strong>Trwa generowanie plików PDF.</strong> Na koniec zobaczysz komunikat z potwierdzeniem.</span>
            <span class="generating-pdfs-cancel-wrap d-none ms-2">
                <button type="button" class="btn btn-sm btn-outline-danger" id="cancelPdfGenerationBtn">Przerwij generowanie</button>
            </span>
        </div>
        <div id="sendingEmailsAlert" class="alert alert-info d-none mb-4" role="alert"
             data-status-url="{{ route('participants.certificate-emails.status', $course) }}"
             data-cancel-url="{{ route('participants.certificate-emails.cancel', $course) }}">
            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            <span class="sending-emails-text"><strong>Trwa wysyłanie e-maili.</strong> Na koniec zobaczysz komunikat z potwierdzeniem.</span>
            <span class="sending-emails-cancel-wrap d-none ms-2">
                <button type="button" class="btn btn-sm btn-outline-danger" id="cancelEmailBatchBtn">Przerwij wysyłkę</button>
            </span>
        </div>
        <div id="otherCoursePdfGenerationAlert" class="alert alert-warning d-none mb-4" role="alert"
             data-status-any-url="{{ route('certificates.pdf-generation-status-any') }}"
             data-current-course-id="{{ $course->id }}">
            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            <span class="other-course-pdfs-text"></span>
        </div>
        <!-- Nagłówek z akcjami -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('courses.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Powrót do listy szkoleń
                    </a>
                    <a href="{{ route('courses.show', $course->id) }}" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-1"></i> Powrót do kursu
                    </a>
                    <a href="{{ route('participants.create', $course) }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Dodaj uczestnika
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-csv me-1"></i> Import uczestników z PUBLIGO CSV
                    </button>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCertificatesModal">
                        <i class="fas fa-certificate me-1"></i> Import zaświadczeń z PUBLIGO CSV
                    </button>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkGenerateModal">
                        <i class="fas fa-certificate me-1"></i> Wygeneruj zaświadczenia
                    </button>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkGenerateAllModal">
                        <i class="fas fa-certificate me-1"></i> Wygeneruj zaświadczenia dla wszystkich
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generateAllPdfsModal" title="Generowanie w tle – wymaga działającego workera kolejki (sail artisan queue:work)">
                        <i class="fas fa-file-pdf me-1"></i> Wygeneruj pliki PDF dla wszystkich zaświadczeń
                    </button>
                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#deletePdfFilesModal" title="Usuwa pliki PDF, zachowuje rekordy i numery – potem można wygenerować od nowa">
                        <i class="fas fa-file-pdf me-1"></i> Usuń tylko pliki PDF zaświadczeń
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                        <i class="fas fa-trash me-1"></i> Usuń zaświadczenia
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bulkEmailListModal">
                        <i class="fas fa-envelope me-1"></i> Wyślij e-maile: lista zaświadczeń
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bulkEmailSingleModal">
                        <i class="fas fa-envelope me-1"></i> Wyślij e-maile: to zaświadczenie
                    </button>
                    <a href="{{ route('participants.download-pdf', array_merge(['course' => $course], request()->query())) }}" class="btn btn-info" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Pobierz listę uczestników PDF
                    </a>
                    <a href="{{ route('participants.download-registry', $course) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-file-pdf me-1"></i> Pobierz rejestr zaświadczeń
                    </a>
                </div>
            </div>
            @php
                $currentCertSort = request('sort_certificate');
                $certSortBaseQuery = request()->except('page', 'sort_certificate');
            @endphp
            <div class="col-md-4 text-end">
                <div class="d-flex align-items-center gap-2 justify-content-end">
                    <a href="{{ route('participants.index', ['course' => $course->id, 'sort' => 'asc']) }}" class="btn btn-outline-info">
                        <i class="fas fa-sort-alpha-down me-1"></i> Sortuj alfabetycznie
                    </a>
                    <div class="btn-group" role="group" aria-label="Sortowanie numerów certyfikatów">
                        <a href="{{ route('participants.index', array_merge(['course' => $course->id], $certSortBaseQuery, ['sort_certificate' => 'asc'])) }}"
                           class="btn btn-outline-primary {{ $currentCertSort === 'asc' ? 'active' : '' }}"
                           title="Rosnąco po numerze zaświadczenia">
                            <i class="fas fa-sort-numeric-down"></i>
                        </a>
                        <a href="{{ route('participants.index', array_merge(['course' => $course->id], $certSortBaseQuery, ['sort_certificate' => 'desc'])) }}"
                           class="btn btn-outline-primary {{ $currentCertSort === 'desc' ? 'active' : '' }}"
                           title="Malejąco po numerze zaświadczenia">
                            <i class="fas fa-sort-numeric-down-alt"></i>
                        </a>
                    </div>
                    @if($currentCertSort)
                        <a href="{{ route('participants.index', array_merge(['course' => $course->id], $certSortBaseQuery)) }}"
                           class="btn btn-outline-secondary"
                           title="Wyczyść sortowanie numerów">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                    <div class="d-flex align-items-center gap-2">
                        <label for="per_page" class="form-label mb-0 fw-bold">Wyświetl:</label>
                        <form method="GET" action="{{ route('participants.index', $course) }}" class="d-flex align-items-center">
                            @foreach(request()->query() as $key => $value)
                                @if($key !== 'per_page')
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <select name="per_page" class="form-select" style="width: auto;" onchange="this.form.submit()">
                                <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200</option>
                                <option value="500" {{ request('per_page') == 500 ? 'selected' : '' }}>500</option>
                                <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>Wszyscy</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>        

        <!-- Komunikaty -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Wyszukiwarka -->
        <div class="mb-4">
            <form method="GET" action="{{ route('participants.index', $course) }}" class="mb-3">
                <div class="row">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Szukaj po imieniu, nazwisku, email lub miejscu urodzenia..."
                                   value="{{ request('search') }}"
                                   autocomplete="off">
                            @if(request('search'))
                                <a href="{{ route('participants.index', $course) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Szukaj
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            @if(request('search'))
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <span>Wyniki wyszukiwania dla: <strong>"{{ request('search') }}"</strong> 
                    (znaleziono: {{ $participants->total() }} {{ $participants->total() == 1 ? 'uczestnik' : ($participants->total() < 5 ? 'uczestników' : 'uczestników') }})</span>
                    <a href="{{ route('participants.index', $course) }}" class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="fas fa-times me-1"></i> Wyczyść
                    </a>
                </div>
            @endif
        </div>

        @if($participants->count() > 0)
            <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Nazwisko</th>                    
                    <th>Imię</th>
                    <th>Email</th>
                    <th>Data urodzenia</th>
                    <th>Miejsce urodzenia</th>
                    <th>Data wygaśnięcia dostępu</th>
                    <th>
                        @php
                            $currentCertSortHeader = request('sort_certificate');
                            $nextCertSortDirection = $currentCertSortHeader === 'asc' ? 'desc' : 'asc';
                            $certSortToggleQuery = request()->except('page', 'sort_certificate');
                        @endphp
                        <a href="{{ route('participants.index', array_merge(['course' => $course->id], $certSortToggleQuery, ['sort_certificate' => $nextCertSortDirection])) }}"
                           class="text-white text-decoration-none">
                            Nr zaświadczenia
                            @if($currentCertSortHeader === 'asc')
                                <i class="fas fa-sort-numeric-down ms-1"></i>
                            @elseif($currentCertSortHeader === 'desc')
                                <i class="fas fa-sort-numeric-down-alt ms-1"></i>
                            @else
                                <i class="fas fa-sort ms-1 text-white-50"></i>
                            @endif
                        </a>
                    </th>                    
                    <th>Zaświadczenie</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($participants as $index => $participant)
                    <tr>
                        <td>{{ $participant->order }}</td>
                        <td>{{ $participant->id }}</td>
                        <td>{{ $participant->last_name }}</td>                        
                        <td>{{ $participant->first_name }}</td>
                        <td>{{ $participant->email ?? 'Brak' }}</td>
                        <td>{{ $participant->birth_date ? $participant->birth_date->format('Y-m-d') : 'Brak' }}</td>
                        <td>{{ $participant->birth_place ?? 'Brak' }}</td>
                        <td>
                            @if ($participant->access_expires_at)
                                <span class="badge {{ $participant->hasExpiredAccess() ? 'bg-danger' : ($participant->hasActiveAccess() ? 'bg-success' : 'bg-warning') }}" title="UTC: {{ $participant->access_expires_at->format('d.m.Y H:i') }} | Lokalny: {{ $participant->access_expires_at->setTimezone('Europe/Warsaw')->format('d.m.Y H:i') }}">
                                    {{ $participant->access_expires_at->format('d.m.Y H:i') }}
                                    @if ($participant->hasExpiredAccess())
                                        <br><small>Wygasł</small>
                                    @elseif ($participant->hasActiveAccess())
                                        <br><small>Aktywny</small>
                                    @else
                                        <br><small>{{ $participant->getRemainingAccessTime() }}</small>
                                    @endif
                                </span>
                            @else
                                <span class="badge bg-info">Bezterminowy</span>
                            @endif
                        </td>
                        <td>
                            @if ($participant->certificate)
                                <a href="{{ route('certificates.generate', $participant->id) }}">
                                    {{ $participant->certificate->certificate_number }}
                                </a>
                                @if(!empty($participant->certificate->file_path))
                                    <a href="{{ route('certificates.download-pdf', $participant->certificate) }}" class="text-success ms-1 text-decoration-none" title="Pobierz plik PDF z serwera (bez generowania)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                @endif
                                @php
                                    $downloadCount = (int) ($participant->certificate->download_count ?? 0);
                                    $lastDownloadedAt = $participant->certificate->last_downloaded_at ?? null;
                                @endphp
                                <div class="mt-1">
                                    <span class="badge {{ $downloadCount > 0 ? 'bg-success' : 'bg-secondary' }}"
                                          title="{{ $downloadCount > 0 && $lastDownloadedAt ? 'Ostatnie pobranie: ' . $lastDownloadedAt->format('d.m.Y H:i') : '' }}">
                                        Pobrane: {{ $downloadCount > 0 ? 'TAK' : 'NIE' }}@if($downloadCount > 0 && $lastDownloadedAt) ({{ $lastDownloadedAt->format('d.m.Y H:i') }})@endif
                                    </span>
                                </div>
                            @else
                                Brak zaświadczenia
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                @if ($participant->certificate)
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteCertificateModal{{ $participant->certificate->id }}">
                                        <i class="bi bi-trash"></i> Usuń
                                    </button>
                                    @if(!empty($participant->certificate->file_path))
                                        <form action="{{ route('certificates.delete-pdf', $participant->certificate) }}" method="POST" class="d-inline" onsubmit="return confirm('Usunąć tylko plik PDF tego zaświadczenia? Numer zaświadczenia zostanie zachowany – potem możesz wygenerować plik ponownie (np. po poprawce danych).');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Usuwa plik PDF, zachowuje zaświadczenie – potem wygeneruj ponownie">
                                                <i class="bi bi-file-earmark-pdf"></i> Usuń PDF
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <a href="{{ route('certificates.store', $participant) }}" class="btn btn-primary btn-sm">Generuj</a>
                                @endif
                                @php
                                    $certToken = !empty($participant->email) ? ($downloadTokensByEmail[strtolower(trim($participant->email))] ?? null) : null;
                                @endphp
                                @if($certToken)
                                    <a href="{{ $pneduFrontendUrl }}/certificates/{{ $certToken }}" class="btn btn-link btn-sm p-0 text-start" target="_blank" rel="noopener" title="Lista zaświadczeń (pnedu.pl)">Zaświadczenia (pnedu)</a>
                                    <a href="{{ $pneduFrontendUrl }}/certificate/{{ $certToken }}/{{ $course->id }}" class="btn btn-link btn-sm p-0 text-start" target="_blank" rel="noopener" title="To konkretne zaświadczenie (pnedu.pl)">Zaświadczenie (pnedu)</a>
                                    <form action="{{ route('participants.send-certificate-link', [$course, $participant]) }}" method="POST" class="d-inline" onsubmit="return confirm('Wysłać e-mail z linkiem do zaświadczeń?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm px-2 py-0 small text-nowrap">Wyślij e-mail</button>
                                    </form>
                                    <form action="{{ route('participants.send-single-certificate-link', [$course, $participant]) }}" method="POST" class="d-inline" onsubmit="return confirm('Wysłać e-mail z linkiem do tego konkretnego zaświadczenia?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm px-2 py-0 small text-nowrap">Wyślij e-mail to</button>
                                    </form>
                                @endif
                            </div>
                        </td>                         
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <a href="{{ route('participants.edit', [$course, $participant]) }}" class="btn btn-info btn-sm" style="min-width: 80px;">Podgląd</a>
                                <button type="button" class="btn btn-danger btn-sm" style="min-width: 80px;" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal{{ $participant->id }}">
                                    <i class="bi bi-trash"></i> Usuń
                                </button>
                            </div>
                        </td>                       
                    </tr>
                @endforeach
            </tbody>
            </table>
        @else
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                @if(request('search'))
                    <h4 class="text-muted">Brak wyników wyszukiwania</h4>
                    <p class="text-muted">Nie znaleziono uczestników pasujących do frazy: <strong>"{{ request('search') }}"</strong></p>
                    <a href="{{ route('participants.index', $course) }}" class="btn btn-primary">
                        <i class="fas fa-list me-1"></i> Pokaż wszystkich uczestników
                    </a>
                @else
                    <h4 class="text-muted">Brak uczestników</h4>
                    <p class="text-muted">Ten kurs nie ma jeszcze żadnych uczestników.</p>
                @endif
            </div>
        @endif

        @if($participants->count() > 0)
            <div class="mt-3">
                {{ $participants->appends(request()->query())->links() }}
            </div>
        @endif

        {{-- Modale potwierdzenia usunięcia uczestników --}}
        @foreach ($participants as $participant)
        <div class="modal fade" id="deleteModal{{ $participant->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $participant->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel{{ $participant->id }}">
                            <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz usunąć uczestnika <strong>#{{ $participant->id }}</strong>?</p>
                        <div class="bg-light p-3 rounded">
                            <h6 class="mb-2">Szczegóły uczestnika:</h6>
                            <ul class="mb-0">
                                <li><strong>Imię i nazwisko:</strong> {{ $participant->first_name }} {{ $participant->last_name }}</li>
                                <li><strong>Email:</strong> {{ $participant->email ?? 'Brak' }}</li>
                                <li><strong>Data urodzenia:</strong> {{ $participant->birth_date ? $participant->birth_date->format('Y-m-d') : 'Brak' }}</li>
                                <li><strong>Miejsce urodzenia:</strong> {{ $participant->birth_place ?? 'Brak' }}</li>
                                <li><strong>Szkolenie:</strong> {!! $course->title !!}</li>
                                <li><strong>Data wygaśnięcia dostępu:</strong> {{ $participant->access_expires_at ? $participant->access_expires_at->format('d.m.Y H:i') : 'Bezterminowy' }}</li>
                            </ul>
                        </div>
                        <p class="text-muted mt-3">
                            <i class="bi bi-info-circle"></i>
                            Uczestnik zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form action="{{ route('participants.destroy', [$course, $participant]) }}" 
                              method="POST" 
                              class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Usuń uczestnika
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Modale potwierdzenia usunięcia zaświadczeń --}}
        @foreach ($participants as $participant)
            @if ($participant->certificate)
            <div class="modal fade" id="deleteCertificateModal{{ $participant->certificate->id }}" tabindex="-1" aria-labelledby="deleteCertificateModalLabel{{ $participant->certificate->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteCertificateModalLabel{{ $participant->certificate->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia zaświadczenia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć zaświadczenie <strong>#{{ $participant->certificate->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły zaświadczenia:</h6>
                                <ul class="mb-0">
                                    <li><strong>Numer zaświadczenia:</strong> {{ $participant->certificate->certificate_number ?? 'Brak numeru' }}</li>
                                    <li><strong>Uczestnik:</strong> {{ $participant->first_name }} {{ $participant->last_name }}</li>
                                    <li><strong>Email:</strong> {{ $participant->email ?? 'Brak' }}</li>
                                    <li><strong>Szkolenie:</strong> {!! $course->title !!}</li>
                                    <li><strong>Data wygenerowania:</strong> {{ $participant->certificate->created_at ? $participant->certificate->created_at->format('d.m.Y H:i') : 'Nieznana' }}</li>
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
                            <form action="{{ route('certificates.destroy', $participant->certificate->id) }}" 
                                  method="POST" 
                                  class="d-inline">
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

        {{-- Modal: Wygeneruj zaświadczenia (tylko z kompletnymi danymi) --}}
        <div class="modal fade" id="bulkGenerateModal" tabindex="-1" aria-labelledby="bulkGenerateModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="bulkGenerateModalLabel">
                            <i class="bi bi-exclamation-triangle"></i> Wygeneruj zaświadczenia
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz wygenerować zaświadczenia dla wszystkich uczestników z <strong>kompletnymi danymi</strong> (Nazwisko, Imię, Data urodzenia, Miejsce urodzenia)?</p>
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle"></i>
                            Uczestnicy, którzy już mają zaświadczenia, otrzymają kolejne.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <a href="{{ route('certificates.bulk-generate', $course) }}" class="btn btn-warning">
                            <i class="fas fa-certificate me-1"></i> Wygeneruj zaświadczenia
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Wygeneruj zaświadczenia dla wszystkich --}}
        <div class="modal fade" id="bulkGenerateAllModal" tabindex="-1" aria-labelledby="bulkGenerateAllModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="bulkGenerateAllModalLabel">
                            <i class="bi bi-exclamation-triangle"></i> Wygeneruj zaświadczenia dla wszystkich
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz wygenerować zaświadczenia dla <strong>WSZYSTKICH</strong> uczestników (bez względu na kompletność danych)?</p>
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle"></i>
                            Uczestnicy, którzy już mają zaświadczenia, otrzymają kolejne.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <a href="{{ route('certificates.bulk-generate-all', $course) }}" class="btn btn-warning">
                            <i class="fas fa-certificate me-1"></i> Wygeneruj zaświadczenia dla wszystkich
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Wygeneruj pliki PDF w tle (kolejka) --}}
        <div class="modal fade" id="generateAllPdfsModal" tabindex="-1" aria-labelledby="generateAllPdfsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="generateAllPdfsModalLabel">
                            <i class="fas fa-file-pdf me-2"></i> Wygeneruj pliki PDF dla wszystkich zaświadczeń
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Zlecić generowanie plików PDF w tle (kolejka)? Działa także przy 1000+ uczestników.</p>
                        <p class="text-warning mb-0">
                            <i class="bi bi-info-circle"></i>
                            Upewnij się, że worker kolejki działa: <code>sail artisan queue:work</code>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form id="generateAllPdfsForm" action="{{ route('certificates.generate-all-pdfs', $course) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-pdf me-1"></i> Zleć generowanie
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Usuń tylko pliki PDF zaświadczeń --}}
        <div class="modal fade" id="deletePdfFilesModal" tabindex="-1" aria-labelledby="deletePdfFilesModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="deletePdfFilesModalLabel">
                            <i class="bi bi-exclamation-triangle"></i> Usuń tylko pliki PDF zaświadczeń
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Usunąć tylko pliki PDF z dysku (zachowując numery zaświadczeń)?</p>
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle"></i>
                            Po edycji danych szkolenia możesz potem wygenerować pliki ponownie.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form action="{{ route('certificates.delete-pdf-files', $course) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-file-pdf me-1"></i> Usuń pliki PDF
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Usuń wszystkie zaświadczenia --}}
        <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="bulkDeleteModalLabel">
                            <i class="bi bi-exclamation-triangle"></i> Usuń zaświadczenia
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz usunąć <strong>WSZYSTKIE</strong> zaświadczenia dla tego szkolenia?</p>
                        <p class="text-danger mb-0">
                            <i class="bi bi-info-circle"></i>
                            Ta operacja jest nieodwracalna!
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <a href="{{ route('certificates.bulk-delete', $course) }}" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń zaświadczenia
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Masowa wysyłka e-maili – lista zaświadczeń --}}
        <div class="modal fade" id="bulkEmailListModal" tabindex="-1" aria-labelledby="bulkEmailListModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title" id="bulkEmailListModalLabel">
                            <i class="fas fa-envelope me-2"></i> Wyślij e-maile: lista zaświadczeń
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Wyśle e-mail do uczestników z linkiem do listy wszystkich ich zaświadczeń (pnedu).</p>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Tryb wysyłki</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_email_list_mode" id="bulk_email_list_mode_unsent" value="unsent" checked>
                                <label class="form-check-label" for="bulk_email_list_mode_unsent">Wyślij tylko do tych, do których jeszcze nie wysłano</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_email_list_mode" id="bulk_email_list_mode_resend" value="resend_all">
                                <label class="form-check-label" for="bulk_email_list_mode_resend">Wyślij ponownie do wszystkich</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_email_list_mode" id="bulk_email_list_mode_not_downloaded" value="not_downloaded">
                                <label class="form-check-label" for="bulk_email_list_mode_not_downloaded">Wyślij tylko do tych, którzy jeszcze nie pobrali</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form action="{{ route('participants.send-certificate-links-bulk', $course) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="type" value="list_link">
                            <input type="hidden" name="mode" id="bulk_email_list_mode_input" value="unsent">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-paper-plane me-1"></i> Wyślij
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Masowa wysyłka e-maili – konkretne zaświadczenie --}}
        <div class="modal fade" id="bulkEmailSingleModal" tabindex="-1" aria-labelledby="bulkEmailSingleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title" id="bulkEmailSingleModalLabel">
                            <i class="fas fa-envelope me-2"></i> Wyślij e-maile: to zaświadczenie
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Wyśle e-mail do uczestników z linkiem do zaświadczenia dla tego szkolenia (pnedu). W treści będzie tytuł szkolenia i prowadzący.</p>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Tryb wysyłki</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_email_single_mode" id="bulk_email_single_mode_unsent" value="unsent" checked>
                                <label class="form-check-label" for="bulk_email_single_mode_unsent">Wyślij tylko do tych, do których jeszcze nie wysłano</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_email_single_mode" id="bulk_email_single_mode_resend" value="resend_all">
                                <label class="form-check-label" for="bulk_email_single_mode_resend">Wyślij ponownie do wszystkich</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_email_single_mode" id="bulk_email_single_mode_not_downloaded" value="not_downloaded">
                                <label class="form-check-label" for="bulk_email_single_mode_not_downloaded">Wyślij tylko do tych, którzy jeszcze nie pobrali</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Anuluj
                        </button>
                        <form action="{{ route('participants.send-certificate-links-bulk', $course) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="type" value="single_certificate">
                            <input type="hidden" name="mode" id="bulk_email_single_mode_input" value="unsent">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-paper-plane me-1"></i> Wyślij
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Import CSV -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="fas fa-file-csv me-2"></i>Import uczestników z CSV
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('participants.import', $course) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-bold">
                                <i class="fas fa-upload me-1"></i>Wybierz plik CSV
                            </label>
                            <input type="file" class="form-control form-control-lg" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Plik CSV powinien zawierać kolumny: <strong>ID</strong>, <strong>E-mail uczestnika</strong>, <strong>Imię i nazwisko</strong>, <strong>Numer telefonu</strong>, <strong>Dostęp wygasa</strong>
                            </div>
                        </div>
                        <div class="alert alert-info border-0">
                            <div class="d-flex">
                                <i class="fas fa-lightbulb me-3 mt-1 text-warning"></i>
                                <div>
                                    <strong>Format pliku CSV:</strong>
                                    <div class="mt-2">
                                        <code class="small">
                                            ID,"E-mail uczestnika","Imię i nazwisko","Numer telefonu",Postęp,"Dopisano do kursu","Dostęp wygasa"<br>
                                            2,waldemar.grabowski@hostnet.pl,"Waldemar Grabowski",501654274,"0 / 1 (0%)","2025-08-22 22:42:40","2025-10-26 22:59:00"
                                        </code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Anuluj
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-1"></i>Importuj
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Import Certificates CSV -->
    <div class="modal fade" id="importCertificatesModal" tabindex="-1" aria-labelledby="importCertificatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="importCertificatesModalLabel">
                        <i class="fas fa-certificate me-2"></i>Import zaświadczeń z PUBLIGO CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('certificates.import', $course) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-4">
                            <label for="certificates_csv" class="form-label fw-bold">
                                <i class="fas fa-upload me-1"></i>Wybierz plik CSV z numerami zaświadczeń
                            </label>
                            <input type="file" class="form-control form-control-lg" id="certificates_csv" name="certificates_csv" accept=".csv" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Plik powinien pochodzić z eksportu PUBLIGO i zawierać kolumny: <strong>Id</strong>, <strong>Kurs</strong>, <strong>Imię i nazwisko</strong>, <strong>Email</strong>, <strong>Numer certyfikatu</strong>, <strong>Data utworzenia</strong>.
                            </div>
                        </div>
                        <div class="alert alert-warning border-0">
                            <div class="d-flex">
                                <i class="fas fa-lightbulb me-3 mt-1"></i>
                                <div>
                                    <strong>Co się wydarzy po imporcie?</strong>
                                    <ul class="mb-0 mt-2 ps-3">
                                        <li>System dopasuje zaświadczenia do uczestników po adresie e-mail.</li>
                                        <li>Jeśli uczestnik nie istnieje na liście, zostanie utworzony automatycznie.</li>
                                        <li>Numery certyfikatów zostaną zapisane dokładnie takie jak w pliku.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Anuluj
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-file-import me-1"></i>Importuj zaświadczenia
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        var pdfProgressIntervalId = null;
        var emailProgressIntervalId = null;

        // Bulk email modals: sync selected radio -> hidden input
        (function() {
            function bind(groupName, hiddenInputId) {
                var hidden = document.getElementById(hiddenInputId);
                if (!hidden) return;
                document.querySelectorAll('input[name="' + groupName + '"]').forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        hidden.value = this.value;
                    });
                });
            }
            bind('bulk_email_list_mode', 'bulk_email_list_mode_input');
            bind('bulk_email_single_mode', 'bulk_email_single_mode_input');
        })();

        function clearModalBackdrop() {
            document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }

        function emailTypeLabel(type) {
            if (type === 'single_certificate') return 'to zaświadczenie';
            if (type === 'list_link') return 'lista zaświadczeń';
            return type || 'e-maile';
        }

        function updateEmailProgress(alertEl, type) {
            if (!alertEl) return;
            var statusUrl = alertEl.getAttribute('data-status-url');
            var textEl = alertEl.querySelector('.sending-emails-text');
            var cancelWrap = alertEl.querySelector('.sending-emails-cancel-wrap');
            if (!statusUrl || !textEl) return;

            var url = new URL(statusUrl, window.location.origin);
            url.searchParams.set('type', type);

            fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.active) {
                        // zakończ polling i pokaż podsumowanie
                        if (emailProgressIntervalId) { clearInterval(emailProgressIntervalId); emailProgressIntervalId = null; }
                        if (cancelWrap) cancelWrap.classList.add('d-none');

                        if (data.state === 'finished') {
                            alertEl.classList.remove('alert-info', 'alert-warning', 'alert-danger');
                            alertEl.classList.add('alert-success');
                            textEl.innerHTML =
                                '<strong>Wysyłka zakończona (' + emailTypeLabel(data.type) + ').</strong> ' +
                                'Wysłano/obsłużono <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                'błędów: <strong>' + data.failed + '</strong>.';
                            setTimeout(function() { alertEl.classList.add('d-none'); }, 6000);
                        } else if (data.state === 'cancelled') {
                            alertEl.classList.remove('alert-info', 'alert-success', 'alert-danger');
                            alertEl.classList.add('alert-warning');
                            textEl.innerHTML =
                                '<strong>Wysyłka przerwana (' + emailTypeLabel(data.type) + ').</strong> ' +
                                'Wykonano <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                                'błędów: <strong>' + data.failed + '</strong>.';
                            setTimeout(function() { alertEl.classList.add('d-none'); }, 6000);
                        } else {
                            // brak aktywnej wysyłki
                            alertEl.classList.add('d-none');
                        }
                        return;
                    }
                    textEl.innerHTML =
                        '<strong>Trwa wysyłanie e-maili (' + emailTypeLabel(data.type) + ').</strong> ' +
                        'Wykonano <strong>' + data.processed + ' z ' + data.total + '</strong>, ' +
                        'błędów: <strong>' + data.failed + '</strong>.';
                })
                .catch(function() {});
        }

        function showEmailCancelButtonAndWire(alertEl, type) {
            var cancelWrap = alertEl && alertEl.querySelector('.sending-emails-cancel-wrap');
            var textEl = alertEl && alertEl.querySelector('.sending-emails-text');
            var cancelUrl = alertEl && alertEl.getAttribute('data-cancel-url');
            if (!cancelWrap || !cancelUrl) return;
            cancelWrap.classList.remove('d-none');
            var btn = cancelWrap.querySelector('#cancelEmailBatchBtn');
            if (!btn || btn.dataset.wired) return;
            btn.dataset.wired = '1';
            btn.addEventListener('click', function() {
                btn.disabled = true;
                var token = (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content')) || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value);
                var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
                if (token) headers['X-CSRF-TOKEN'] = token;
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
                var body = new URLSearchParams({ type: type, _token: token || '' });

                fetch(cancelUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers,
                    body: body
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (emailProgressIntervalId) { clearInterval(emailProgressIntervalId); emailProgressIntervalId = null; }
                    cancelWrap.classList.add('d-none');
                    if (textEl) textEl.innerHTML = '<strong>Wysyłka przerwana.</strong>';
                }).catch(function() {
                    btn.disabled = false;
                });
            });
        }

        function startEmailPolling(alertEl, type) {
            if (!alertEl) return;
            alertEl.classList.remove('d-none');
            updateEmailProgress(alertEl, type);
            emailProgressIntervalId = setInterval(function() { updateEmailProgress(alertEl, type); }, 2000);
            showEmailCancelButtonAndWire(alertEl, type);
        }

        function updateProgressText(alertEl) {
            var progressUrl = alertEl && alertEl.getAttribute('data-progress-url');
            var textEl = alertEl && alertEl.querySelector('.generating-pdfs-text');
            if (!progressUrl || !textEl) return;
            fetch(progressUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    textEl.innerHTML = '<strong>Trwa generowanie plików PDF w tle (kolejka).</strong> Wygenerowano <strong>' + data.with_file + ' z ' + data.total + '</strong> plików. Na koniec zobaczysz komunikat z potwierdzeniem.';
                })
                .catch(function() {});
        }

        function showCancelButtonAndWire(alertEl) {
            var cancelWrap = alertEl && alertEl.querySelector('.generating-pdfs-cancel-wrap');
            var cancelUrl = alertEl && alertEl.getAttribute('data-cancel-url');
            var textEl = alertEl && alertEl.querySelector('.generating-pdfs-text');
            if (!cancelWrap || !cancelUrl) return;
            cancelWrap.classList.remove('d-none');
            var btn = cancelWrap.querySelector('#cancelPdfGenerationBtn');
            if (!btn || btn.dataset.wired) return;
            btn.dataset.wired = '1';
            btn.addEventListener('click', function() {
                btn.disabled = true;
                var token = (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content')) || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value);
                var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
                if (token) headers['X-CSRF-TOKEN'] = token;
                var body = token ? new URLSearchParams({ _token: token }) : null;
                if (body) headers['Content-Type'] = 'application/x-www-form-urlencoded';
                fetch(cancelUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers,
                    body: body
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (pdfProgressIntervalId) { clearInterval(pdfProgressIntervalId); pdfProgressIntervalId = null; }
                    cancelWrap.classList.add('d-none');
                    if (textEl) textEl.innerHTML = '<strong>Generowanie przerwane.</strong>';
                }).catch(function() {
                    btn.disabled = false;
                });
            });
        }

        function startBatchProgressPolling(alertEl) {
            if (!alertEl) return;
            alertEl.classList.remove('d-none');
            updateProgressText(alertEl);
            pdfProgressIntervalId = setInterval(function() { updateProgressText(alertEl); }, 2000);
            fetch(alertEl.getAttribute('data-status-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) { if (data.active) showCancelButtonAndWire(alertEl); });
        }

        document.getElementById('generateAllPdfsForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            var form = this;
            var modalEl = document.getElementById('generateAllPdfsModal');
            var alertEl = document.getElementById('generatingPdfsAlert');
            function startFetch() {
                if (alertEl) alertEl.classList.remove('d-none');
                var progressUrl = alertEl && alertEl.getAttribute('data-progress-url');
                var textEl = alertEl && alertEl.querySelector('.generating-pdfs-text');
                var progressIntervalId = null;
                if (progressUrl && textEl) {
                    function updateProgress() {
                        fetch(progressUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                textEl.innerHTML = '<strong>Trwa generowanie plików PDF.</strong> Wygenerowano <strong>' + data.with_file + ' z ' + data.total + '</strong> plików. Na koniec zobaczysz komunikat z potwierdzeniem.';
                            })
                            .catch(function() {});
                    }
                    updateProgress();
                    progressIntervalId = setInterval(updateProgress, 2000);
                    pdfProgressIntervalId = progressIntervalId;
                    fetch(alertEl.getAttribute('data-status-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(data) { if (data.active) showCancelButtonAndWire(alertEl); });
                }
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                }).then(function(res) {
                    if (progressIntervalId) clearInterval(progressIntervalId);
                    pdfProgressIntervalId = null;
                    if (res.redirected) {
                        window.location.href = res.url;
                    } else {
                        window.location.reload();
                    }
                }).catch(function() {
                    if (progressIntervalId) clearInterval(progressIntervalId);
                    pdfProgressIntervalId = null;
                    if (alertEl) {
                        alertEl.classList.remove('alert-info');
                        alertEl.classList.add('alert-danger');
                        alertEl.innerHTML = '<strong>Błąd połączenia.</strong> Odśwież stronę i spróbuj ponownie.';
                    }
                });
            }
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                if (modal) {
                    modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                        modalEl.removeEventListener('hidden.bs.modal', onHidden);
                        clearModalBackdrop();
                        startFetch();
                    }, { once: true });
                    modal.hide();
                } else {
                    clearModalBackdrop();
                    startFetch();
                }
            } else {
                clearModalBackdrop();
                startFetch();
            }
        });

        (function checkBatchOnLoad() {
            var alertEl = document.getElementById('generatingPdfsAlert');
            var statusUrl = alertEl && alertEl.getAttribute('data-status-url');
            if (!statusUrl) return;
            fetch(statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.active) startBatchProgressPolling(alertEl);
                })
                .catch(function() {});
        })();

        (function checkOtherCourseBatch() {
            var alertEl = document.getElementById('otherCoursePdfGenerationAlert');
            var statusAnyUrl = alertEl && alertEl.getAttribute('data-status-any-url');
            var currentCourseId = alertEl && parseInt(alertEl.getAttribute('data-current-course-id'), 10);
            if (!statusAnyUrl || isNaN(currentCourseId)) return;
            var textEl = alertEl && alertEl.querySelector('.other-course-pdfs-text');
            function update() {
                fetch(statusAnyUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.active || data.course_id === currentCourseId) {
                            alertEl.classList.add('d-none');
                            return;
                        }
                        var rawTitle = data.course_title || 'kurs #' + data.course_id;
                        var title = String(rawTitle).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        var url = data.participants_url || '';
                        if (url) {
                            textEl.innerHTML = '<strong>Trwa generowanie plików PDF w tle dla innego szkolenia:</strong> <a href="' + url.replace(/"/g, '&quot;') + '" class="alert-link">' + title + '</a>. Poczekaj na zakończenie przed zleceniem kolejnego.';
                        } else {
                            textEl.innerHTML = '<strong>Trwa generowanie plików PDF w tle dla innego szkolenia:</strong> „' + title + '”. Poczekaj na zakończenie przed zleceniem kolejnego.';
                        }
                        alertEl.classList.remove('d-none');
                    })
                    .catch(function() {});
            }
            update();
            setInterval(update, 10000);
        })();

        (function checkEmailBatchesOnLoad() {
            var alertEl = document.getElementById('sendingEmailsAlert');
            var statusUrl = alertEl && alertEl.getAttribute('data-status-url');
            if (!alertEl || !statusUrl) return;

            function check(type) {
                var url = new URL(statusUrl, window.location.origin);
                url.searchParams.set('type', type);
                return fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) { return data.active ? type : null; })
                    .catch(function() { return null; });
            }

            Promise.all([check('single_certificate'), check('list_link')]).then(function(results) {
                var activeType = results[0] || results[1];
                if (activeType) startEmailPolling(alertEl, activeType);
            });
        })();
    </script>
    @endpush
</x-app-layout>

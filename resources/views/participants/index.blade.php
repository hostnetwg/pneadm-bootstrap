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
                    <a href="{{ route('certificates.bulk-generate', $course) }}" class="btn btn-warning" onclick="return confirm('Czy na pewno chcesz wygenerować zaświadczenia dla wszystkich uczestników bez zaświadczeń?')">
                        <i class="fas fa-certificate me-1"></i> Wygeneruj zaświadczenia
                    </a>
                    <a href="{{ route('certificates.bulk-delete', $course) }}" class="btn btn-danger" onclick="return confirm('Czy na pewno chcesz usunąć WSZYSTKIE zaświadczenia dla tego szkolenia? Ta operacja jest nieodwracalna!')">
                        <i class="fas fa-trash me-1"></i> Usuń zaświadczenia
                    </a>
                    <a href="{{ route('participants.download-pdf', array_merge(['course' => $course], request()->query())) }}" class="btn btn-info" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Pobierz listę uczestników PDF
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
                        <td>{{ $participant->birth_date ?? 'Brak' }}</td>
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
                            @else
                                Brak zaświadczenia
                            @endif
                        </td>
                        <td>
                            @if ($participant->certificate)
                                <button type="button" class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteCertificateModal{{ $participant->certificate->id }}">
                                    <i class="bi bi-trash"></i> Usuń
                                </button>
                            @else
                                <a href="{{ route('certificates.store', $participant) }}" class="btn btn-primary btn-sm">Generuj</a>
                            @endif
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
                                <li><strong>Data urodzenia:</strong> {{ $participant->birth_date ?? 'Brak' }}</li>
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
</x-app-layout>

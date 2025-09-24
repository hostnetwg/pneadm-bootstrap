<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-semibold fs-4 text-dark mb-1">
                    <i class="fas fa-users me-2"></i>Lista uczestników
                </h2>
                <p class="text-muted mb-0">
                    <strong>{{ $course->title }}</strong>
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
                    <a href="{{ route('courses.edit', $course->id) }}" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> Powrót do kursu
                    </a>
                    <a href="{{ route('participants.create', $course) }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Dodaj uczestnika
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-csv me-1"></i> Importuj z CSV
                    </button>
                    <a href="{{ route('certificates.bulk-generate', $course) }}" class="btn btn-warning" onclick="return confirm('Czy na pewno chcesz wygenerować zaświadczenia dla wszystkich uczestników bez certyfikatów?')">
                        <i class="fas fa-certificate me-1"></i> Wygeneruj zaświadczenia
                    </a>
                    <a href="{{ route('certificates.bulk-delete', $course) }}" class="btn btn-danger" onclick="return confirm('Czy na pewno chcesz usunąć WSZYSTKIE zaświadczenia dla tego szkolenia? Ta operacja jest nieodwracalna!')">
                        <i class="fas fa-trash me-1"></i> Usuń zaświadczenia
                    </a>
                    <a href="{{ route('certificates.download-list', $course) }}" class="btn btn-info">
                        <i class="fas fa-file-pdf me-1"></i> Pobierz listę zaświadczeń w PDF
                    </a>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex align-items-center gap-2 justify-content-end">
                    <a href="{{ route('participants.index', ['course' => $course->id, 'sort' => 'asc']) }}" class="btn btn-outline-info">
                        <i class="fas fa-sort-alpha-down me-1"></i> Sortuj alfabetycznie
                    </a>
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
                    <th>Nr certyfikatu</th>                    
                    <th>Certyfikat</th>
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
                                Brak certyfikatu
                            @endif
                            </td>
                        </td>
                        <td>
                            @if ($participant->certificate)
                                <form action="{{ route('certificates.destroy', $participant->certificate->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Usunąć certyfikat?')">Usuń</button>
                                </form>
                            @else
                                <a href="{{ route('certificates.store', $participant) }}" class="btn btn-primary btn-sm">Generuj</a>
                            @endif
                        </td>                         
                        <td>
                            <a href="{{ route('participants.edit', [$course, $participant]) }}" class="btn btn-warning btn-sm">Edytuj</a>
                            <form action="{{ route('participants.destroy', [$course, $participant]) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Usunąć uczestnika?')">Usuń</button>
                            </form>
                        </td>                       
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-3">
            {{ $participants->links() }}
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
</x-app-layout>

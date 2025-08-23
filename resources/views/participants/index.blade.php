<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Lista uczestników - {{ $course->title }} ({{ date('d.m.Y H:i', strtotime($course->start_date)) }})
        </h2>
    </x-slot>

    <div class="container py-3">
        <div class="d-flex justify-content-between mb-3">
            <div>
                <a href="{{ route('courses.index') }}" class="btn btn-secondary me-2">Powrót do listy szkoleń</a>
                <a href="{{ route('participants.create', $course) }}" class="btn btn-primary">Dodaj uczestnika</a>
            </div>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-file-csv me-1"></i> Importuj z CSV
                </button>
            </div>
        </div>
        <!-- Przycisk sortowania -->
        <div class="mb-3">
            <a href="{{ route('participants.index', ['course' => $course->id, 'sort' => 'asc']) }}" class="btn btn-info">Sortuj alfabetycznie</a>
        </div>        

        <!-- Komunikaty -->
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import uczestników z CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('participants.import', $course) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Wybierz plik CSV</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                Plik CSV powinien zawierać kolumny: ID, E-mail uczestnika, Imię i nazwisko, Numer telefonu, Dostęp wygasa
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <strong>Format pliku CSV:</strong><br>
                            <small>
                                ID,"E-mail uczestnika","Imię i nazwisko","Numer telefonu",Postęp,"Dopisano do kursu","Dostęp wygasa"<br>
                                2,waldemar.grabowski@hostnet.pl,"Waldemar Grabowski",501654274,"0 / 1 (0%)","2025-08-22 22:42:40","2025-10-26 22:59:00"
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-success">Importuj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Lista uczestników - {{ $course->title }} ({{ date('d.m.Y H:i', strtotime($course->start_date)) }})
        </h2>
    </x-slot>

    <div class="container py-3">
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('courses.index') }}" class="btn btn-secondary me-2">Powrót do listy szkoleń</a>
            <a href="{{ route('participants.create', $course) }}" class="btn btn-primary">Dodaj uczestnika</a>
        </div>
        <!-- Przycisk sortowania -->
        <div class="mb-3">
            <a href="{{ route('participants.index', ['course' => $course->id, 'sort' => 'asc']) }}" class="btn btn-info">Sortuj alfabetycznie</a>
        </div>        

        <!-- Komunikat o sukcesie -->
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
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
                                <span class="badge {{ $participant->hasExpiredAccess() ? 'bg-danger' : ($participant->hasActiveAccess() ? 'bg-success' : 'bg-warning') }}" title="{{ $participant->access_expires_at->format('d.m.Y H:i') }}">
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
</x-app-layout>

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

        <!-- Komunikat o sukcesie -->
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Imię</th>
                    <th>Nazwisko</th>
                    <th>Email</th>
                    <th>Data urodzenia</th>
                    <th>Miejsce urodzenia</th>
                    <th>Nr certyfikatu</th>                    
                    <th>Akcje</th>
                    <th>Certyfikat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($participants as $participant)
                    <tr>
                        <td>{{ $participant->id }}</td>
                        <td>{{ $participant->first_name }}</td>
                        <td>{{ $participant->last_name }}</td>
                        <td>{{ $participant->email ?? 'Brak' }}</td>
                        <td>{{ $participant->birth_date ?? 'Brak' }}</td>
                        <td>{{ $participant->birth_place ?? 'Brak' }}</td>
                        <td>
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
                            <a href="{{ route('participants.edit', [$course, $participant]) }}" class="btn btn-warning btn-sm">Edytuj</a>
                            <form action="{{ route('participants.destroy', [$course, $participant]) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Usunąć uczestnika?')">Usuń</button>
                            </form>
                        </td>
                        <td>
                            @if ($participant->certificate)
                                <a href="{{ route('certificates.store', $participant) }}" class="btn btn-success btn-sm">Generuj</a>                                
                            @else
                                <a href="{{ route('certificates.store', $participant) }}" class="btn btn-primary btn-sm">Generuj</a>
                            @endif
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

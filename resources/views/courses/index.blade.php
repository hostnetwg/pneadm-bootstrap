<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista szkoleń') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h1>Lista szkoleń</h1>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('courses.create') }}" class="btn btn-primary">Dodaj szkolenie</a>
            </div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Obrazek</th>
                        <th>Tytuł</th>
                        <th>Opis</th>
                        <th>Data rozpoczęcia</th>
                        <th>Rodzaj</th>
                        <th>Lokalizacja / Dostęp</th>
                        <th>Instruktor</th>
                        <th>Status</th>
                        <th title="Uczestnicy">U</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                    <tr class="{{ strtotime($course->end_date) < time() ? 'table-secondary text-muted' : '' }}">
                        <td>{{ $course->id }}</td>
                        <td>
                            <img src="{{ asset('storage/' . ($course->image ?? 'default-course.png')) }}" alt="Obrazek kursu" width="50">
                        </td>
                        <td>{{ $course->title }}</td>
                        <td>{{ Str::limit($course->description, 50) }}</td>
                        <td>{{ $course->start_date ? date('d.m.Y H:i', strtotime($course->start_date)) : 'Brak daty' }}</td>
                        <td>{{ $course->type === 'offline' ? 'Stacjonarne' : 'Online' }}</td>
                        <td>
                            @if ($course->type === 'offline')
                                {{ $course->location ? $course->location->address . ', ' . $course->location->city : 'Brak lokalizacji' }}
                            @else
                                <a href="{{ $course->onlineDetails->meeting_link ?? '#' }}" target="_blank">
                                    {{ $course->onlineDetails->platform ?? 'Brak platformy' }}
                                </a>
                            @endif
                        </td>
                        <td>
                            {{ $course->instructor ? $course->instructor->first_name . ' ' . $course->instructor->last_name : 'Brak instruktora' }}
                        </td>
                        <td>{{ $course->is_active ? 'Aktywny' : 'Nieaktywny' }}</td>
                        <td title="Liczba uczestników">{{ $course->participants->count() }}</td>
                        <td>
                            <a href="{{ route('courses.edit', $course->id) }}" class="btn btn-warning btn-sm" style="width: 100px;">Edytuj</a>
                            <form action="{{ route('courses.destroy', $course->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" style="width: 100px;" onclick="return confirm('Czy na pewno chcesz usunąć?')">Usuń</button>
                            </form>
                            <a href="{{ route('participants.index', $course->id) }}" class="btn btn-info btn-sm" style="width: 100px;">Uczestnicy</a>
                        </td>
                    </tr>@endforeach
                </tbody>
            </table>

            <div class="mt-3">
                {{ $courses->links() }}
            </div>

        </div>
    </div>
</x-app-layout>

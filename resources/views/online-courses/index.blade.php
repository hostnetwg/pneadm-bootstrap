<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Kursy online (nagrania)</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <form method="get" action="{{ route('online-courses.index') }}" class="d-flex gap-2">
                    <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Szukaj tytułu, slug, ID Publigo…">
                    <button type="submit" class="btn btn-outline-secondary">Szukaj</button>
                </form>
                <a href="{{ route('online-courses.create') }}" class="btn btn-primary">Nowy kurs online</a>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tytuł</th>
                            <th>Slug</th>
                            <th>Moduły/lekcje</th>
                            <th>Dostępy</th>
                            <th>Aktywny</th>
                            <th>W panelu PNEDU</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses as $course)
                            <tr>
                                <td>{{ $course->id }}</td>
                                <td>{{ $course->title }}</td>
                                <td><code>{{ $course->slug }}</code></td>
                                <td>{{ $course->modules_count }}/{{ $course->lessons_count }}</td>
                                <td>{{ $course->enrollments_count }}</td>
                                <td>{{ $course->is_active ? 'Tak' : 'Nie' }}</td>
                                <td>{{ $course->visible_in_dashboard ? 'Tak' : 'Nie' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-sm btn-outline-primary">Edycja</a>
                                    <a href="{{ route('online-courses.enrollments.index', $course) }}" class="btn btn-sm btn-outline-secondary">Dostępy</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted">Brak kursów online.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $courses->links() }}
        </div>
    </div>
</x-app-layout>

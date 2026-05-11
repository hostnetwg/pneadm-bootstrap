<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Dostępy: {{ $online_course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="mb-3">
                <a href="{{ route('online-courses.enrollments.create', $online_course) }}" class="btn btn-primary">Dodaj dostęp</a>
                <a href="{{ route('online-courses.edit', $online_course) }}" class="btn btn-outline-secondary">Treść kursu</a>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>E-mail</th>
                            <th>Imię i nazwisko</th>
                            <th>Wygasa</th>
                            <th>Źródło</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollments as $e)
                            <tr>
                                <td>{{ $e->email }}</td>
                                <td>{{ trim(($e->first_name ?? '').' '.($e->last_name ?? '')) ?: '—' }}</td>
                                <td>{{ $e->access_expires_at ? $e->access_expires_at->format('Y-m-d H:i') . ' UTC' : 'bezterminowo' }}</td>
                                <td>{{ $e->access_source }}</td>
                                <td class="text-end">
                                    <a href="{{ route('online-courses.enrollments.edit', [$online_course, $e]) }}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Brak przypisań.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $enrollments->links() }}
        </div>
    </div>
</x-app-layout>

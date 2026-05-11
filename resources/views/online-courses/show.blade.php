<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">{{ $course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            <p class="text-muted">Slug: <code>{{ $course->slug }}</code> · Dostępy: {{ $course->enrollments_count }}</p>
            <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-primary">Edycja treści i modułów</a>
            <a href="{{ route('online-courses.enrollments.index', $course) }}" class="btn btn-outline-secondary">Lista dostępów</a>
        </div>
    </div>
</x-app-layout>

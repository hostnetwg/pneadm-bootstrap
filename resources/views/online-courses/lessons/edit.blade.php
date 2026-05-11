<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Lekcja: {{ $lesson->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4" style="max-width: 960px;">
            @include('online-courses.lessons.partials.form', ['course' => $online_course, 'module' => $module, 'lesson' => $lesson])
        </div>
    </div>
</x-app-layout>

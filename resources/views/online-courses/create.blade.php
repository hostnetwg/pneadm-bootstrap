<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Nowy kurs online</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4" style="max-width: 920px;">
            @include('online-courses.partials.form', ['course' => null, 'instructors' => $instructors])
        </div>
    </div>
</x-app-layout>

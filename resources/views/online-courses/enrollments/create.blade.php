<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Dodaj dostęp · {{ $online_course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4" style="max-width: 640px;">
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route('online-courses.enrollments.store', $online_course) }}">
                @csrf
                @include('online-courses.enrollments.partials.fields', ['enrollment' => null])
                <button type="submit" class="btn btn-primary">Zapisz</button>
                <a href="{{ route('online-courses.enrollments.index', $online_course) }}" class="btn btn-outline-secondary">Anuluj</a>
            </form>
        </div>
    </div>
</x-app-layout>

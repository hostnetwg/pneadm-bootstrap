<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Edycja dostępu · {{ $online_course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4" style="max-width: 640px;">
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route('online-courses.enrollments.update', [$online_course, $enrollment]) }}">
                @csrf
                @method('PUT')
                @include('online-courses.enrollments.partials.fields', ['enrollment' => $enrollment])
                <button type="submit" class="btn btn-primary">Zapisz</button>
                <a href="{{ route('online-courses.enrollments.index', $online_course) }}" class="btn btn-outline-secondary">Lista</a>
            </form>
            <hr>
            <form method="post" action="{{ route('online-courses.enrollments.destroy', [$online_course, $enrollment]) }}" onsubmit="return confirm('Usunąć ten dostęp?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Usuń dostęp</button>
            </form>
        </div>
    </div>
</x-app-layout>

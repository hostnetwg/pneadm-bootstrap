<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Edycja kursu: {{ $course->title }}</h2>
    </x-slot>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item"><span class="nav-link active">Dane kursu</span></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('online-courses.enrollments.index', $course) }}">Dostępy</a></li>
            </ul>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">Podstawowe</div>
                        <div class="card-body">
                            @include('online-courses.partials.form', ['course' => $course, 'instructors' => $instructors])
                            <hr>
                            <form method="post" action="{{ route('online-courses.destroy', $course) }}" onsubmit="return confirm('Przenieść kurs do kosza?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Usuń kurs (soft delete)</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header">Dodaj moduł</div>
                        <div class="card-body">
                            <form method="post" action="{{ route('online-courses.modules.store', $course) }}">
                                @csrf
                                <div class="input-group">
                                    <input type="text" name="title" class="form-control" placeholder="Tytuł modułu" required maxlength="255">
                                    <button class="btn btn-primary" type="submit">Dodaj</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    @foreach($course->modules as $module)
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <form method="post" action="{{ route('online-courses.modules.update', [$course, $module]) }}" class="flex-grow-1 d-flex gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="title" class="form-control form-control-sm" value="{{ $module->title }}" required>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Zmień tytuł</button>
                                    </form>
                                    <form method="post" action="{{ route('online-courses.modules.destroy', [$course, $module]) }}" onsubmit="return confirm('Usunąć moduł z lekcjami?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                                    </form>
                                </div>
                                <p class="small text-muted mb-2">Lekcje</p>
                                <ul class="list-group list-group-flush mb-2">
                                    @foreach($module->lessons as $lesson)
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span>
                                                @if(!$lesson->is_published)<span class="badge text-bg-secondary me-1">szkic</span>@endif
                                                {{ $lesson->title }}
                                                <small class="text-muted">({{ $lesson->embeds->count() }} wideo, {{ $lesson->resourceLinks->count() }} link.)</small>
                                            </span>
                                            <span>
                                                <a href="{{ route('online-courses.lessons.edit', [$course, $module, $lesson]) }}" class="btn btn-sm btn-link">Edytuj</a>
                                                <form class="d-inline" method="post" action="{{ route('online-courses.lessons.destroy', [$course, $module, $lesson]) }}" onsubmit="return confirm('Usunąć lekcję?');">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-link text-danger">Usuń</button>
                                                </form>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('online-courses.lessons.create', [$course, $module]) }}" class="btn btn-sm btn-primary">Nowa lekcja</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

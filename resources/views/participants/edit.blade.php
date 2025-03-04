<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj szkolenie') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <!-- Formularz edycji kursu -->
            <form action="{{ route('courses.update', $course->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł kursu</label>
                    <input type="text" name="title" id="title" class="form-control" value="{{ $course->title }}" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Opis</label>
                    <textarea name="description" id="description" class="form-control">{{ $course->description }}</textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data rozpoczęcia</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $course->start_date }}">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data zakończenia</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $course->end_date }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Zdjęcie kursu</label>
                    <input type="file" name="image" id="image" class="form-control">
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                    <a href="{{ route('courses.index') }}" class="btn btn-secondary">Anuluj</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

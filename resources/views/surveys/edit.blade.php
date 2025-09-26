<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj ankietę') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {!! session('success') !!}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    {!! session('error') !!}
                </div>
            @endif

            <!-- Nawigacja -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4>Edytuj ankietę: <strong>{{ $survey->title }}</strong></h4>
                    <p class="text-muted mb-0">Modyfikuj informacje o ankiecie</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-outline-primary">
                        <i class="fas fa-eye"></i> Zobacz szczegóły
                    </a>
                    <a href="{{ route('surveys.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Powrót do listy
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-edit"></i> Edycja ankiety
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('surveys.update', $survey->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Tytuł ankiety <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title', $survey->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Opis ankiety</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description', $survey->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="course_id" class="form-label">Szkolenie <span class="text-danger">*</span></label>
                                    <select class="form-select @error('course_id') is-invalid @enderror" 
                                            id="course_id" name="course_id" required>
                                        <option value="">Wybierz szkolenie</option>
                                        @foreach($courses as $course)
                                            <option value="{{ $course->id }}" 
                                                    {{ old('course_id', $survey->course_id) == $course->id ? 'selected' : '' }}>
                                                {{ $course->title }} 
                                                ({{ $course->start_date ? $course->start_date->format('d.m.Y') : 'Brak daty' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('course_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="instructor_id" class="form-label">Instruktor</label>
                                    <select class="form-select @error('instructor_id') is-invalid @enderror" 
                                            id="instructor_id" name="instructor_id">
                                        <option value="">Wybierz instruktora (opcjonalnie)</option>
                                        @foreach($instructors as $instructor)
                                            <option value="{{ $instructor->id }}" 
                                                    {{ old('instructor_id', $survey->instructor_id) == $instructor->id ? 'selected' : '' }}>
                                                {{ $instructor->getFullTitleNameAttribute() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('instructor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="source" class="form-label">Źródło <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('source') is-invalid @enderror" 
                                           id="source" name="source" value="{{ old('source', $survey->source) }}" required>
                                    <div class="form-text">
                                        Np. Google Forms, Microsoft Forms, itp.
                                    </div>
                                    @error('source')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-secondary me-md-2">
                                        Anuluj
                                    </a>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save"></i> Zapisz zmiany
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Informacje o ankiecie -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Informacje o ankiecie
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $survey->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Utworzono:</strong></td>
                                    <td>{{ $survey->imported_at->format('d.m.Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Przez:</strong></td>
                                    <td>{{ $survey->importedBy->name ?? 'Nieznany' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Odpowiedzi:</strong></td>
                                    <td>{{ $survey->total_responses }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Pytań:</strong></td>
                                    <td>{{ $survey->questions->count() }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Akcje -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cogs"></i> Akcje
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> Zobacz szczegóły
                                </a>
                                <a href="{{ route('surveys.report', $survey->id) }}" class="btn btn-success" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Generuj raport PDF
                                </a>
                                <form action="{{ route('surveys.destroy', $survey->id) }}" method="POST" 
                                      onsubmit="return confirm('Czy na pewno chcesz usunąć tę ankietę?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-trash"></i> Usuń ankietę
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

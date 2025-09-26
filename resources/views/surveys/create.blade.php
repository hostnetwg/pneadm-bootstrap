<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj ankietę') }}
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
                    <h4>Dodaj nową ankietę</h4>
                    <p class="text-muted mb-0">Utwórz nową ankietę dla wybranego szkolenia</p>
                </div>
                <a href="{{ route('surveys.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Powrót do listy
                </a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-plus"></i> Nowa ankieta
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('surveys.store') }}" method="POST">
                                @csrf
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Tytuł ankiety <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Opis ankiety</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description') }}</textarea>
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
                                                    {{ old('course_id') == $course->id ? 'selected' : '' }}>
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
                                                    {{ old('instructor_id') == $instructor->id ? 'selected' : '' }}>
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
                                           id="source" name="source" value="{{ old('source', 'Google Forms') }}" required>
                                    <div class="form-text">
                                        Np. Google Forms, Microsoft Forms, itp.
                                    </div>
                                    @error('source')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="{{ route('surveys.index') }}" class="btn btn-secondary me-md-2">
                                        Anuluj
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Utwórz ankietę
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Informacje
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                Utworzenie ankiety pozwoli Ci na:
                            </p>
                            <ul class="list-unstyled small">
                                <li><i class="fas fa-check text-success"></i> Importowanie danych z pliku CSV</li>
                                <li><i class="fas fa-check text-success"></i> Analizę odpowiedzi</li>
                                <li><i class="fas fa-check text-success"></i> Generowanie raportów</li>
                                <li><i class="fas fa-check text-success"></i> Porównywanie wyników</li>
                            </ul>

                            <div class="alert alert-info mt-3">
                                <small>
                                    <i class="fas fa-lightbulb"></i>
                                    <strong>Wskazówka:</strong> Po utworzeniu ankiety będziesz mógł zaimportować dane z pliku CSV.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

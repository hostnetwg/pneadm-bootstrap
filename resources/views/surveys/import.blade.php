<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Import ankiety') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
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
                    <h4>Import ankiety dla: <strong>{!! $course->title !!}</strong></h4>
                    <p class="text-muted mb-0">Zaimportuj wyniki ankiety z pliku CSV pobranego z Google Forms</p>
                </div>
                <a href="{{ route('courses.show', $course->id) }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Powrót do szkolenia
                </a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-file-import"></i> Import pliku CSV
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('surveys.import.store', $course->id) }}" method="POST" enctype="multipart/form-data">
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
                                    <label for="instructor_id" class="form-label">Instruktor</label>
                                    <select class="form-select @error('instructor_id') is-invalid @enderror" 
                                            id="instructor_id" name="instructor_id">
                                        <option value="">Wybierz instruktora (opcjonalnie)</option>
                                        @foreach(\App\Models\Instructor::orderBy('first_name')->get() as $instructor)
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
                                    <label for="csv_file" class="form-label">Plik CSV <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control @error('csv_file') is-invalid @enderror" 
                                           id="csv_file" name="csv_file" accept=".csv,.txt" required>
                                    <div class="form-text">
                                        Wybierz plik CSV pobrany z Google Forms. Maksymalny rozmiar: 10MB.
                                    </div>
                                    @error('csv_file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="{{ route('courses.show', $course->id) }}" class="btn btn-secondary me-md-2">
                                        Anuluj
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Importuj ankietę
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
                                <i class="fas fa-info-circle"></i> Informacje o imporcie
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Wymagania pliku CSV:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Format CSV z nagłówkami</li>
                                <li><i class="fas fa-check text-success"></i> Kolumna "Sygnatura czasowa"</li>
                                <li><i class="fas fa-check text-success"></i> Pytania w nagłówkach kolumn</li>
                                <li><i class="fas fa-check text-success"></i> Kodowanie UTF-8</li>
                            </ul>

                            <h6 class="mt-3">Automatyczne wykrywanie:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-star text-warning"></i> Pytania ratingowe (1-5)</li>
                                <li><i class="fas fa-comment text-info"></i> Pytania tekstowe</li>
                                <li><i class="fas fa-list text-primary"></i> Pytania wielokrotnego wyboru</li>
                                <li><i class="fas fa-calendar text-success"></i> Pytania z datą/czasem</li>
                            </ul>

                            <div class="alert alert-info mt-3">
                                <small>
                                    <i class="fas fa-lightbulb"></i>
                                    <strong>Wskazówka:</strong> System automatycznie wykryje typy pytań na podstawie danych w pliku CSV.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-download"></i> Przykład pliku
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                Pobierz przykładowy plik CSV, aby zobaczyć oczekiwany format:
                            </p>
                            <a href="#" class="btn btn-outline-primary btn-sm" onclick="downloadSample()">
                                <i class="fas fa-download"></i> Pobierz przykład
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadSample() {
            // Przykładowe dane CSV
            const sampleData = `Sygnatura czasowa,"1. Pytanie [Czy prowadzący przedstawił cele szkolenia precyzyjnie i zrozumiale?]","1. Pytanie [Czy prowadzący szkolenie posiadał odpowiednią wiedzę i przygotowanie merytoryczne?]","2. Jakie zauważa Pan/Pani plusy w szkoleniu?","3. Co zmieniłaby/zmieniłby, dodała/dodał Pani/Pan w szkoleniu?"
2025/08/18 1:56:47 PM EEST,"5","5","Przygotowanie, materiały","Nic"
2025/08/18 1:56:56 PM EEST,"5","5","Konkretne informacje","Tempo, jest intensywne"`;

            const blob = new Blob([sampleData], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'przyklad_ankiety.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</x-app-layout>

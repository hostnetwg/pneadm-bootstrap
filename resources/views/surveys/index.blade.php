<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Ankiety') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {!! session('success') !!}
                </div>
            @endif

            <!-- Nagłówek i akcje -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4>Wszystkie ankiety</h4>
                    <p class="text-muted mb-0">Zarządzaj ankietami z wszystkich szkoleń</p>
                </div>
                <a href="{{ route('surveys.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Dodaj ankietę
                </a>
            </div>

            <!-- Filtry -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('surveys.index') }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Wyszukaj</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="{{ request('search') }}" placeholder="Tytuł, opis, szkolenie...">
                            </div>
                            <div class="col-md-3">
                                <label for="course_id" class="form-label">Szkolenie</label>
                                <select class="form-select" id="course_id" name="course_id">
                                    <option value="">Wszystkie szkolenia</option>
                                    @foreach($courses as $course)
                                        <option value="{{ $course->id }}" 
                                                {{ request('course_id') == $course->id ? 'selected' : '' }}>
                                            {{ $course->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="instructor_id" class="form-label">Instruktor</label>
                                <select class="form-select" id="instructor_id" name="instructor_id">
                                    <option value="">Wszyscy instruktorzy</option>
                                    @foreach($instructors as $instructor)
                                        <option value="{{ $instructor->id }}" 
                                                {{ request('instructor_id') == $instructor->id ? 'selected' : '' }}>
                                            {{ $instructor->getFullTitleNameAttribute() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtruj
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statystyki przefiltrowanych ankiet -->
            @if($statistics['total_surveys'] > 0)
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statystyki przefiltrowanych ankiet</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-clipboard-list"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-info">{{ $statistics['total_surveys'] }}</div>
                                                <small class="text-muted">Ankiet</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-comments"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-success">{{ $statistics['total_responses'] }}</div>
                                                <small class="text-muted">Odpowiedzi</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-question-circle"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-warning">{{ $statistics['total_questions'] }}</div>
                                                <small class="text-muted">Pytań</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-danger">
                                                    @if($statistics['average_rating'] > 0)
                                                        {{ $statistics['average_rating'] }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
                                                <small class="text-muted">
                                                    Średnia ocen
                                                    @if($statistics['surveys_with_ratings'] > 0)
                                                        ({{ $statistics['surveys_with_ratings'] }} ankiet)
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Lista ankiet -->
            @if($surveys->count() > 0)
                <div class="row">
                    @foreach($surveys as $survey)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">{{ Str::limit($survey->title, 40) }}</h6>
                                    <span class="badge bg-primary">{{ $survey->total_responses }}</span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-graduation-cap"></i> 
                                        <a href="{{ route('courses.show', $survey->course_id) }}" class="text-decoration-none">
                                            {{ Str::limit($survey->course->title, 50) }}
                                        </a>
                                    </p>
                                    
                                    @if($survey->instructor)
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-user"></i> {{ $survey->instructor->getFullTitleNameAttribute() }}
                                        </p>
                                    @endif

                                    <p class="text-muted mb-2">
                                        <i class="fas fa-calendar"></i> 
                                        @if($survey->course->start_date)
                                            Szkolenie: {{ $survey->course->start_date->format('d.m.Y') }}
                                        @else
                                            Import: {{ $survey->imported_at->format('d.m.Y H:i') }}
                                        @endif
                                    </p>
                                    
                                    @if($survey->course->start_date)
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-upload"></i> Import: {{ $survey->imported_at->format('d.m.Y H:i') }}
                                        </p>
                                    @endif

                                    @if($survey->description)
                                        <p class="text-muted small mb-3">{{ Str::limit($survey->description, 100) }}</p>
                                    @endif

                                    <!-- Statystyki -->
                                    <div class="row text-center mb-3">
                                        <div class="col-3">
                                            <div class="text-primary fw-bold">{{ $survey->total_responses }}</div>
                                            <small class="text-muted">Odpowiedzi</small>
                                        </div>
                                        <div class="col-3">
                                            <div class="text-success fw-bold">{{ $survey->getActualQuestionsCount() }}</div>
                                            <small class="text-muted">Pytań</small>
                                        </div>
                                        <div class="col-3">
                                            <div class="text-warning fw-bold">
                                                {{ $survey->getAverageRating() > 0 ? $survey->getAverageRating() : 'N/A' }}
                                            </div>
                                            <small class="text-muted">Średnia</small>
                                        </div>
                                        <div class="col-3">
                                            @if($survey->original_file_path)
                                                <div class="text-success fw-bold">
                                                    <i class="fas fa-check-circle"></i>
                                                </div>
                                                <small class="text-muted">CSV</small>
                                            @else
                                                <div class="text-muted fw-bold">
                                                    <i class="fas fa-times-circle"></i>
                                                </div>
                                                <small class="text-muted">CSV</small>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Informacja o pliku CSV -->
                                    @if($survey->original_file_path)
                                        <div class="alert alert-light py-2 mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-file-csv text-success"></i> 
                                                        <strong>Plik:</strong> {{ basename($survey->original_file_path) }}
                                                    </small>
                                                </div>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('surveys.download-file', $survey->id) }}" 
                                                       class="btn btn-outline-success btn-sm" 
                                                       title="Pobierz plik CSV">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <form action="{{ route('surveys.delete-original-file', $survey->id) }}" 
                                                          method="POST" 
                                                          onsubmit="return confirm('Czy na pewno chcesz usunąć plik CSV?')" 
                                                          class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                                title="Usuń plik CSV">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="card-footer">
                                    <div class="d-grid gap-2">
                                        <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Zobacz szczegóły
                                        </a>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('surveys.report.form', $survey->id) }}" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            <a href="{{ route('surveys.edit', $survey->id) }}" class="btn btn-outline-warning btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('surveys.destroy', $survey->id) }}" method="POST" 
                                                  onsubmit="return confirm('Czy na pewno chcesz usunąć tę ankietę?')" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Paginacja -->
                <div class="d-flex justify-content-center">
                    {{ $surveys->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Brak ankiet</h5>
                    <p class="text-muted">Nie znaleziono ankiet spełniających kryteria wyszukiwania.</p>
                    <a href="{{ route('surveys.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Dodaj pierwszą ankietę
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

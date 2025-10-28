<!-- resources/views/courses/instructors/show.blade.php -->

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Podgląd instruktora') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <!-- Nawigacja -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $instructor->getFullTitleNameAttribute() }}</h4>
                    <p class="text-muted mb-0">Podgląd profilu instruktora</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('courses.instructors.index') }}" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Lista instruktorów
                    </a>
                    <a href="{{ route('courses.instructors.edit', $instructor->id) }}" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edytuj
                    </a>
                    <button type="button" class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        <i class="fas fa-trash"></i> Usuń
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <!-- Zdjęcie i podstawowe informacje -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user"></i> Zdjęcie profilowe
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            @if ($instructor->photo)
                                <img src="{{ asset('storage/' . $instructor->photo) }}" 
                                     alt="Zdjęcie {{ $instructor->getFullNameAttribute() }}" 
                                     class="img-fluid rounded-circle mb-3" 
                                     style="width: 200px; height: 200px; object-fit: cover;">
                            @else
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3 mx-auto" 
                                     style="width: 200px; height: 200px;">
                                    <i class="fas fa-user fa-4x text-muted"></i>
                                </div>
                            @endif
                            
                            <h5 class="card-title">{{ $instructor->getFullTitleNameAttribute() }}</h5>
                            <p class="text-muted">
                                @if($instructor->is_active)
                                    <span class="badge bg-success">Aktywny</span>
                                @else
                                    <span class="badge bg-secondary">Nieaktywny</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Podpis -->
                    @if($instructor->signature)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-signature"></i> Podpis
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <img src="{{ asset('storage/' . $instructor->signature) }}" 
                                     alt="Podpis {{ $instructor->getFullNameAttribute() }}" 
                                     class="img-fluid" 
                                     style="max-height: 150px;">
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-md-8">
                    <!-- Szczegółowe informacje -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Informacje kontaktowe
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong><i class="fas fa-id-card text-primary"></i> Tytuł:</strong></td>
                                            <td>{{ $instructor->title ?: 'Brak' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong><i class="fas fa-user text-primary"></i> Imię:</strong></td>
                                            <td>{{ $instructor->first_name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong><i class="fas fa-user text-primary"></i> Nazwisko:</strong></td>
                                            <td>{{ $instructor->last_name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong><i class="fas fa-venus-mars text-primary"></i> Płeć:</strong></td>
                                            <td>{{ $instructor->gender_label }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong><i class="fas fa-envelope text-primary"></i> Email:</strong></td>
                                            <td>
                                                <a href="mailto:{{ $instructor->email }}" class="text-decoration-none">
                                                    {{ $instructor->email }}
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong><i class="fas fa-phone text-primary"></i> Telefon:</strong></td>
                                            <td>
                                                @if($instructor->phone)
                                                    <a href="tel:{{ $instructor->phone }}" class="text-decoration-none">
                                                        {{ $instructor->phone }}
                                                    </a>
                                                @else
                                                    Brak
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><i class="fas fa-calendar text-primary"></i> Status:</strong></td>
                                            <td>
                                                @if($instructor->is_active)
                                                    <span class="badge bg-success">Aktywny</span>
                                                @else
                                                    <span class="badge bg-secondary">Nieaktywny</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><i class="fas fa-hashtag text-primary"></i> ID:</strong></td>
                                            <td>{{ $instructor->id }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Biografia -->
                    @if($instructor->bio || $instructor->bio_html)
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-book"></i> Biografia
                                </h5>
                            </div>
                            <div class="card-body">
                                @if($instructor->bio_html)
                                    <div class="bio-html-content">
                                        {!! $instructor->bio_html !!}
                                    </div>
                                @elseif($instructor->bio)
                                    <div class="bio-content">
                                        {!! nl2br(e($instructor->bio)) !!}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Statystyki -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar"></i> Statystyki
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <h4 class="text-primary mb-1">
                                            {{ $instructor->courses()->count() }}
                                        </h4>
                                        <small class="text-muted">Szkolenia</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <h4 class="text-success mb-1">
                                            {{ $instructor->courses()->where('end_date', '<', now())->count() }}
                                        </h4>
                                        <small class="text-muted">Zakończone</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <h4 class="text-info mb-1">
                                            {{ $instructor->courses()->where('end_date', '>=', now())->count() }}
                                        </h4>
                                        <small class="text-muted">W trakcie/Przyszłe</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista ankiet -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list"></i> Ankiety ({{ $instructor->surveys->count() }})
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($instructor->surveys->count() > 0)
                                <div class="row">
                                    @foreach($instructor->surveys as $survey)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">{{ Str::limit($survey->title, 30) }}</h6>
                                                    <span class="badge bg-primary">{{ $survey->total_responses }}</span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="text-muted mb-2">
                                                        <i class="fas fa-graduation-cap"></i> 
                                                        <a href="{{ route('courses.show', $survey->course_id) }}" class="text-decoration-none">
                                                            {{ Str::limit($survey->course->title, 40) }}
                                                        </a>
                                                    </p>
                                                    
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
                                                        <p class="text-muted small mb-3">{{ Str::limit($survey->description, 80) }}</p>
                                                    @endif

                                                    <!-- Statystyki ankiety -->
                                                    <div class="row text-center mb-3">
                                                        <div class="col-4">
                                                            <div class="text-primary fw-bold">{{ $survey->total_responses }}</div>
                                                            <small class="text-muted">Odpowiedzi</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="text-success fw-bold">{{ $survey->getActualQuestionsCount() }}</div>
                                                            <small class="text-muted">Pytań</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="text-warning fw-bold">
                                                                {{ $survey->getAverageRating() > 0 ? $survey->getAverageRating() : 'N/A' }}
                                                            </div>
                                                            <small class="text-muted">Średnia</small>
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
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Brak ankiet</h5>
                                    <p class="text-muted">Ten instruktor nie ma jeszcze przypisanych ankiet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal potwierdzenia usunięcia --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć instruktora <strong>#{{ $instructor->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły instruktora:</h6>
                        <ul class="mb-0">
                            <li><strong>Imię i nazwisko:</strong> {{ $instructor->getFullTitleNameAttribute() }}</li>
                            <li><strong>Email:</strong> {{ $instructor->email }}</li>
                            <li><strong>Telefon:</strong> {{ $instructor->phone ?? 'Brak' }}</li>
                            <li><strong>Płeć:</strong> {{ $instructor->gender_label }}</li>
                            <li><strong>Status:</strong> {{ $instructor->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                            <li><strong>Liczba szkoleń:</strong> {{ $instructor->courses()->count() }}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="bi bi-info-circle"></i>
                        Instruktor zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    <form action="{{ route('courses.instructors.destroy', $instructor->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Usuń instruktora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

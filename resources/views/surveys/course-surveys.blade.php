<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Ankiety szkolenia') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    {!! session('success') !!}
                </div>
            @endif

            <!-- Nawigacja -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4>Ankiety dla: <strong>{{ $course->title }}</strong></h4>
                    <p class="text-muted mb-0">Wszystkie ankiety przypisane do tego szkolenia</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('surveys.import', $course->id) }}" class="btn btn-success">
                        <i class="fas fa-file-import"></i> Importuj ankietę
                    </a>
                    <a href="{{ route('courses.show', $course->id) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Powrót do szkolenia
                    </a>
                </div>
            </div>

            <!-- Informacje o kursie -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informacje o szkoleniu:</h6>
                            <p class="mb-1"><strong>Data:</strong> {{ $course->start_date ? $course->start_date->format('d.m.Y H:i') : 'Brak daty' }}</p>
                            @if($course->instructor)
                                <p class="mb-1"><strong>Instruktor:</strong> {{ $course->instructor->getFullTitleNameAttribute() }}</p>
                            @endif
                            <p class="mb-0"><strong>Typ:</strong> {{ ucfirst($course->type) }} | {{ $course->is_paid ? 'Płatne' : 'Bezpłatne' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Statystyki:</h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-primary fw-bold">{{ $course->participants->count() }}</div>
                                    <small class="text-muted">Uczestnicy</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-success fw-bold">{{ $course->certificates->count() }}</div>
                                    <small class="text-muted">Zaświadczenia</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning fw-bold">{{ $surveys->count() }}</div>
                                    <small class="text-muted">Ankiety</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                                    @if($survey->description)
                                        <p class="text-muted small mb-3">{{ Str::limit($survey->description, 100) }}</p>
                                    @endif

                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="text-primary fw-bold">{{ $survey->total_responses }}</div>
                                            <small class="text-muted">Odpowiedzi</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success fw-bold">{{ $survey->questions->count() }}</div>
                                            <small class="text-muted">Pytań</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-warning fw-bold">
                                                {{ $survey->getAverageRating() > 0 ? $survey->getAverageRating() : 'N/A' }}
                                            </div>
                                            <small class="text-muted">Średnia</small>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> {{ $survey->imported_at->format('d.m.Y H:i') }}<br>
                                            <i class="fas fa-user"></i> {{ $survey->importedBy->name ?? 'Nieznany' }}<br>
                                            <i class="fas fa-database"></i> {{ $survey->source }}
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-grid gap-2">
                                        <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Zobacz szczegóły
                                        </a>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('surveys.report', $survey->id) }}" class="btn btn-outline-success btn-sm" target="_blank">
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

                <!-- Statystyki ogólne -->
                @if($surveys->count() > 1)
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar"></i> Statystyki ogólne
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h4 class="text-primary">{{ $surveys->sum('total_responses') }}</h4>
                                    <small class="text-muted">Łącznie odpowiedzi</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-success">{{ $surveys->sum(fn($s) => $s->questions->count()) }}</h4>
                                    <small class="text-muted">Łącznie pytań</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-warning">
                                        @php
                                            $avgRatings = $surveys->map(fn($s) => $s->getAverageRating())->filter(fn($r) => $r > 0);
                                        @endphp
                                        {{ $avgRatings->count() > 0 ? round($avgRatings->avg(), 2) : 'N/A' }}
                                    </h4>
                                    <small class="text-muted">Średnia ocen</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-info">{{ $surveys->count() }}</h4>
                                    <small class="text-muted">Ankiet</small>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Brak ankiet</h5>
                    <p class="text-muted">Nie ma jeszcze żadnych ankiet dla tego szkolenia.</p>
                    <a href="{{ route('surveys.import', $course->id) }}" class="btn btn-primary">
                        <i class="fas fa-file-import"></i> Importuj pierwszą ankietę
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły szkolenia') }}
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
            <div class="mb-4 d-flex justify-content-end align-items-center gap-2">
                <!-- Przycisk Powrót do listy -->
                <a href="{{ route('courses.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Powrót do listy
                </a>

                <!-- Przycisk Poprzednie -->
                @if($previousCourse)
                    <a href="{{ route('courses.show', $previousCourse->id) }}" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> Poprzednie
                    </a>
                @else
                    <button class="btn btn-outline-secondary" disabled>
                        <i class="fas fa-chevron-left"></i> Poprzednie
                    </button>
                @endif

                <!-- Przycisk Następne -->
                @if($nextCourse)
                    <a href="{{ route('courses.show', $nextCourse->id) }}" class="btn btn-outline-primary">
                        Następne <i class="fas fa-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-outline-secondary" disabled>
                        Następne <i class="fas fa-chevron-right"></i>
                    </button>
                @endif
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Główne informacje o kursie -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-graduation-cap"></i> {{ $course->title }}
                            </h4>
                        </div>
                        <div class="card-body">
                            @if($course->description)
                                <h5>Opis szkolenia</h5>
                                <p class="text-muted">{{ $course->description }}</p>
                            @endif

                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Podstawowe informacje</h5>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>{{ $course->id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Data rozpoczęcia:</strong></td>
                                            <td>
                                                @if($course->start_date)
                                                    {{ date('d.m.Y H:i', strtotime($course->start_date)) }}
                                                    @if($course->end_date)
                                                        @php
                                                            $startDateTime = \Carbon\Carbon::parse($course->start_date);
                                                            $endDateTime = \Carbon\Carbon::parse($course->end_date);
                                                            $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
                                                        @endphp
                                                        <br><small class="text-muted">Czas trwania: {{ $durationMinutes }} minut</small>
                                                    @endif
                                                @else
                                                    Brak daty
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Data zakończenia:</strong></td>
                                            <td>{{ $course->end_date ? date('d.m.Y H:i', strtotime($course->end_date)) : 'Brak daty' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Rodzaj:</strong></td>
                                            <td>
                                                <span class="badge {{ $course->is_paid ? 'bg-warning' : 'bg-success' }}">
                                                    {{ $course->is_paid ? 'Płatne' : 'Bezpłatne' }}
                                                </span>
                                                <span class="badge bg-info ms-1">{{ ucfirst($course->type) }}</span>
                                                <span class="badge {{ $course->category === 'open' ? 'bg-success' : 'bg-danger' }} ms-1">
                                                    {{ $course->category === 'open' ? 'Otwarte' : 'Zamknięte' }}
                                                </span>
                                                <span class="badge {{ $course->is_active ? 'bg-success' : 'bg-danger' }} ms-1">
                                                    {{ $course->is_active ? 'Aktywne' : 'Nieaktywne' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="col-md-6">
                                    <h5>Instruktor</h5>
                                    @if($course->instructor)
                                        <div class="d-flex align-items-center">
                                            @if($course->instructor->photo)
                                                <img src="{{ asset('storage/' . $course->instructor->photo) }}" 
                                                     alt="Zdjęcie instruktora" 
                                                     class="rounded-circle me-3" 
                                                     width="60" height="60">
                                            @else
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 60px; height: 60px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <h6 class="mb-0">{{ $course->instructor->getFullTitleNameAttribute() }}</h6>
                                                @if($course->instructor->email)
                                                    <small class="text-muted">{{ $course->instructor->email }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-muted">Brak przypisanego instruktora</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lokalizacja lub szczegóły online -->
                    @if($course->type === 'offline' && $course->location)
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> Lokalizacja
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6>{{ $course->location->location_name ?? 'Brak nazwy lokalizacji' }}</h6>
                                <p class="mb-1">{{ $course->location->address ?? 'Brak adresu' }}</p>
                                <p class="mb-0">{{ $course->location->postal_code ?? '' }} {{ $course->location->post_office ?? '' }}</p>
                                @if($course->location->country)
                                    <p class="mb-0">{{ $course->location->country }}</p>
                                @endif
                            </div>
                        </div>
                    @elseif($course->type === 'online' && $course->onlineDetails)
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-video"></i> Szczegóły spotkania online
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Platforma:</strong></td>
                                        <td>{{ $course->onlineDetails->platform ?? 'Nieznana' }}</td>
                                    </tr>
                                    @if($course->onlineDetails->meeting_link)
                                        <tr>
                                            <td><strong>Link do spotkania:</strong></td>
                                            <td>
                                                <a href="{{ $course->onlineDetails->meeting_link }}" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-external-link-alt"></i> Dołącz do spotkania
                                                </a>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($course->onlineDetails->meeting_password)
                                        <tr>
                                            <td><strong>Hasło:</strong></td>
                                            <td><code>{{ $course->onlineDetails->meeting_password }}</code></td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    <!-- Statystyki -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Statystyki</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h4 class="text-primary">{{ $course->participants->count() }}</h4>
                                    <small class="text-muted">Uczestnicy</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-success">{{ $course->certificates->count() }}</h4>
                                    <small class="text-muted">Zaświadczenia</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-warning">{{ $course->surveys->count() }}</h4>
                                    <small class="text-muted">Ankiety</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Obrazek kursu -->
                    @if($course->image)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Obrazek kursu</h5>
                            </div>
                            <div class="card-body text-center">
                                <img src="{{ asset('storage/' . $course->image) }}" 
                                     alt="Obrazek kursu" 
                                     class="img-fluid rounded">
                            </div>
                        </div>
                    @endif

                    <!-- Ankiety -->
                    @if($course->surveys->count() > 0)
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-clipboard-list"></i> Ankiety ({{ $course->surveys->count() }})
                                </h5>
                                <a href="{{ route('surveys.course', $course->id) }}" class="btn btn-sm btn-outline-primary">
                                    Zobacz wszystkie
                                </a>
                            </div>
                            <div class="card-body">
                                @foreach($course->surveys->take(3) as $survey)
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <div>
                                            <h6 class="mb-1">
                                                <a href="{{ route('surveys.show', $survey->id) }}" class="text-decoration-none">
                                                    {{ Str::limit($survey->title, 40) }}
                                                </a>
                                            </h6>
                                            <small class="text-muted">
                                                {{ $survey->total_responses }} odpowiedzi | 
                                                {{ $survey->imported_at->format('d.m.Y') }}
                                                @if($survey->getAverageRating() > 0)
                                                    | Średnia: {{ $survey->getAverageRating() }}
                                                @endif
                                            </small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('surveys.show', $survey->id) }}" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('surveys.report', $survey->id) }}" class="btn btn-outline-success">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                                @if($course->surveys->count() > 3)
                                    <div class="text-center mt-2">
                                        <small class="text-muted">... i {{ $course->surveys->count() - 3 }} więcej</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Akcje -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Akcje</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('courses.edit', $course->id) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edytuj szkolenie
                                </a>
                                <a href="{{ route('participants.index', $course->id) }}" class="btn btn-info text-white">
                                    <i class="fas fa-users"></i> Uczestnicy ({{ $course->participants->count() }})
                                </a>
                                <a href="{{ route('surveys.import', $course->id) }}" class="btn btn-success">
                                    <i class="fas fa-file-import"></i> Importuj ankietę
                                </a>
                                <form action="{{ route('courses.destroy', $course->id) }}" method="POST" class="d-grid">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="btn btn-danger" 
                                            onclick="return confirm('Czy na pewno chcesz usunąć to szkolenie?')">
                                        <i class="fas fa-trash"></i> Usuń szkolenie
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

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista szkoleń') }}
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
            
            @if(session('warning'))
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {!! session('warning') !!}
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('courses.create') }}" class="btn btn-primary">Dodaj szkolenie</a>
                <div class="d-flex align-items-center gap-3">
                    <!-- Button do generowania PDF -->
                    <a href="{{ route('courses.pdf', request()->query()) }}" class="btn btn-success" target="_blank">
                        <i class="fas fa-file-pdf"></i> Wygeneruj listę kursów PDF
                    </a>
                    <!-- Button do generowania statystyk -->
                    <a href="{{ route('courses.statistics', request()->query()) }}" class="btn btn-info" target="_blank">
                        <i class="fas fa-chart-bar"></i> Wygeneruj statystyki szkoleń
                    </a>
                    <!-- Opcje paginacji -->
                    <form method="GET" action="{{ route('courses.index') }}" class="d-flex align-items-center gap-2">
                        @foreach(request()->query() as $key => $value)
                            @if($key !== 'per_page')
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label for="per_page" class="form-label mb-0 fw-bold">Wyświetl:</label>
                        <select name="per_page" class="form-select" style="width: auto;" onchange="this.form.submit()">
                            <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                            <option value="200" {{ request('per_page', 10) == 200 ? 'selected' : '' }}>200</option>
                            <option value="all" {{ request('per_page', 10) == 'all' ? 'selected' : '' }}>Wszystkie</option>
                        </select>
                    </form>
                </div>
            </div>

            <form method="GET" action="{{ route('courses.index') }}" class="mb-4 p-3 bg-light rounded shadow-sm">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <div class="row g-3 align-items-end">
            
                    <!-- Wyszukiwarka -->
                    <div class="col-md-3">
                        <label for="search" class="form-label fw-bold">Wyszukaj</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Tytuł, instruktor, lokalizacja..."
                                   value="{{ request('search') }}"
                                   autocomplete="off">
                            @if(request('search'))
                                <a href="{{ route('courses.index', array_filter(request()->except('search'))) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </div>
                    </div>
            
                    <!-- Filtr: Termin -->
                    <div class="col-md-2">
                        <label for="date_filter" class="form-label fw-bold">Termin</label>
                        <select name="date_filter" class="form-select">
                            <option value="all" {{ request()->get('date_filter', 'upcoming') === 'all' ? 'selected' : '' }}>Wszystkie</option>                            
                            <option value="upcoming" {{ request()->get('date_filter', 'upcoming') === 'upcoming' ? 'selected' : '' }}>Nadchodzące</option>
                            <option value="past" {{ request()->get('date_filter') === 'past' ? 'selected' : '' }}>Archiwalne</option>
                        </select>                                                                                         
                    </div>

                    <!-- Filtr: Zakres dat -->
                    <div class="col-md-2">
                        <label for="date_from" class="form-label fw-bold">Data od</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="date_to" class="form-label fw-bold">Data do</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
            
                    <!-- Filtr: Płatność -->
                    <div class="col-md-1">
                        <label for="is_paid" class="form-label fw-bold">Płatność</label>
                        <select name="is_paid" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="1" {{ request('is_paid') == '1' ? 'selected' : '' }}>Płatne</option>
                            <option value="0" {{ request('is_paid') == '0' ? 'selected' : '' }}>Bezpłatne</option>
                        </select>
                    </div>
            
                    <!-- Filtr: Rodzaj kursu -->
                    <div class="col-md-1">
                        <label for="type" class="form-label fw-bold">Rodzaj</label>
                        <select name="type" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="online" {{ request('type') == 'online' ? 'selected' : '' }}>Online</option>
                            <option value="offline" {{ request('type') == 'offline' ? 'selected' : '' }}>Stacjonarne</option>
                        </select>
                    </div>
            
                    <!-- Filtr: Kategoria -->
                    <div class="col-md-1">
                        <label for="category" class="form-label fw-bold">Kategoria</label>
                        <select name="category" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="open" {{ request('category') == 'open' ? 'selected' : '' }}>Otwarte</option>
                            <option value="closed" {{ request('category') == 'closed' ? 'selected' : '' }}>Zamknięte</option>
                        </select>
                    </div>
{{--            
                    <!-- Filtr: Status aktywności -->
                    <div class="col-md-2">
                        <label for="is_active" class="form-label fw-bold">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Aktywne</option>
                            <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Nieaktywne</option>
                        </select>
                    </div> 
--}}            
                    <!-- Filtr: Source ID Old -->
                    <div class="col-md-2">
                        <label for="source_id_old" class="form-label fw-bold">Źródło</label>
                        <select name="source_id_old" class="form-select">
                            <option value="">Wszystkie</option>
                            @foreach($sourceIdOldOptions as $option)
                                <option value="{{ $option }}" {{ request('source_id_old') == $option ? 'selected' : '' }}>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
            
                    <!-- Filtr: Instruktor -->
                    <div class="col-md-2">
                        <label for="instructor_id" class="form-label fw-bold">Instruktor</label>
                        <select name="instructor_id" class="form-select">
                            <option value="">Wszyscy</option>
                            @foreach($instructors as $instructor)
                                <option value="{{ $instructor->id }}" {{ request('instructor_id') == $instructor->id ? 'selected' : '' }}>
                                    {{ $instructor->first_name }} {{ $instructor->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
            
                    <!-- Filtr: Seria -->
                    <div class="col-md-2">
                        <label for="course_series_id" class="form-label fw-bold">Seria</label>
                        <select name="course_series_id" class="form-select">
                            <option value="">Wszystkie</option>
                            @foreach($series as $seria)
                                <option value="{{ $seria->id }}" {{ request('course_series_id') == $seria->id ? 'selected' : '' }}>
                                    {{ $seria->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
            
                    <!-- Przycisk filtrowania i resetu -->
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter"></i> Filtruj</button>
                        <a href="{{ route('courses.index') }}" class="btn btn-secondary flex-grow-1"><i class="fas fa-sync-alt"></i> Resetuj</a>
                    </div>
            
                </div>
            </form>
            
            <!-- Informacja o wynikach wyszukiwania -->
            @if(request('search'))
                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <span>Wyniki wyszukiwania dla: <strong>"{{ request('search') }}"</strong> 
                    (znaleziono: {{ $filteredCount }} {{ $filteredCount == 1 ? 'szkolenie' : ($filteredCount < 5 ? 'szkolenia' : 'szkoleń') }})</span>
                    <a href="{{ route('courses.index', array_filter(request()->except('search'))) }}" class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="fas fa-times me-1"></i> Wyczyść wyszukiwanie
                    </a>
                </div>
            @endif
            
            <!-- Informacja o liczbie rekordów -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted">
                    <strong>Wyświetlane rekordy:</strong> {{ $filteredCount }}/{{ $totalCount }}
                    @if($filteredCount != $totalCount && !request('search'))
                        <span class="text-info">(po zastosowaniu filtrów)</span>
                    @endif
                </div>
                <div class="text-muted small">
                    Strona {{ $courses->currentPage() }} z {{ $courses->lastPage() }}
                </div>
            </div>
            
            @if($courses->count() > 0)
                <table class="table table-striped table-hover table-responsive">
                    <thead class="table-dark">
                    <tr>
                        <th class="text-center" style="width: 5%;">#id</th>
                        <th class="text-center" style="width: 6%;">id_old</th>
                        <th style="width: 8%;">
                            <a href="{{ route('courses.index', array_merge(request()->query(), ['sort' => 'start_date', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="text-light text-decoration-none">
                                Data
                                @php
                                    $currentSort = request()->get('sort', 'start_date');
                                    $currentDirection = request()->get('direction', request('date_filter') === 'upcoming' ? 'asc' : 'desc');
                                @endphp
                                @if($currentSort === 'start_date')
                                    @if($currentDirection === 'asc')
                                        🔼
                                    @else
                                        🔽
                                    @endif
                                @endif
                            </a>
                        </th>                                              
                        <th class="text-center" style="width: 9%;">Obrazek</th>
                        <th style="width: 25%;">Tytuł</th>
                        {{-- <th>Opis</th> --}}
                        <th style="width: 10%;">Rodzaj</th>
                        <th style="width: 18%;">Lokalizacja / Dostęp</th>
                        <th style="width: 6%;">Instruktor</th>
                        <th class="text-center" style="width: 3%;" title="Check lista">C</th>
                        <th class="text-center" style="width: 5%;" title="Uczestnicy">U</th>
                        <th class="text-center" style="width: 10%;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                    <tr class="{{ strtotime($course->end_date) < time() ? 'table-secondary text-muted' : '' }}">
                        <td class="text-center align-middle">{{ $course->id }}</td>
                        <td class="text-center align-middle small">
                            {{ $course->id_old ?? '-' }}
                            <div class="d-flex justify-content-center gap-1 mt-1">
                                @if($course->source_id_old === 'certgen_Publigo' && $course->id_old)
                                    <a href="https://zdalna-lekcja.pl/zamowienia/formularz/?idP={{ $course->id_old }}" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       title="Otwórz formularz zamówienia (idP={{ $course->id_old }})"
                                       class="text-success">
                                        <i class="bi bi-file-earmark-text-fill"></i>
                                    </a>
                                @endif
                                @if($course->surveys->isNotEmpty())
                                    @php
                                        $firstSurvey = $course->surveys->first();
                                    @endphp
                                    <a href="{{ route('surveys.show', $firstSurvey->id) }}" 
                                       title="Otwórz ankietę dla tego szkolenia"
                                       class="text-info">
                                        <i class="bi bi-clipboard-check-fill"></i>
                                    </a>
                                @endif
                                <div id="videoIcons{{ $course->id }}" 
                                     class="d-flex justify-content-center gap-1"
                                     data-course-id="{{ $course->id }}"
                                     data-course-title="{{ strip_tags(html_entity_decode($course->title, ENT_QUOTES | ENT_HTML5, 'UTF-8')) }}"
                                     data-course-date="{{ $course->start_date ? $course->start_date->format('d.m.Y') : '' }}"
                                     data-course-time="{{ $course->start_date && $course->start_date->format('H:i') !== '00:00' ? $course->start_date->format('H:i') : '' }}"
                                     data-course-instructor="{{ $course->instructor ? $course->instructor->first_name . ' ' . $course->instructor->last_name : '' }}">
                                    <!-- Ikonka kamerki - zawsze czerwona, prowadzi do modala z formularzem -->
                                    <button type="button" 
                                            class="btn btn-link p-0 text-danger border-0" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#videoModal{{ $course->id }}"
                                            title="Zarządzaj nagraniami">
                                        <i class="bi bi-camera-video"></i>
                                    </button>
                                    <!-- Ikonka monitora - tylko gdy są nagrania, pokazuje możliwość odtworzenia -->
                                    @if(isset($course->videos) && $course->videos->isNotEmpty())
                                        <button type="button" 
                                                class="btn btn-link p-0 text-primary border-0" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#videoPlayerModal{{ $course->id }}"
                                                title="Odtwórz nagrania ({{ $course->videos->count() }})"
                                                id="videoPlayerBtn{{ $course->id }}">
                                            <i class="bi bi-display"></i>
                                        </button>
                                    @endif
                                    <button type="button"
                                            class="btn btn-link p-0 border-0 {{ ($course->fileLinks?->count() ?? 0) > 0 ? 'text-primary' : 'text-secondary' }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#fileLinkModal{{ $course->id }}"
                                            title="Materiały / linki do plików ({{ $course->fileLinks?->count() ?? 0 }})">
                                        <i class="bi bi-folder2-open"></i>
                                    </button>
                                    @php
                                        $surveyLinksCount = $course->surveyLinks?->count() ?? 0;
                                        $surveyLinksColor = $surveyLinksCount > 0 ? 'text-primary' : 'text-secondary';
                                    @endphp
                                    <button type="button"
                                            id="surveyLinkBtn{{ $course->id }}"
                                            class="btn btn-link p-0 border-0 {{ $surveyLinksColor }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#surveyLinkModal{{ $course->id }}"
                                            title="Ankiety zewnętrzne ({{ $surveyLinksCount }})">
                                        <i class="bi bi-card-checklist"></i>
                                    </button>
                                </div>
                            </div>
                            <br>
                            <small class="text-muted">{{ $course->source_id_old ?? '-' }}</small>
                        </td>
                        <td class="align-middle">
                            @if ($course->start_date && $course->end_date)
                                {{ date('d.m.Y H:i', strtotime($course->start_date)) }}<br>
                                @php
                                    $startDateTime = \Carbon\Carbon::parse($course->start_date);
                                    $endDateTime = \Carbon\Carbon::parse($course->end_date);
                                    $durationMinutes = $startDateTime->diffInMinutes($endDateTime);
                                @endphp
                                <small class="text-muted">{{ $durationMinutes }} min</small>
                            @else
                                {{ $course->start_date ? date('d.m.Y H:i', strtotime($course->start_date)) : 'Brak daty' }}
                            @endif
                        </td>                        
                        <td class="text-center align-middle">
                            @if ($course->image)
                                <img src="{{ asset('storage/' . $course->image) }}" alt="Obrazek kursu" width="100" class="img-thumbnail">
                            @else
                                <span></span>
                            @endif
                        </td>
                        <td class="align-middle"><strong>{!! $course->title !!}</strong></td>
                       {{-- <td>{{ Str::limit($course->description, 50) }}</td> --}}
                        <td class="align-middle">
                            <span class="badge {{ $course->is_paid == true ? 'bg-warning' : 'bg-success' }}">
                                {{ $course->is_paid ? 'Płatne' : 'Bezpłatne' }}
                            </span> <br>
                            <span class="small">{{ $course->type === 'offline' ? 'Stacjonarne' : ucfirst($course->type) }}</span> <br>
                            <span class="small">{{ $course->category === 'open' ? 'Otwarte' : 'Zamknięte' }}</span> <br>
                            <span class="badge {{ $course->is_active ? 'bg-success' : 'bg-danger' }}">
                                {{ $course->is_active ? 'Aktywne' : 'Nieaktywne' }}
                            </span>                            
                        </td>
                        <td class="align-middle small">
                            @if ($course->type === 'offline' && $course->location)
                            <strong>{{ $course->location->location_name ?? 'Brak nazwy lokalizacji' }}</strong><br>
                            {{ $course->location->address ?? 'Brak adresu' }}<br>
                            {{ $course->location->postal_code ?? '' }} {{ $course->location->post_office ?? '' }}
                            @elseif ($course->type === 'online' && $course->onlineDetails)
                                <strong>Platforma:</strong> {{ $course->onlineDetails->platform ?? 'Nieznana' }}<br>
                                <a href="{{ $course->onlineDetails->meeting_link ?? '#' }}" class="btn btn-sm btn-outline-primary mt-1" target="_blank">Dołącz do spotkania</a>
                            @else
                                Brak danych
                            @endif
                        </td>
                        <td class="align-middle">
                            {{ $course->instructor ? $course->instructor->getFullTitleNameAttribute() : 'Brak instruktora' }}
                        </td>
                        <td class="text-center align-middle">
                            <div class="d-flex flex-column align-items-center gap-1">
                                @php
                                    $certStatus = $course->certificate_download_status ?? 'in_preparation';
                                @endphp
                                @if($certStatus === 'download_enabled')
                                    <i class="bi bi-award-fill text-success" title="Udostępniono pobieranie zaświadczeń (link na pnedu.pl)" style="font-size: 1.2em;"></i>
                                @elseif($certStatus === 'in_preparation')
                                    <i class="bi bi-award-fill text-warning" title="Zaświadczenie w przygotowaniu" style="font-size: 1.2em;"></i>
                                @else
                                    <i class="bi bi-award-fill text-danger" title="Brak zaświadczenia" style="font-size: 1.2em;"></i>
                                @endif
                                @if(!empty(trim($course->description ?? '')))
                                    <i class="bi bi-check-circle-fill text-success" title="podano zakres szkolenia" style="font-size: 1.2em;"></i>
                                @else
                                    <i class="bi bi-x-circle-fill text-danger" title="brak zakresu szkolenia" style="font-size: 1.2em;"></i>
                                @endif
                                @if(!empty(trim($course->notatki ?? '')))
                                    <i class="bi bi-journal-text text-primary" 
                                       title="{{ e($course->notatki) }}" 
                                       style="font-size: 1.2em; cursor: help;"></i>
                                @endif
                            </div>
                        </td>
                        <td class="text-center align-middle">
                            <span class="badge bg-info" title="Liczba uczestników">{{ $course->participants->count() }}</span><br>
                            @php
                                // Upewnij się, że uczestnicy są załadowani z potrzebnymi polami
                                if (!$course->relationLoaded('participants')) {
                                    $course->load(['participants' => function($query) {
                                        $query->select('id', 'course_id', 'first_name', 'last_name', 'birth_date', 'birth_place');
                                    }]);
                                }
                                
                                $completeDataCount = $course->participants->filter(function($participant) {
                                    return !empty(trim($participant->last_name ?? '')) 
                                        && !empty(trim($participant->first_name ?? '')) 
                                        && !is_null($participant->birth_date) 
                                        && !empty(trim($participant->birth_place ?? ''));
                                })->count();
                            @endphp
                            <span class="badge bg-success text-white" title="Liczba uczestników z kompletnymi danymi (Nazwisko, Imię, Data urodzenia, Miejsce urodzenia)">{{ $completeDataCount }}</span><br>
                            <span class="badge bg-warning" title="Liczba wygenerowanych zaświadczeń">{{ $course->certificates->count() }}</span><br>
                            @if($course->orders_count > 0)
                                <a href="{{ route('form-orders.index', ['filter' => 'new', 'search' => $course->id]) }}" 
                                   class="badge bg-danger text-decoration-none" 
                                   title="Kliknij, aby zobaczyć nie wprowadzone zamówienia dla tego szkolenia (filtr po ID kursu / product_id)">
                                    {{ $course->orders_count }}
                                </a>
                            @else
                                <span class="badge bg-secondary" title="Liczba nie wprowadzonych zamówień">0</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            <div class="d-flex flex-column gap-1">
                                <a href="{{ route('courses.show', $course->id) }}" class="btn btn-primary btn-sm">Podgląd</a>
                                <a href="{{ route('courses.edit', array_merge(['id' => $course->id], request()->query())) }}" class="btn btn-warning btn-sm">Edytuj</a>
                                <button type="button" class="btn btn-danger btn-sm w-100" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal{{ $course->id }}">
                                    <i class="bi bi-trash"></i> Usuń
                                </button>
                                <a href="{{ route('participants.index', $course->id) }}" class="btn btn-info btn-sm text-white">Uczestnicy</a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            {{-- Modale potwierdzenia usunięcia --}}
            @foreach ($courses as $course)
            <div class="modal fade" id="deleteModal{{ $course->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $course->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel{{ $course->id }}">
                                <i class="bi bi-exclamation-triangle"></i> Potwierdzenie usunięcia
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Czy na pewno chcesz usunąć szkolenie <strong>#{{ $course->id }}</strong>?</p>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">Szczegóły szkolenia:</h6>
                                <ul class="mb-0">
                                    <li><strong>Tytuł:</strong> {!! $course->title !!}</li>
                                    <li><strong>Instruktor:</strong> {{ $course->instructor ? $course->instructor->getFullTitleNameAttribute() : 'Brak instruktora' }}</li>
                                    <li><strong>Data:</strong> {{ $course->start_date ? $course->start_date->format('d.m.Y H:i') : 'Brak daty' }}</li>
                                    <li><strong>Uczestnicy:</strong> {{ $course->participants->count() }}</li>
                                    <li><strong>Zaświadczenia:</strong> {{ $course->certificates->count() }}</li>
                                </ul>
                            </div>
                            <p class="text-muted mt-3">
                                <i class="bi bi-info-circle"></i>
                                Szkolenie zostanie przeniesione do kosza (soft delete) i będzie można je przywrócić.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </button>
                            <form action="{{ route('courses.destroy', $course->id) }}" 
                                  method="POST" 
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Usuń szkolenie
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @else
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    @if(request('search'))
                        <h4 class="text-muted">Brak wyników wyszukiwania</h4>
                        <p class="text-muted">Nie znaleziono szkoleń pasujących do frazy: <strong>"{{ request('search') }}"</strong></p>
                        <a href="{{ route('courses.index', array_filter(request()->except('search'))) }}" class="btn btn-primary">
                            <i class="fas fa-list me-1"></i> Pokaż wszystkie szkolenia
                        </a>
                    @else
                        <h4 class="text-muted">Brak szkoleń</h4>
                        <p class="text-muted">Nie ma szkoleń spełniających wybrane kryteria.</p>
                        <a href="{{ route('courses.index') }}" class="btn btn-primary">
                            <i class="fas fa-list me-1"></i> Pokaż wszystkie szkolenia
                        </a>
                    @endif
                </div>
            @endif

            @if($courses->count() > 0)
                <div class="mt-3">
                    {{-- {{ $courses->links() }} --}}
                    {{ $courses->appends(request()->query())->links() }}
                </div>
            @endif

        </div>
    </div>

    <!-- Modale do zarządzania nagraniami -->
    @foreach($courses as $course)
    <div class="modal fade" id="videoModal{{ $course->id }}" tabindex="-1" aria-labelledby="videoModalLabel{{ $course->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoModalLabel{{ $course->id }}">
                        <i class="bi bi-camera-video me-2"></i>
                        Nagrania - {{ $course->title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Lista istniejących nagrań -->
                    <div id="videosList{{ $course->id }}">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Ładowanie...</span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Formularz dodawania nowego nagrania -->
                    <h6 class="mb-3">Dodaj nowe nagranie</h6>
                    <form id="videoForm{{ $course->id }}" class="needs-validation" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="video_url{{ $course->id }}" class="form-label">URL nagrania <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="video_url{{ $course->id }}" name="video_url" required 
                                   placeholder="https://www.youtube.com/watch?v=... lub https://vimeo.com/...">
                            <div class="invalid-feedback">Proszę podać prawidłowy URL.</div>
                        </div>

                        <div class="mb-3">
                            <label for="platform{{ $course->id }}" class="form-label">Platforma <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform{{ $course->id }}" name="platform" required>
                                <option value="">Wybierz platformę</option>
                                <option value="youtube">YouTube</option>
                                <option value="vimeo" selected>Vimeo</option>
                            </select>
                            <div class="invalid-feedback">Proszę wybrać platformę.</div>
                        </div>

                        <div class="mb-3">
                            <label for="title{{ $course->id }}" class="form-label">Tytuł (opcjonalnie)</label>
                            <input type="text" class="form-control" id="title{{ $course->id }}" name="title" 
                                   placeholder="Np. Nagranie z dnia 1">
                        </div>

                        <div class="mb-3">
                            <label for="order{{ $course->id }}" class="form-label">Kolejność</label>
                            <input type="number" class="form-control" id="order{{ $course->id }}" name="order" value="1" min="1">
                            <small class="form-text text-muted">Niższa liczba = wyższa kolejność</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>
                                Dodaj nagranie
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    <!-- Modale do odtwarzania nagrań -->
    @foreach($courses as $course)
    @if(isset($course->videos) && $course->videos->isNotEmpty())
    <div class="modal fade" id="videoPlayerModal{{ $course->id }}" tabindex="-1" aria-labelledby="videoPlayerModalLabel{{ $course->id }}" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="flex-grow-1">
                        <h5 class="modal-title mb-1" id="videoPlayerModalLabel{{ $course->id }}">
                            <i class="bi bi-display me-2"></i>
                            Nagrania - {{ strip_tags(html_entity_decode($course->title, ENT_QUOTES | ENT_HTML5, 'UTF-8')) }}
                        </h5>
                        <div class="text-muted small">
                            @if($course->start_date)
                                <i class="bi bi-calendar-event me-1"></i>
                                {{ $course->start_date->format('d.m.Y') }}
                                @if($course->start_date->format('H:i') !== '00:00')
                                    <i class="bi bi-clock me-1 ms-2"></i>
                                    {{ $course->start_date->format('H:i') }}
                                @endif
                            @endif
                            @if($course->instructor)
                                <span class="ms-2">
                                    <i class="bi bi-person me-1"></i>
                                    {{ $course->instructor->first_name }} {{ $course->instructor->last_name }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        @foreach($course->videos ?? [] as $video)
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        @if($video->platform === 'youtube')
                                            <i class="bi bi-youtube text-danger me-2"></i>
                                        @else
                                            <i class="bi bi-vimeo text-info me-2"></i>
                                        @endif
                                        {{ strip_tags(html_entity_decode($video->title ?: 'Nagranie ' . $loop->iteration, ENT_QUOTES | ENT_HTML5, 'UTF-8')) }}
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="ratio ratio-16x9">
                                        <iframe 
                                            data-src="{{ $video->getEmbedUrl() }}" 
                                            src="" 
                                            frameborder="0" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                            allowfullscreen
                                            loading="lazy">
                                        </iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endforeach

    @foreach($courses as $course)
    <div class="modal fade" id="surveyLinkModal{{ $course->id }}" tabindex="-1" aria-labelledby="surveyLinkModalLabel{{ $course->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="surveyLinkModalLabel{{ $course->id }}">
                        <i class="bi bi-card-checklist me-2"></i>
                        Ankiety zewnętrzne — {{ $course->title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Wklej publiczny link do formularza, np. Google Forms (<code>docs.google.com/forms/...</code> lub <code>forms.gle/...</code>),
                        Microsoft Forms, Typeform itp. Możesz opcjonalnie ustawić okno czasowe.
                    </p>
                    <div id="surveyLinksList{{ $course->id }}">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-3">Dodaj nową ankietę</h6>
                    <form id="surveyLinkForm{{ $course->id }}" class="needs-validation" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="survey_link_url{{ $course->id }}" class="form-label">URL ankiety <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="survey_link_url{{ $course->id }}" name="url" required
                                   placeholder="https://docs.google.com/forms/d/e/.../viewform">
                            <div class="invalid-feedback">Podaj prawidłowy adres URL (https).</div>
                        </div>
                        <div class="mb-3">
                            <label for="survey_link_title{{ $course->id }}" class="form-label">Tytuł / opis (opcjonalnie)</label>
                            <input type="text" class="form-control" id="survey_link_title{{ $course->id }}" name="title"
                                   placeholder="Np. Ankieta ewaluacyjna po szkoleniu">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="survey_link_opens_at{{ $course->id }}" class="form-label">Otwarcie (opcjonalnie)</label>
                                <input type="datetime-local" class="form-control" id="survey_link_opens_at{{ $course->id }}" name="opens_at">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="survey_link_closes_at{{ $course->id }}" class="form-label">Zamknięcie (opcjonalnie)</label>
                                <input type="datetime-local" class="form-control" id="survey_link_closes_at{{ $course->id }}" name="closes_at">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="survey_link_order{{ $course->id }}" class="form-label">Kolejność</label>
                                <input type="number" class="form-control" id="survey_link_order{{ $course->id }}" name="order" value="1" min="0">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="survey_link_is_active{{ $course->id }}" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="survey_link_is_active{{ $course->id }}">Aktywna</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Dodaj ankietę</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fileLinkModal{{ $course->id }}" tabindex="-1" aria-labelledby="fileLinkModalLabel{{ $course->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fileLinkModalLabel{{ $course->id }}">
                        <i class="bi bi-link-45deg me-2"></i>
                        Materiały do pobrania (pliki) — {{ $course->title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Wklej adres udostępniony z Dysku Google lub inny publiczny link HTTPS.</p>
                    <div id="fileLinksList{{ $course->id }}">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-3">Dodaj nowy link</h6>
                    <form id="fileLinkForm{{ $course->id }}" class="needs-validation" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="file_link_url{{ $course->id }}" class="form-label">URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="file_link_url{{ $course->id }}" name="url" required placeholder="https://drive.google.com/...">
                            <div class="invalid-feedback">Podaj prawidłowy adres URL (https).</div>
                        </div>
                        <div class="mb-3">
                            <label for="file_link_title{{ $course->id }}" class="form-label">Opis (opcjonalnie)</label>
                            <input type="text" class="form-control" id="file_link_title{{ $course->id }}" name="title" placeholder="Np. Slajdy">
                        </div>
                        <div class="mb-3">
                            <label for="file_link_order{{ $course->id }}" class="form-label">Kolejność</label>
                            <input type="number" class="form-control" id="file_link_order{{ $course->id }}" name="order" value="1" min="0">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Dodaj link</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dla każdego modala załaduj listę nagrań
            @foreach($courses as $course)
            const modal{{ $course->id }} = document.getElementById('videoModal{{ $course->id }}');
            if (modal{{ $course->id }}) {
                modal{{ $course->id }}.addEventListener('show.bs.modal', function() {
                    loadVideos{{ $course->id }}();
                });
            }

            // Funkcja ładowania nagrań
            function loadVideos{{ $course->id }}() {
                fetch('{{ route('courses.videos.index', $course->id) }}')
                    .then(response => response.json())
                    .then(data => {
                        const videosList = document.getElementById('videosList{{ $course->id }}');
                        const orderInput = document.getElementById('order{{ $course->id }}');

                        if (data.success && data.videos.length > 0) {
                            let html = '<div class="list-group mb-3">';
                            
                            // Ustaw sugerowaną kolejność (liczba nagrań + 1)
                            if (orderInput) {
                                orderInput.value = data.videos.length + 1;
                            }

                            data.videos.forEach(video => {
                                const platformIcon = video.platform === 'youtube' ? 'bi-youtube text-danger' : 'bi-vimeo text-info';
                                html += `
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <i class="bi ${platformIcon} me-2"></i>
                                            <strong>${video.title || 'Brak tytułu'}</strong>
                                            <br>
                                            <small class="text-muted">${video.video_url}</small>
                                            <span class="badge bg-light text-dark border ms-2">Nr ${video.order}</span>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteVideo{{ $course->id }}(${video.id})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            videosList.innerHTML = html;
                        } else {
                            videosList.innerHTML = '<p class="text-muted text-center">Brak nagrań. Dodaj pierwsze nagranie używając formularza poniżej.</p>';
                            
                            // Ustaw sugerowaną kolejność na 1, jeśli brak nagrań
                            if (orderInput) {
                                orderInput.value = 1;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Błąd:', error);
                        document.getElementById('videosList{{ $course->id }}').innerHTML = '<div class="alert alert-danger">Nie udało się załadować nagrań.</div>';
                    });
            }

            // Obsługa formularza dodawania nagrania
            const form{{ $course->id }} = document.getElementById('videoForm{{ $course->id }}');
            if (form{{ $course->id }}) {
                form{{ $course->id }}.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!form{{ $course->id }}.checkValidity()) {
                        form{{ $course->id }}.classList.add('was-validated');
                        return;
                    }

                    const formData = new FormData(form{{ $course->id }});
                    
                    fetch('{{ route('courses.videos.store', $course->id) }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            form{{ $course->id }}.reset();
                            form{{ $course->id }}.classList.remove('was-validated');
                            loadVideos{{ $course->id }}();
                            
                            // Sprawdź czy ikonka monitora już istnieje, jeśli nie - dodaj ją
                            updateVideoPlayerIcon{{ $course->id }}();
                            
                            // Pokaż komunikat sukcesu
                            const alert = document.createElement('div');
                            alert.className = 'alert alert-success alert-dismissible fade show';
                            alert.innerHTML = `
                                ${data.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            form{{ $course->id }}.parentElement.insertBefore(alert, form{{ $course->id }});
                            setTimeout(() => alert.remove(), 3000);
                        } else {
                            alert('Błąd: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Błąd:', error);
                        alert('Wystąpił błąd podczas dodawania nagrania.');
                    });
                });
            }

            // Funkcja aktualizacji ikonki odtwarzacza wideo w tabeli
            function updateVideoPlayerIcon{{ $course->id }}() {
                const videoIconsContainer = document.getElementById('videoIcons{{ $course->id }}');
                const existingPlayerBtn = document.getElementById('videoPlayerBtn{{ $course->id }}');
                
                if (!videoIconsContainer) return;
                
                // Sprawdź ile jest wideo poprzez fetch
                fetch('{{ route('courses.videos.index', $course->id) }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.videos && data.videos.length > 0) {
                            // Jeśli ikonka nie istnieje, dodaj ją
                            if (!existingPlayerBtn) {
                                const playerBtn = document.createElement('button');
                                playerBtn.type = 'button';
                                playerBtn.className = 'btn btn-link p-0 text-primary border-0';
                                playerBtn.setAttribute('data-bs-toggle', 'modal');
                                playerBtn.setAttribute('data-bs-target', '#videoPlayerModal{{ $course->id }}');
                                playerBtn.setAttribute('title', `Odtwórz nagrania (${data.videos.length})`);
                                playerBtn.id = 'videoPlayerBtn{{ $course->id }}';
                                playerBtn.innerHTML = '<i class="bi bi-display"></i>';
                                
                                // Dodaj ikonkę po ikonce kamerki
                                const cameraBtn = videoIconsContainer.querySelector('button[data-bs-target="#videoModal{{ $course->id }}"]');
                                if (cameraBtn) {
                                    cameraBtn.parentNode.insertBefore(playerBtn, cameraBtn.nextSibling);
                                } else {
                                    videoIconsContainer.appendChild(playerBtn);
                                }
                                
                                // Zaktualizuj modal odtwarzania wideo jeśli nie istnieje
                                updateVideoPlayerModal{{ $course->id }}(data.videos);
                            } else {
                                // Zaktualizuj tytuł z liczbą wideo
                                existingPlayerBtn.setAttribute('title', `Odtwórz nagrania (${data.videos.length})`);
                                // Zaktualizuj modal odtwarzania
                                updateVideoPlayerModal{{ $course->id }}(data.videos);
                            }
                        } else {
                            // Jeśli nie ma wideo, usuń ikonkę (jeśli istnieje)
                            if (existingPlayerBtn) {
                                existingPlayerBtn.remove();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Błąd podczas sprawdzania wideo:', error);
                    });
            }

            // Funkcja aktualizacji modala odtwarzania wideo
            function updateVideoPlayerModal{{ $course->id }}(videos) {
                let modal = document.getElementById('videoPlayerModal{{ $course->id }}');
                const videoIconsContainer = document.getElementById('videoIcons{{ $course->id }}');
                
                if (!modal) {
                    // Jeśli modal nie istnieje, utwórz go
                    if (!videoIconsContainer) return;
                    
                    const courseTitle = videoIconsContainer.getAttribute('data-course-title') || 'Szkolenie';
                    const courseDate = videoIconsContainer.getAttribute('data-course-date') || '';
                    const courseTime = videoIconsContainer.getAttribute('data-course-time') || '';
                    const courseInstructor = videoIconsContainer.getAttribute('data-course-instructor') || '';
                    
                    // Utwórz modal HTML
                    let modalHtml = `
                        <div class="modal fade" id="videoPlayerModal{{ $course->id }}" tabindex="-1" aria-labelledby="videoPlayerModalLabel{{ $course->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div class="flex-grow-1">
                                            <h5 class="modal-title mb-1" id="videoPlayerModalLabel{{ $course->id }}">
                                                <i class="bi bi-display me-2"></i>
                                                Nagrania - ${escapeHtml(courseTitle)}
                                            </h5>
                                            <div class="text-muted small">
                    `;
                    
                    if (courseDate) {
                        modalHtml += `<i class="bi bi-calendar-event me-1"></i>${escapeHtml(courseDate)}`;
                        if (courseTime) {
                            modalHtml += `<i class="bi bi-clock me-1 ms-2"></i>${escapeHtml(courseTime)}`;
                        }
                    }
                    
                    if (courseInstructor) {
                        modalHtml += `<span class="ms-2"><i class="bi bi-person me-1"></i>${escapeHtml(courseInstructor)}</span>`;
                    }
                    
                    modalHtml += `
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row"></div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Dodaj modal do body
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    modal = document.getElementById('videoPlayerModal{{ $course->id }}');
                    
                    // Dodaj event listenery dla nowego modala
                    setupVideoPlayerModal{{ $course->id }}();
                }
                
                // Zaktualizuj zawartość modala
                const modalBody = modal.querySelector('.modal-body .row');
                if (modalBody) {
                    modalBody.innerHTML = '';
                    videos.forEach((video, index) => {
                        const platformIcon = video.platform === 'youtube' ? 'bi-youtube text-danger' : 'bi-vimeo text-info';
                        const videoTitle = video.title || `Nagranie ${index + 1}`;
                        const embedUrl = video.embed_url || video.video_url;
                        
                        modalBody.innerHTML += `
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="bi ${platformIcon} me-2"></i>
                                            ${escapeHtml(videoTitle)}
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="ratio ratio-16x9">
                                            <iframe 
                                                data-src="${embedUrl}" 
                                                src="" 
                                                frameborder="0" 
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                                allowfullscreen
                                                loading="lazy">
                                            </iframe>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
            }

            // Funkcja konfiguracji event listenerów dla modala odtwarzania
            function setupVideoPlayerModal{{ $course->id }}() {
                const videoPlayerModal{{ $course->id }} = document.getElementById('videoPlayerModal{{ $course->id }}');
                if (videoPlayerModal{{ $course->id }}) {
                    // Przy otwieraniu modala - załaduj wideo
                    videoPlayerModal{{ $course->id }}.addEventListener('show.bs.modal', function() {
                        const iframes = videoPlayerModal{{ $course->id }}.querySelectorAll('iframe[data-src]');
                        iframes.forEach(iframe => {
                            if (iframe.getAttribute('data-src') && !iframe.getAttribute('src')) {
                                iframe.setAttribute('src', iframe.getAttribute('data-src'));
                            }
                        });
                    });

                    // Przy zamykaniu modala - zatrzymaj wideo
                    videoPlayerModal{{ $course->id }}.addEventListener('hide.bs.modal', function() {
                        const iframes = videoPlayerModal{{ $course->id }}.querySelectorAll('iframe[data-src]');
                        iframes.forEach(iframe => {
                            iframe.setAttribute('src', '');
                        });
                    });
                }
            }

            // Funkcje pomocnicze do ekstrakcji ID wideo
            function extractYouTubeId(url) {
                const patterns = [
                    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
                    /youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/
                ];
                for (const pattern of patterns) {
                    const match = url.match(pattern);
                    if (match) return match[1];
                }
                return null;
            }

            function extractVimeoId(url) {
                const match = url.match(/vimeo\.com\/(\d+)/);
                return match ? match[1] : null;
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Funkcja usuwania nagrania
            window.deleteVideo{{ $course->id }} = function(videoId) {
                if (!confirm('Czy na pewno chcesz usunąć to nagranie?')) {
                    return;
                }

                fetch('{{ route('courses.videos.destroy', [$course->id, ':videoId']) }}'.replace(':videoId', videoId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadVideos{{ $course->id }}();
                        // Zaktualizuj ikonkę odtwarzacza po usunięciu
                        updateVideoPlayerIcon{{ $course->id }}();
                    } else {
                        alert('Błąd: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Błąd:', error);
                    alert('Wystąpił błąd podczas usuwania nagrania.');
                });
            };

            const fileLinkModalIdx{{ $course->id }} = document.getElementById('fileLinkModal{{ $course->id }}');
            if (fileLinkModalIdx{{ $course->id }}) {
                fileLinkModalIdx{{ $course->id }}.addEventListener('show.bs.modal', function() {
                    loadFileLinks{{ $course->id }}();
                });
            }

            function loadFileLinks{{ $course->id }}() {
                fetch('{{ route('courses.file-links.index', $course->id) }}')
                    .then(response => response.json())
                    .then(data => {
                        const listEl = document.getElementById('fileLinksList{{ $course->id }}');
                        const orderInput = document.getElementById('file_link_order{{ $course->id }}');
                        if (!listEl) return;
                        if (data.success && data.file_links && data.file_links.length > 0) {
                            if (orderInput) orderInput.value = data.file_links.length + 1;
                            let html = '<div class="list-group mb-3">';
                            data.file_links.forEach(link => {
                                const escAttr = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                                const escHtml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                                const title = escHtml(link.title || '');
                                const urlRaw = link.url || '';
                                const urlHref = escAttr(urlRaw);
                                const urlText = escHtml(urlRaw);
                                html += `<div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1 me-2"><strong>${title || 'Bez tytułu'}</strong><br><small class="text-break"><a href="${urlHref}" target="_blank" rel="noopener noreferrer" class="text-muted">${urlText}</a></small>
                                    <span class="badge bg-light text-dark border ms-2">Nr ${link.order}</span></div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFileLink{{ $course->id }}(${link.id})"><i class="bi bi-trash"></i></button>
                                </div>`;
                            });
                            html += '</div>';
                            listEl.innerHTML = html;
                        } else {
                            listEl.innerHTML = '<p class="text-muted text-center">Brak linków. Dodaj pierwszy poniżej.</p>';
                            if (orderInput) orderInput.value = 1;
                        }
                    })
                    .catch(() => {
                        const listEl = document.getElementById('fileLinksList{{ $course->id }}');
                        if (listEl) listEl.innerHTML = '<div class="alert alert-danger">Nie udało się załadować listy.</div>';
                    });
            }

            const fileLinkFormIdx{{ $course->id }} = document.getElementById('fileLinkForm{{ $course->id }}');
            if (fileLinkFormIdx{{ $course->id }}) {
                fileLinkFormIdx{{ $course->id }}.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!fileLinkFormIdx{{ $course->id }}.checkValidity()) {
                        fileLinkFormIdx{{ $course->id }}.classList.add('was-validated');
                        return;
                    }
                    const fd = new FormData(fileLinkFormIdx{{ $course->id }});
                    fetch('{{ route('courses.file-links.store', $course->id) }}', {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            fileLinkFormIdx{{ $course->id }}.reset();
                            fileLinkFormIdx{{ $course->id }}.classList.remove('was-validated');
                            loadFileLinks{{ $course->id }}();
                            const folderBtn = document.querySelector('[data-bs-target="#fileLinkModal{{ $course->id }}"]');
                            if (folderBtn && data.file_link) {
                                fetch('{{ route('courses.file-links.index', $course->id) }}').then(r => r.json()).then(d => {
                                    if (d.success && d.file_links) {
                                        folderBtn.setAttribute('title', 'Materiały / linki do plików (' + d.file_links.length + ')');
                                        folderBtn.classList.remove('text-primary', 'text-secondary');
                                        folderBtn.classList.add(d.file_links.length > 0 ? 'text-primary' : 'text-secondary');
                                    }
                                });
                            }
                        } else {
                            alert('Błąd: ' + (data.message || ''));
                        }
                    })
                    .catch(() => alert('Błąd dodawania linku.'));
                });
            }

            window.deleteFileLink{{ $course->id }} = function(linkId) {
                if (!confirm('Usunąć ten link?')) return;
                fetch('{{ route('courses.file-links.destroy', [$course->id, ':fileLinkId']) }}'.replace(':fileLinkId', linkId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadFileLinks{{ $course->id }}();
                        const folderBtn = document.querySelector('[data-bs-target="#fileLinkModal{{ $course->id }}"]');
                        if (folderBtn) {
                            fetch('{{ route('courses.file-links.index', $course->id) }}').then(r => r.json()).then(d => {
                                if (d.success && d.file_links) {
                                    folderBtn.setAttribute('title', 'Materiały / linki do plików (' + d.file_links.length + ')');
                                    folderBtn.classList.remove('text-primary', 'text-secondary');
                                    folderBtn.classList.add(d.file_links.length > 0 ? 'text-primary' : 'text-secondary');
                                }
                            });
                        }
                    } else {
                        alert('Błąd: ' + data.message);
                    }
                })
                .catch(() => alert('Błąd usuwania.'));
            };

            // ---------- Linki do ankiet zewnętrznych ----------
            const surveyLinkModalIdx{{ $course->id }} = document.getElementById('surveyLinkModal{{ $course->id }}');
            if (surveyLinkModalIdx{{ $course->id }}) {
                surveyLinkModalIdx{{ $course->id }}.addEventListener('show.bs.modal', function() {
                    loadSurveyLinks{{ $course->id }}();
                });
            }

            function refreshSurveyLinkBtn{{ $course->id }}() {
                const btn = document.getElementById('surveyLinkBtn{{ $course->id }}');
                if (!btn) return;
                fetch('{{ route('courses.survey-links.index', $course->id) }}').then(r => r.json()).then(d => {
                    if (d.success && d.survey_links) {
                        btn.setAttribute('title', 'Ankiety zewnętrzne (' + d.survey_links.length + ')');
                        btn.classList.remove('text-primary', 'text-secondary');
                        btn.classList.add(d.survey_links.length > 0 ? 'text-primary' : 'text-secondary');
                    }
                });
            }

            function loadSurveyLinks{{ $course->id }}() {
                fetch('{{ route('courses.survey-links.index', $course->id) }}')
                    .then(response => response.json())
                    .then(data => {
                        const listEl = document.getElementById('surveyLinksList{{ $course->id }}');
                        const orderInput = document.getElementById('survey_link_order{{ $course->id }}');
                        if (!listEl) return;

                        if (data.success && data.survey_links && data.survey_links.length > 0) {
                            if (orderInput) orderInput.value = data.survey_links.length + 1;
                            const escAttr = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                            const escHtml = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            const fmt = (iso) => {
                                if (!iso) return '';
                                const d = new Date(iso);
                                if (isNaN(d.getTime())) return '';
                                const pad = n => String(n).padStart(2, '0');
                                return `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
                            };

                            let html = '<div class="list-group mb-3">';
                            data.survey_links.forEach(link => {
                                const title = escHtml(link.title || '');
                                const urlHref = escAttr(link.url || '');
                                const urlText = escHtml(link.url || '');
                                const providerLabel = escHtml(link.provider_label || '');
                                const providerIcon = escAttr(link.provider_icon || 'fas fa-clipboard-list text-secondary');

                                let badges = `<span class="badge bg-light text-dark border ms-2">Nr ${link.order}</span>`;
                                badges += link.is_active
                                    ? '<span class="badge bg-success ms-1">Aktywna</span>'
                                    : '<span class="badge bg-secondary ms-1">Wyłączona</span>';
                                if (!link.is_available_now && link.is_active) {
                                    badges += '<span class="badge bg-warning text-dark ms-1" title="Poza oknem czasowym"><i class="fas fa-clock"></i></span>';
                                }

                                let timing = '';
                                if (link.opens_at || link.closes_at) {
                                    timing = '<br><small class="text-muted">';
                                    if (link.opens_at) timing += `<i class="fas fa-play me-1"></i>${fmt(link.opens_at)}`;
                                    if (link.opens_at && link.closes_at) timing += ' &nbsp;–&nbsp; ';
                                    if (link.closes_at) timing += `<i class="fas fa-stop me-1"></i>${fmt(link.closes_at)}`;
                                    timing += '</small>';
                                }

                                html += `<div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1 me-2">
                                        <div><i class="${providerIcon} me-1"></i><strong>${title || 'Bez tytułu'}</strong> <small class="text-muted ms-1">(${providerLabel})</small></div>
                                        <small class="text-break"><a href="${urlHref}" target="_blank" rel="noopener noreferrer" class="text-muted">${urlText}</a></small>
                                        ${timing}
                                        <div class="mt-1">${badges}</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSurveyLink{{ $course->id }}(${link.id})" title="Usuń"><i class="bi bi-trash"></i></button>
                                </div>`;
                            });
                            html += '</div>';
                            listEl.innerHTML = html;
                        } else {
                            listEl.innerHTML = '<p class="text-muted text-center">Brak ankiet. Dodaj pierwszą poniżej.</p>';
                            if (orderInput) orderInput.value = 1;
                        }
                    })
                    .catch(() => {
                        const listEl = document.getElementById('surveyLinksList{{ $course->id }}');
                        if (listEl) listEl.innerHTML = '<div class="alert alert-danger">Nie udało się załadować listy ankiet.</div>';
                    });
            }

            const surveyLinkFormIdx{{ $course->id }} = document.getElementById('surveyLinkForm{{ $course->id }}');
            if (surveyLinkFormIdx{{ $course->id }}) {
                surveyLinkFormIdx{{ $course->id }}.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!surveyLinkFormIdx{{ $course->id }}.checkValidity()) {
                        surveyLinkFormIdx{{ $course->id }}.classList.add('was-validated');
                        return;
                    }
                    const fd = new FormData(surveyLinkFormIdx{{ $course->id }});
                    if (!fd.has('is_active')) fd.append('is_active', '0');
                    fetch('{{ route('courses.survey-links.store', $course->id) }}', {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            surveyLinkFormIdx{{ $course->id }}.reset();
                            const cb = document.getElementById('survey_link_is_active{{ $course->id }}');
                            if (cb) cb.checked = true;
                            surveyLinkFormIdx{{ $course->id }}.classList.remove('was-validated');
                            loadSurveyLinks{{ $course->id }}();
                            refreshSurveyLinkBtn{{ $course->id }}();
                        } else {
                            let msg = data.message || 'Walidacja nie powiodła się.';
                            if (data.errors) msg += '\n' + Object.values(data.errors).flat().join('\n');
                            alert('Błąd: ' + msg);
                        }
                    })
                    .catch(() => alert('Błąd dodawania ankiety.'));
                });
            }

            window.deleteSurveyLink{{ $course->id }} = function(linkId) {
                if (!confirm('Usunąć ten link do ankiety?')) return;
                fetch('{{ route('courses.survey-links.destroy', [$course->id, ':surveyLinkId']) }}'.replace(':surveyLinkId', linkId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadSurveyLinks{{ $course->id }}();
                        refreshSurveyLinkBtn{{ $course->id }}();
                    } else {
                        alert('Błąd: ' + data.message);
                    }
                })
                .catch(() => alert('Błąd usuwania.'));
            };

            // Obsługa modala z odtwarzaniem wideo - zatrzymywanie przy zamykaniu
            @if(isset($course->videos) && $course->videos->isNotEmpty())
            setupVideoPlayerModal{{ $course->id }}();
            @endif
            @endforeach
        });
    </script>
    @endpush
</x-app-layout>

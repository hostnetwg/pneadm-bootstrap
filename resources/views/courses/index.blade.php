<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista szkoleń') }}
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
                        <th style="width: 17%;">Tytuł</th>
                        {{-- <th>Opis</th> --}}
                        <th style="width: 10%;">Rodzaj</th>
                        <th style="width: 18%;">Lokalizacja / Dostęp</th>
                        <th style="width: 10%;">Instruktor</th>
                        <th class="text-center" style="width: 5%;" title="Uczestnicy">U</th>
                        <th class="text-center" style="width: 10%;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                    <tr class="{{ strtotime($course->end_date) < time() ? 'table-secondary text-muted' : '' }}">
                        <td class="text-center align-middle">{{ $course->id }}</td>
                        <td class="text-center align-middle small">
                            {{ $course->id_old ?? '-' }}<br>
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
                        <td class="align-middle"><strong>{{ $course->title }}</strong></td>
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
                            <span class="badge bg-info" title="Liczba uczestników">{{ $course->participants->count() }}</span><br>
                            <span class="badge bg-warning" title="Liczba wygenerowanych zaświadczeń">{{ $course->certificates->count() }}</span><br>
                            @if($course->orders_count > 0)
                                <span class="badge bg-danger" title="Liczba nie wprowadzonych zamówień">{{ $course->orders_count }}</span>
                            @else
                                <span class="badge bg-secondary" title="Liczba nie wprowadzonych zamówień">0</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            <div class="d-flex flex-column gap-1">
                                <a href="{{ route('courses.show', $course->id) }}" class="btn btn-primary btn-sm">Podgląd</a>
                                <a href="{{ route('courses.edit', $course->id) }}" class="btn btn-warning btn-sm">Edytuj</a>
                                <form action="{{ route('courses.destroy', $course->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Czy na pewno chcesz usunąć?')">Usuń</button>
                                </form>
                                <a href="{{ route('participants.index', $course->id) }}" class="btn btn-info btn-sm text-white">Uczestnicy</a>
                            </div>
                        </td>
                    </tr>@endforeach
                </tbody>
                </table>
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
</x-app-layout>

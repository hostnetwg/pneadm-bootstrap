<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista uczestników') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Przycisk zbierania e-maili -->
            <div class="mb-3">
                <form action="{{ route('participants.collect-emails') }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz zebrać bazę e-mail z uczestników? Ta operacja może zająć chwilę.');">
                    @csrf
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-envelope"></i> Zbierz bazę e-mail
                    </button>
                </form>
            </div>

            <!-- Wyszukiwarka i filtry -->
            <form method="GET" action="{{ route('participants.all') }}" class="mb-4 p-3 bg-light rounded shadow-sm">
                <div class="row g-3 align-items-end">
                    <!-- Wyszukiwarka -->
                    <div class="col-md-4">
                        <label for="search" class="form-label fw-bold">Wyszukaj</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Nazwisko, imię, email, miejsce urodzenia..."
                                   value="{{ request('search') }}"
                                   autocomplete="off">
                            @if(request('search'))
                                <a href="{{ route('participants.all', array_filter(request()->except('search'))) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Filtr: Email -->
                    <div class="col-md-2">
                        <label for="filter_email" class="form-label fw-bold">Email</label>
                        <select name="filter_email" id="filter_email" class="form-select">
                            <option value="">Wszyscy</option>
                            <option value="has" {{ request('filter_email') == 'has' ? 'selected' : '' }}>E-mail</option>
                            <option value="missing" {{ request('filter_email') == 'missing' ? 'selected' : '' }}>Brak</option>
                        </select>
                    </div>

                    <!-- Filtr: Data urodzenia -->
                    <div class="col-md-2">
                        <label for="filter_birth_date" class="form-label fw-bold">Data urodzenia</label>
                        <select name="filter_birth_date" id="filter_birth_date" class="form-select">
                            <option value="">Wszyscy</option>
                            <option value="has" {{ request('filter_birth_date') == 'has' ? 'selected' : '' }}>Podane</option>
                            <option value="missing" {{ request('filter_birth_date') == 'missing' ? 'selected' : '' }}>Brak</option>
                        </select>
                    </div>

                    <!-- Filtr: Miejsce urodzenia -->
                    <div class="col-md-2">
                        <label for="filter_birth_place" class="form-label fw-bold">Miejsce urodzenia</label>
                        <select name="filter_birth_place" id="filter_birth_place" class="form-select">
                            <option value="">Wszyscy</option>
                            <option value="has" {{ request('filter_birth_place') == 'has' ? 'selected' : '' }}>Podane</option>
                            <option value="missing" {{ request('filter_birth_place') == 'missing' ? 'selected' : '' }}>Brak</option>
                        </select>
                    </div>

                    <!-- Przyciski -->
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter"></i> Filtruj</button>
                        @if(request()->anyFilled(['search', 'filter_email', 'filter_birth_date', 'filter_birth_place']))
                            <a href="{{ route('participants.all', array_filter(request()->only(['sort_by', 'sort_direction', 'per_page']))) }}" class="btn btn-secondary"><i class="fas fa-times"></i> Resetuj</a>
                        @endif
                    </div>
                </div>
            </form>

            <!-- Informacja o liczbie rekordów -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted">
                    <strong>Wyświetlane rekordy:</strong> {{ $participants->total() }}
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label for="per_page" class="form-label mb-0 fw-bold">Wyświetl:</label>
                    <form method="GET" action="{{ route('participants.all') }}" class="d-flex align-items-center">
                        @foreach(request()->query() as $key => $value)
                            @if($key !== 'per_page')
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <select name="per_page" class="form-select" style="width: auto;" onchange="this.form.submit()">
                            <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200</option>
                            <option value="500" {{ request('per_page') == 500 ? 'selected' : '' }}>500</option>
                            <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>Wszystkie</option>
                        </select>
                    </form>
                </div>
            </div>

            @if($participants->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 5%;">
                                    <a href="{{ route('participants.all', array_merge(request()->query(), ['sort_by' => 'id', 'sort_direction' => request('sort_by') == 'id' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>ID</span>
                                        @if(request('sort_by') == 'id')
                                            @if(request('sort_direction', 'asc') == 'asc')
                                                <i class="fas fa-sort-up ms-2"></i>
                                            @else
                                                <i class="fas fa-sort-down ms-2"></i>
                                            @endif
                                        @else
                                            <i class="fas fa-sort ms-2 text-muted"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 20%;">
                                    <a href="{{ route('participants.all', array_merge(request()->query(), ['sort_by' => 'last_name', 'sort_direction' => request('sort_by') == 'last_name' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Nazwisko</span>
                                        @if(request('sort_by', 'last_name') == 'last_name')
                                            @if(request('sort_direction', 'asc') == 'asc')
                                                <i class="fas fa-sort-up ms-2"></i>
                                            @else
                                                <i class="fas fa-sort-down ms-2"></i>
                                            @endif
                                        @else
                                            <i class="fas fa-sort ms-2 text-muted"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 20%;">
                                    <a href="{{ route('participants.all', array_merge(request()->query(), ['sort_by' => 'first_name', 'sort_direction' => request('sort_by') == 'first_name' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Imię</span>
                                        @if(request('sort_by') == 'first_name')
                                            @if(request('sort_direction', 'asc') == 'asc')
                                                <i class="fas fa-sort-up ms-2"></i>
                                            @else
                                                <i class="fas fa-sort-down ms-2"></i>
                                            @endif
                                        @else
                                            <i class="fas fa-sort ms-2 text-muted"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 25%;">
                                    <a href="{{ route('participants.all', array_merge(request()->query(), ['sort_by' => 'email', 'sort_direction' => request('sort_by') == 'email' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Email</span>
                                        @if(request('sort_by') == 'email')
                                            @if(request('sort_direction', 'asc') == 'asc')
                                                <i class="fas fa-sort-up ms-2"></i>
                                            @else
                                                <i class="fas fa-sort-down ms-2"></i>
                                            @endif
                                        @else
                                            <i class="fas fa-sort ms-2 text-muted"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 15%;">
                                    <a href="{{ route('participants.all', array_merge(request()->query(), ['sort_by' => 'birth_date', 'sort_direction' => request('sort_by') == 'birth_date' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Data urodzenia</span>
                                        @if(request('sort_by') == 'birth_date')
                                            @if(request('sort_direction', 'asc') == 'asc')
                                                <i class="fas fa-sort-up ms-2"></i>
                                            @else
                                                <i class="fas fa-sort-down ms-2"></i>
                                            @endif
                                        @else
                                            <i class="fas fa-sort ms-2 text-muted"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 15%;">
                                    <a href="{{ route('participants.all', array_merge(request()->query(), ['sort_by' => 'birth_place', 'sort_direction' => request('sort_by') == 'birth_place' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Miejsce urodzenia</span>
                                        @if(request('sort_by') == 'birth_place')
                                            @if(request('sort_direction', 'asc') == 'asc')
                                                <i class="fas fa-sort-up ms-2"></i>
                                            @else
                                                <i class="fas fa-sort-down ms-2"></i>
                                            @endif
                                        @else
                                            <i class="fas fa-sort ms-2 text-muted"></i>
                                        @endif
                                    </a>
                                </th>
                                <th style="width: 25%;">Szkolenie</th>
                                <th style="width: 8%;" class="text-center">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($participants as $participant)
                            <tr>
                                <td>{{ $participant->id }}</td>
                                <td><strong>{{ $participant->last_name }}</strong></td>
                                <td>{{ $participant->first_name }}</td>
                                <td>{{ $participant->email ?: '-' }}</td>
                                <td>{{ $participant->birth_date ? $participant->birth_date->format('Y-m-d') : '-' }}</td>
                                <td>{{ $participant->birth_place ?: '-' }}</td>
                                <td>
                                    @if($participant->course)
                                        <a href="{{ route('courses.show', $participant->course->id) }}" class="text-decoration-none" target="_blank">
                                            {{ str_replace('&nbsp;', ' ', $participant->course->title) }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal{{ $participant->id }}"
                                                title="Podgląd uczestnika">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        @if($participant->course)
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal{{ $participant->id }}"
                                                title="Usuń uczestnika">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Paginacja -->
                <div class="mt-3">
                    {{ $participants->links() }}
                </div>

                @foreach ($participants as $participant)
    @if($participant->course)
    <div class="modal fade" id="deleteModal{{ $participant->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $participant->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel{{ $participant->id }}">
                        <i class="fas fa-exclamation-triangle"></i> Potwierdzenie usunięcia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Czy na pewno chcesz usunąć uczestnika <strong>#{{ $participant->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły uczestnika:</h6>
                        <ul class="mb-0">
                            <li><strong>Imię i nazwisko:</strong> {{ $participant->first_name }} {{ $participant->last_name }}</li>
                            <li><strong>Email:</strong> {{ $participant->email ?? 'Brak' }}</li>
                            <li><strong>Szkolenie:</strong> {!! $participant->course->title !!}</li>
                        </ul>
                    </div>
                    <p class="text-muted mt-3 small">
                        <i class="fas fa-info-circle"></i>
                        Uczestnik zostanie przeniesiony do kosza (soft delete) i będzie można go przywrócić w sekcji Kosz.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Anuluj
                    </button>
                    <form action="{{ route('participants.destroy', [$participant->course->id, $participant->id]) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Usuń uczestnika
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endforeach

    <!-- Modale podglądu uczestników -->
                @foreach ($participants as $participant)
                <div class="modal fade" id="viewModal{{ $participant->id }}" tabindex="-1" aria-labelledby="viewModalLabel{{ $participant->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="viewModalLabel{{ $participant->id }}">
                                    <i class="fas fa-user"></i> Podgląd uczestnika #{{ $participant->id }}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>ID:</strong>
                                        <p class="mb-0">{{ $participant->id }}</p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Nazwisko:</strong>
                                        <p class="mb-0"><strong>{{ $participant->last_name }}</strong></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Imię:</strong>
                                        <p class="mb-0">{{ $participant->first_name }}</p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Email:</strong>
                                        <p class="mb-0">{{ $participant->email ?: '-' }}</p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Data urodzenia:</strong>
                                        <p class="mb-0">{{ $participant->birth_date ? $participant->birth_date->format('Y-m-d') : '-' }}</p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Miejsce urodzenia:</strong>
                                        <p class="mb-0">{{ $participant->birth_place ?: '-' }}</p>
                                    </div>
                                    @if($participant->course)
                                    <div class="col-md-12 mb-3">
                                        <strong>Szkolenie:</strong>
                                        <p class="mb-0">
                                            <a href="{{ route('courses.show', $participant->course->id) }}" target="_blank">
                                                {!! $participant->course->title !!}
                                            </a>
                                        </p>
                                    </div>
                                    @endif
                                    @if($participant->access_expires_at)
                                    <div class="col-md-6 mb-3">
                                        <strong>Data wygaśnięcia dostępu:</strong>
                                        <p class="mb-0">{{ $participant->access_expires_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                    @endif
                                    @if($participant->certificate)
                                    <div class="col-md-6 mb-3">
                                        <strong>Zaświadczenie:</strong>
                                        <p class="mb-0">
                                            <span class="badge bg-success">Wygenerowane</span>
                                            @if($participant->certificate->certificate_number)
                                                <br><small>Nr: {{ $participant->certificate->certificate_number }}</small>
                                            @endif
                                        </p>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="modal-footer">
                                @if($participant->course)
                                <a href="{{ route('participants.index', $participant->course->id) }}" class="btn btn-primary">
                                    <i class="fas fa-list"></i> Lista uczestników kursu
                                </a>
                                <a href="{{ route('courses.show', $participant->course->id) }}" class="btn btn-outline-primary">
                                    <i class="fas fa-graduation-cap"></i> Szczegóły kursu
                                </a>
                                @endif
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            @else
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    @if(request('search'))
                        <h4 class="text-muted">Brak wyników wyszukiwania</h4>
                        <p class="text-muted">Nie znaleziono uczestników pasujących do frazy: <strong>"{{ request('search') }}"</strong></p>
                        <a href="{{ route('participants.all', array_filter(request()->except('search'))) }}" class="btn btn-primary">
                            <i class="fas fa-list me-1"></i> Pokaż wszystkich uczestników
                        </a>
                    @else
                        <h4 class="text-muted">Brak uczestników</h4>
                        <p class="text-muted">Nie ma uczestników w systemie.</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


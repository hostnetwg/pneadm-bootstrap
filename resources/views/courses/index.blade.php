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
                    {{ session('success') }}
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('courses.create') }}" class="btn btn-primary">Dodaj szkolenie</a>
                <form action="{{ route('courses.import') }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2" id="csvImportForm">
                    @csrf
                    <input type="file" name="csv_file" class="form-control d-none" accept=".csv" id="csvFileInput">
                    <button type="button" class="btn btn-secondary" id="importCsvButton">Importuj listę kursów CSV</button>
                </form>
            </div>

            <form method="GET" action="{{ route('courses.index') }}" class="mb-4 p-3 bg-light rounded shadow-sm">
                <div class="row g-3 align-items-end">
            
                    <!-- Filtr: Termin -->
                    <div class="col-md-2">
                        <label for="date_filter" class="form-label fw-bold">Termin</label>
                        <select name="date_filter" class="form-select">
                            <option value="all" {{ request()->get('date_filter', 'upcoming') === 'all' ? 'selected' : '' }}>Wszystkie</option>                            
                            <option value="upcoming" {{ request()->get('date_filter', 'upcoming') === 'upcoming' ? 'selected' : '' }}>Nadchodzące</option>
                            <option value="past" {{ request()->get('date_filter') === 'past' ? 'selected' : '' }}>Archiwalne</option>
                        </select>                                                                                         
                    </div>
            
                    <!-- Filtr: Płatność -->
                    <div class="col-md-2">
                        <label for="is_paid" class="form-label fw-bold">Płatność</label>
                        <select name="is_paid" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="1" {{ request('is_paid') == '1' ? 'selected' : '' }}>Płatne</option>
                            <option value="0" {{ request('is_paid') == '0' ? 'selected' : '' }}>Bezpłatne</option>
                        </select>
                    </div>
            
                    <!-- Filtr: Rodzaj kursu -->
                    <div class="col-md-2">
                        <label for="type" class="form-label fw-bold">Rodzaj</label>
                        <select name="type" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="online" {{ request('type') == 'online' ? 'selected' : '' }}>Online</option>
                            <option value="offline" {{ request('type') == 'offline' ? 'selected' : '' }}>Offline</option>
                        </select>
                    </div>
            
                    <!-- Filtr: Kategoria -->
                    <div class="col-md-2">
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
                    <!-- Filtr: Instruktor -->
                    <div class="col-md-3">
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
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter"></i> Filtruj</button>
                        <a href="{{ route('courses.index') }}" class="btn btn-secondary flex-grow-1"><i class="fas fa-sync-alt"></i> Resetuj</a>
                    </div>
            
                </div>
            </form>
            
                        
            <table class="table table-striped table-hover table-responsive">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center" style="width: 5%;">#id</th>
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
                        <th style="width: 8%;">Status</th>
                        <th class="text-center" style="width: 5%;" title="Uczestnicy">U</th>
                        <th class="text-center" style="width: 10%;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                    <tr class="{{ strtotime($course->end_date) < time() ? 'table-secondary text-muted' : '' }}">
                        <td class="text-center align-middle">{{ $course->id }}</td>
                        <td class="align-middle">{{ $course->start_date ? date('d.m.Y H:i', strtotime($course->start_date)) : 'Brak daty' }}</td>                        
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
                                {{ $course->is_paid ? 'Płatny' : 'Bezpłatny' }}
                            </span> <br>
                            <span class="small">{{ ucfirst($course->type) }}</span> <br>
                            <span class="small">{{ $course->category === 'open' ? 'Otwarte' : 'Zamknięte' }}</span>
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
                            {{ $course->instructor ? $course->instructor->first_name . ' ' . $course->instructor->last_name : 'Brak instruktora' }}
                        </td>
                        <td class="align-middle">
                            <span class="badge {{ $course->is_active ? 'bg-success' : 'bg-danger' }}">
                                {{ $course->is_active ? 'Aktywny' : 'Nieaktywny' }}
                            </span>
                        </td>
                        <td class="text-center align-middle" title="Liczba uczestników">
                            <span class="badge bg-info">{{ $course->participants->count() }}</span>
                        </td>
                        <td class="align-middle">
                            <div class="d-flex flex-column gap-1">
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


            <div class="mt-3">
                {{-- {{ $courses->links() }} --}}
                {{ $courses->appends(request()->query())->links() }}

            </div>

        </div>
    </div>
    <script>
        document.getElementById('importCsvButton').addEventListener('click', function() {
            document.getElementById('csvFileInput').click();
        });

        document.getElementById('csvFileInput').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('csvImportForm').submit();
            }
        });
    </script>    
</x-app-layout>

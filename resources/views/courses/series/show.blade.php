<!-- resources/views/courses/series/show.blade.php -->

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szczegóły serii') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Nawigacja -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="{{ route('courses.series.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Lista serii
                        </a>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('courses.series.edit', $series) }}" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edytuj serię
                        </a>
                        <form action="{{ route('courses.series.destroy', $series) }}" method="POST" 
                              onsubmit="return confirm('Czy na pewno chcesz usunąć tę serię?')" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Usuń serię
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Informacje o serii -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle"></i> Informacje o serii
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td style="width: 150px;"><strong>Nazwa:</strong></td>
                                    <td>{{ $series->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Slug (URL):</strong></td>
                                    <td><code>{{ $series->slug }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        @if($series->is_active)
                                            <span class="badge bg-success">Aktywna</span>
                                        @else
                                            <span class="badge bg-danger">Nieaktywna</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Kolejność:</strong></td>
                                    <td>{{ $series->sort_order }}</td>
                                </tr>
                                @if($series->description)
                                <tr>
                                    <td><strong>Opis:</strong></td>
                                    <td>{{ $series->description }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-4 text-center">
                            @if($series->image)
                                <img src="{{ asset('storage/' . $series->image) }}" alt="{{ $series->name }}" class="img-fluid rounded shadow-sm" style="max-height: 200px;">
                            @else
                                <div class="d-flex align-items-center justify-content-center bg-light text-muted rounded" style="height: 150px;">
                                    <div class="text-center">
                                        <i class="fas fa-images fa-3x"></i>
                                        <p class="mb-0 mt-2 small">Brak obrazka</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zarządzanie kursami -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Zarządzanie kursami w serii
                        <span class="badge bg-secondary">{{ $courses->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Lewa kolumna: Lista kursów do edycji -->
                        <div class="col-lg-6">
                            <h6 class="mb-3">Lista kursów w serii (kolejność)</h6>
                            <form action="{{ route('courses.series.update-courses', $series) }}" method="POST" id="updateCoursesForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="alert alert-light border mb-3 small">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Przeciągnij elementy, aby zmienić kolejność. Zapisz zmiany przyciskiem na dole.
                                </div>

                                <div class="list-group mb-3" id="courses_list" style="max-height: 600px; overflow-y: auto;">
                                    @forelse($courses as $course)
                                        <div class="list-group-item d-flex justify-content-between align-items-center" data-id="{{ $course->id }}">
                                            <div class="d-flex align-items-center overflow-hidden">
                                                <span class="badge bg-primary me-2 flex-shrink-0" style="min-width: 30px; text-align: center;">
                                                    {{ $course->pivot->order_in_series ?? $loop->iteration }}
                                                </span>
                                                <i class="fas fa-grip-vertical text-muted me-2 cursor-move flex-shrink-0"></i>
                                                <div class="text-truncate">
                                                    <div class="fw-bold text-truncate">{{ $course->title }}</div>
                                                    <div class="small text-muted">
                                                        {{ $course->start_date ? $course->start_date->format('Y-m-d') : '-' }}
                                                        @if($course->instructor)
                                                         | {{ $course->instructor->first_name }} {{ $course->instructor->last_name }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-course-btn ms-2 flex-shrink-0" title="Usuń z serii">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <input type="hidden" name="courses[]" value="{{ $course->id }}">
                                        </div>
                                            @empty
                                                <div class="text-center py-4 text-muted bg-light rounded" id="empty-message">
                                                    Brak kursów w tej serii. Dodaj kursy z listy dostępnych szkoleń.
                                                </div>
                                            @endforelse
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-check"></i> Zapisz kolejność i listę
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Prawa kolumna: Tabela dostępnych kursów -->
                        <div class="col-lg-6 border-start ps-lg-4 mt-4 mt-lg-0">
                            <h6 class="mb-3">Dostępne szkolenia</h6>
                            
                            <!-- Filtry -->
                            <div class="mb-3 p-3 bg-light rounded">
                                <div class="row g-2">
                                    <!-- Wyszukiwarka -->
                                    <div class="col-12">
                                        <label for="courseSearch" class="form-label small fw-bold mb-1">Wyszukaj</label>
                                        <input type="text" id="courseSearch" class="form-control form-control-sm" placeholder="Nazwa, instruktor...">
                                    </div>
                                    
                                    <!-- Data od -->
                                    <div class="col-6">
                                        <label for="filterDateFrom" class="form-label small fw-bold mb-1">Data od</label>
                                        <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                                    </div>
                                    
                                    <!-- Data do -->
                                    <div class="col-6">
                                        <label for="filterDateTo" class="form-label small fw-bold mb-1">Data do</label>
                                        <input type="date" id="filterDateTo" class="form-control form-control-sm">
                                    </div>
                                    
                                    <!-- Płatność -->
                                    <div class="col-6">
                                        <label for="filterIsPaid" class="form-label small fw-bold mb-1">Płatność</label>
                                        <select id="filterIsPaid" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <option value="1">Płatne</option>
                                            <option value="0">Bezpłatne</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Rodzaj -->
                                    <div class="col-6">
                                        <label for="filterType" class="form-label small fw-bold mb-1">Rodzaj</label>
                                        <select id="filterType" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <option value="online">Online</option>
                                            <option value="offline">Stacjonarne</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Kategoria -->
                                    <div class="col-6">
                                        <label for="filterCategory" class="form-label small fw-bold mb-1">Kategoria</label>
                                        <select id="filterCategory" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            <option value="open">Otwarte</option>
                                            <option value="closed">Zamknięte</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Źródło -->
                                    <div class="col-6">
                                        <label for="filterSourceIdOld" class="form-label small fw-bold mb-1">Źródło</label>
                                        <select id="filterSourceIdOld" class="form-select form-select-sm">
                                            <option value="">Wszystkie</option>
                                            @foreach($sourceIdOldOptions as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <!-- Przycisk resetu -->
                                    <div class="col-12">
                                        <button type="button" id="resetFilters" class="btn btn-sm btn-outline-secondary w-100">
                                            <i class="fas fa-times"></i> Resetuj filtry
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive border rounded" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0" id="availableCoursesTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 30px"></th>
                                            <th class="cursor-pointer sortable" data-sort="id">ID <i class="fas fa-sort small text-muted"></i></th>
                                            <th class="cursor-pointer sortable" data-sort="date">Data <i class="fas fa-sort small text-muted"></i></th>
                                            <th class="cursor-pointer sortable" data-sort="title">Nazwa <i class="fas fa-sort small text-muted"></i></th>
                                            <th class="cursor-pointer sortable" data-sort="instructor">Instruktor <i class="fas fa-sort small text-muted"></i></th>
                                            <th style="width: 50px">Akcja</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($allCourses as $course)
                                            <tr class="course-row" 
                                                data-id="{{ $course->id }}"
                                                data-date="{{ $course->start_date ? $course->start_date->format('Y-m-d') : '' }}"
                                                data-date-timestamp="{{ $course->start_date ? $course->start_date->timestamp : 0 }}"
                                                data-is-paid="{{ $course->is_paid ? '1' : '0' }}"
                                                data-type="{{ $course->type }}"
                                                data-category="{{ $course->category }}"
                                                data-source-id-old="{{ $course->source_id_old ?? '' }}">
                                                <td>
                                                    @if($course->is_active)
                                                        <i class="fas fa-circle text-success small" title="Aktywny"></i>
                                                    @else
                                                        <i class="fas fa-circle text-secondary small" title="Nieaktywny"></i>
                                                    @endif
                                                </td>
                                                <td>{{ $course->id }}</td>
                                                <td data-date="{{ $course->start_date ? $course->start_date->timestamp : 0 }}">
                                                    {{ $course->start_date ? $course->start_date->format('Y-m-d') : '-' }}
                                                </td>
                                                <td>
                                                    <span class="course-title fw-bold">{{ strip_tags(html_entity_decode($course->title, ENT_QUOTES, 'UTF-8')) }}</span>
                                                </td>
                                                <td>
                                                    <span class="course-instructor">
                                                        {{ $course->instructor ? $course->instructor->first_name . ' ' . $course->instructor->last_name : '-' }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-success add-course-btn" 
                                                            data-id="{{ $course->id }}"
                                                            data-title="{{ $course->title }}"
                                                            data-date="{{ $course->start_date ? $course->start_date->format('Y-m-d') : '-' }}"
                                                            data-instructor="{{ $course->instructor ? $course->instructor->first_name . ' ' . $course->instructor->last_name : '' }}">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted mt-2 text-end">
                                Wyświetlono: <span id="visibleCount">{{ $allCourses->count() }}</span> z {{ $allCourses->count() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const coursesList = document.getElementById('courses_list');
            const emptyMessage = document.getElementById('empty-message');
            const courseSearch = document.getElementById('courseSearch');
            const filterDateFrom = document.getElementById('filterDateFrom');
            const filterDateTo = document.getElementById('filterDateTo');
            const filterIsPaid = document.getElementById('filterIsPaid');
            const filterType = document.getElementById('filterType');
            const filterCategory = document.getElementById('filterCategory');
            const filterSourceIdOld = document.getElementById('filterSourceIdOld');
            const resetFiltersBtn = document.getElementById('resetFilters');
            const tableRows = document.querySelectorAll('.course-row');
            const visibleCountSpan = document.getElementById('visibleCount');
            const table = document.getElementById('availableCoursesTable');

            // Funkcja do aktualizacji numerów kolejności
            function updateOrderNumbers() {
                const items = coursesList.querySelectorAll('.list-group-item:not(#empty-message)');
                items.forEach((item, index) => {
                    const badge = item.querySelector('.badge.bg-primary');
                    if (badge) {
                        badge.textContent = index + 1;
                    }
                });
            }

            // Inicjalizacja SortableJS
            const sortable = new Sortable(coursesList, {
                animation: 150,
                ghostClass: 'bg-light',
                handle: '.list-group-item',
                onEnd: function() {
                    // Aktualizuj numery po zakończeniu przeciągania
                    updateOrderNumbers();
                }
            });

            // Funkcja do pobrania ID kursów już w liście po lewej
            function getAssignedCourseIds() {
                return Array.from(coursesList.querySelectorAll('input[value]')).map(input => input.value);
            }

            // Funkcja filtrowania tabeli
            function filterTable() {
                const searchTerm = courseSearch.value.toLowerCase();
                const dateFrom = filterDateFrom.value;
                const dateTo = filterDateTo.value;
                const isPaid = filterIsPaid.value;
                const type = filterType.value;
                const category = filterCategory.value;
                const sourceIdOld = filterSourceIdOld.value;
                
                // Pobierz ID kursów już przypisanych (zarówno zapisanych jak i dodanych dynamicznie)
                const assignedIds = getAssignedCourseIds();
                
                let visible = 0;

                tableRows.forEach(row => {
                    const courseId = row.dataset.id;
                    
                    // Najpierw sprawdź czy kurs nie jest już w liście po lewej - jeśli tak, zawsze ukryj
                    if (assignedIds.includes(courseId)) {
                        row.style.display = 'none';
                        return;
                    }
                    
                    let show = true;

                    // Filtrowanie po wyszukiwarce
                    if (searchTerm) {
                        const text = row.innerText.toLowerCase();
                        if (!text.includes(searchTerm)) {
                            show = false;
                        }
                    }

                    // Filtrowanie po dacie od
                    if (show && dateFrom) {
                        const rowDate = row.dataset.date;
                        if (!rowDate || rowDate < dateFrom) {
                            show = false;
                        }
                    }

                    // Filtrowanie po dacie do
                    if (show && dateTo) {
                        const rowDate = row.dataset.date;
                        if (!rowDate || rowDate > dateTo) {
                            show = false;
                        }
                    }

                    // Filtrowanie po płatności
                    if (show && isPaid !== '') {
                        if (row.dataset.isPaid !== isPaid) {
                            show = false;
                        }
                    }

                    // Filtrowanie po rodzaju
                    if (show && type !== '') {
                        if (row.dataset.type !== type) {
                            show = false;
                        }
                    }

                    // Filtrowanie po kategorii
                    if (show && category !== '') {
                        if (row.dataset.category !== category) {
                            show = false;
                        }
                    }

                    // Filtrowanie po źródle (source_id_old)
                    if (show && sourceIdOld !== '') {
                        const rowSourceIdOld = row.dataset.sourceIdOld || '';
                        if (rowSourceIdOld !== sourceIdOld) {
                            show = false;
                        }
                    }

                    if (show) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                visibleCountSpan.textContent = visible;
            }

            // Event listeners dla filtrów
            courseSearch.addEventListener('keyup', filterTable);
            filterDateFrom.addEventListener('change', filterTable);
            filterDateTo.addEventListener('change', filterTable);
            filterIsPaid.addEventListener('change', filterTable);
            filterType.addEventListener('change', filterTable);
            filterCategory.addEventListener('change', filterTable);
            filterSourceIdOld.addEventListener('change', filterTable);

            // Reset filtrów
            resetFiltersBtn.addEventListener('click', function() {
                courseSearch.value = '';
                filterDateFrom.value = '';
                filterDateTo.value = '';
                filterIsPaid.value = '';
                filterType.value = '';
                filterCategory.value = '';
                filterSourceIdOld.value = '';
                filterTable();
            });

            // Sortowanie tabeli
            document.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    const type = th.dataset.sort;
                    const rows = Array.from(table.querySelectorAll('tbody tr'));
                    const isAsc = th.classList.contains('asc');
                    
                    // Reset ikon
                    document.querySelectorAll('th.sortable i').forEach(i => i.className = 'fas fa-sort small text-muted');
                    th.querySelector('i').className = isAsc ? 'fas fa-sort-down' : 'fas fa-sort-up';
                    th.classList.toggle('asc');

                    rows.sort((a, b) => {
                        let valA, valB;

                        if (type === 'id') {
                            valA = parseInt(a.cells[1].innerText);
                            valB = parseInt(b.cells[1].innerText);
                        } else if (type === 'date') {
                            valA = parseInt(a.cells[2].dataset.date);
                            valB = parseInt(b.cells[2].dataset.date);
                        } else if (type === 'title') {
                            valA = a.cells[3].innerText.toLowerCase();
                            valB = b.cells[3].innerText.toLowerCase();
                        } else if (type === 'instructor') {
                            valA = a.cells[4].innerText.toLowerCase();
                            valB = b.cells[4].innerText.toLowerCase();
                        }

                        if (valA < valB) return isAsc ? -1 : 1;
                        if (valA > valB) return isAsc ? 1 : -1;
                        return 0;
                    });

                    const tbody = table.querySelector('tbody');
                    tbody.innerHTML = '';
                    rows.forEach(row => tbody.appendChild(row));
                });
            });

            // Dodawanie kursu z tabeli
            document.querySelectorAll('.add-course-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const title = this.dataset.title;
                    const date = this.dataset.date;
                    const instructor = this.dataset.instructor;

                    // Sprawdź czy już dodano
                    if (coursesList.querySelector(`input[value="${id}"]`)) {
                        alert('Ten kurs jest już na liście.');
                        return;
                    }

                    if (emptyMessage) emptyMessage.style.display = 'none';

                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center bg-warning-subtle';
                    item.dataset.id = id;
                    
                    let instructorHtml = instructor ? ` | ${instructor}` : '';
                    const currentCount = coursesList.children.length + 1;

                    item.innerHTML = `
                        <div class="d-flex align-items-center overflow-hidden">
                            <span class="badge bg-primary me-2 flex-shrink-0" style="min-width: 30px; text-align: center;">
                                ${currentCount}
                            </span>
                            <i class="fas fa-grip-vertical text-muted me-2 cursor-move flex-shrink-0"></i>
                            <div class="text-truncate">
                                <div class="fw-bold text-truncate">${title}</div>
                                <div class="small text-success mt-1">
                                    ${date}${instructorHtml} (Nowy)
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-course-btn ms-2 flex-shrink-0">
                            <i class="fas fa-times"></i>
                        </button>
                        <input type="hidden" name="courses[]" value="${id}">
                    `;

                    coursesList.appendChild(item);
                    
                    // Aktualizuj numery kolejności
                    updateOrderNumbers();
                    
                    // Natychmiast ukryj kurs w tabeli i zaktualizuj licznik
                    filterTable();
                    
                    // Scroll to bottom of list
                    coursesList.scrollTop = coursesList.scrollHeight;
                });
            });

            // Usuwanie kursu
            coursesList.addEventListener('click', function(e) {
                if (e.target.closest('.remove-course-btn')) {
                    const item = e.target.closest('.list-group-item');
                    item.remove();
                    
                    // Aktualizuj numery kolejności
                    updateOrderNumbers();
                    
                    // Zaktualizuj widoczność tabeli (kurs pojawi się z powrotem jeśli spełnia filtry)
                    filterTable();
                    
                    if (coursesList.children.length === 0 && emptyMessage) {
                        emptyMessage.style.display = 'block';
                    }
                }
            });
        });
    </script>
    @endpush
    
    <style>
        .cursor-move { cursor: grab; }
        .cursor-move:active { cursor: grabbing; }
        .cursor-pointer { cursor: pointer; }
    </style>
</x-app-layout>

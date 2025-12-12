<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista e-mailowa') }}
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

            <!-- Wyszukiwarka i filtry -->
            <form method="GET" action="{{ route('participants.emails-list') }}" class="mb-4 p-3 bg-light rounded shadow-sm">
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
                                   placeholder="Adres e-mail..."
                                   value="{{ request('search') }}"
                                   autocomplete="off">
                            @if(request('search'))
                                <a href="{{ route('participants.emails-list', array_filter(request()->except('search'))) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Filtr: Status aktywności -->
                    <div class="col-md-2">
                        <label for="filter_active" class="form-label fw-bold">Status</label>
                        <select name="filter_active" id="filter_active" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="active" {{ request('filter_active') == 'active' ? 'selected' : '' }}>Aktywne</option>
                            <option value="inactive" {{ request('filter_active') == 'inactive' ? 'selected' : '' }}>Nieaktywne</option>
                        </select>
                    </div>

                    <!-- Filtr: Weryfikacja -->
                    <div class="col-md-2">
                        <label for="filter_verified" class="form-label fw-bold">Weryfikacja</label>
                        <select name="filter_verified" id="filter_verified" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="verified" {{ request('filter_verified') == 'verified' ? 'selected' : '' }}>Zweryfikowane</option>
                            <option value="unverified" {{ request('filter_verified') == 'unverified' ? 'selected' : '' }}>Niezweryfikowane</option>
                        </select>
                    </div>

                    <!-- Filtr: Nieprawidłowe e-maile -->
                    <div class="col-md-2">
                        <label for="filter_invalid_email" class="form-label fw-bold">Walidacja e-mail</label>
                        <select name="filter_invalid_email" id="filter_invalid_email" class="form-select">
                            <option value="">Wszystkie</option>
                            <option value="valid" {{ request('filter_invalid_email') == 'valid' ? 'selected' : '' }}>Prawidłowe</option>
                            <option value="invalid" {{ request('filter_invalid_email') == 'invalid' ? 'selected' : '' }}>Nieprawidłowe</option>
                        </select>
                    </div>

                    <!-- Przyciski -->
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter"></i> Filtruj</button>
                        @if(request()->anyFilled(['search', 'filter_active', 'filter_verified', 'filter_invalid_email']))
                            <a href="{{ route('participants.emails-list', array_filter(request()->only(['sort_by', 'sort_direction', 'per_page']))) }}" class="btn btn-secondary"><i class="fas fa-times"></i> Resetuj</a>
                        @endif
                    </div>
                </div>
            </form>

            <!-- Informacja o liczbie rekordów -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted">
                    <strong>Wyświetlane rekordy:</strong> {{ $emails->total() }}
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label for="per_page" class="form-label mb-0 fw-bold">Wyświetl:</label>
                    <form method="GET" action="{{ route('participants.emails-list') }}" class="d-flex align-items-center">
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

            @if($emails->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 5%;">
                                    <a href="{{ route('participants.emails-list', array_merge(request()->query(), ['sort_by' => 'id', 'sort_direction' => request('sort_by') == 'id' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
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
                                <th style="width: 30%;">
                                    <a href="{{ route('participants.emails-list', array_merge(request()->query(), ['sort_by' => 'email', 'sort_direction' => request('sort_by') == 'email' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>E-mail</span>
                                        @if(request('sort_by', 'email') == 'email')
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
                                <th style="width: 10%;">
                                    <a href="{{ route('participants.emails-list', array_merge(request()->query(), ['sort_by' => 'courses_count', 'sort_direction' => request('sort_by') == 'courses_count' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Szkoleń</span>
                                        @if(request('sort_by') == 'courses_count')
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
                                <th style="width: 15%;">Pierwszy uczestnik</th>
                                <th style="width: 10%;" class="text-center">Status</th>
                                <th style="width: 10%;" class="text-center">Weryfikacja</th>
                                <th style="width: 10%;">
                                    <a href="{{ route('participants.emails-list', array_merge(request()->query(), ['sort_by' => 'created_at', 'sort_direction' => request('sort_by') == 'created_at' && request('sort_direction', 'asc') == 'asc' ? 'desc' : 'asc'])) }}" 
                                       class="text-light text-decoration-none d-flex align-items-center justify-content-between">
                                        <span>Data dodania</span>
                                        @if(request('sort_by') == 'created_at')
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
                                <th style="width: 10%;" class="text-center">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($emails as $email)
                            @php
                                // Usuń BOM (Byte Order Mark) UTF-8 przed walidacją
                                $cleanEmail = ltrim($email->email, "\xEF\xBB\xBF");
                                $cleanEmail = trim($cleanEmail);
                                $isValidEmail = filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) !== false;
                            @endphp
                            <tr>
                                <td>{{ $email->id }}</td>
                                <td>
                                    <strong>{{ $email->email }}</strong>
                                    @if(!$isValidEmail)
                                        <span class="badge bg-danger ms-2" title="Nieprawidłowy format e-maila">
                                            <i class="fas fa-exclamation-triangle"></i> Nieprawidłowy
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary">{{ $email->courses_count ?? 0 }}</span>
                                </td>
                                <td>
                                    @if($email->firstParticipant)
                                        <a href="{{ route('participants.all', ['search' => $email->email]) }}" class="text-decoration-none">
                                            {{ $email->firstParticipant->first_name }} {{ $email->firstParticipant->last_name }}
                                        </a>
                                        <br><small class="text-muted">ID: {{ $email->firstParticipant->id }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($email->is_active)
                                        <span class="badge bg-success">Aktywny</span>
                                    @else
                                        <span class="badge bg-danger">Nieaktywny</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($email->is_verified)
                                        <span class="badge bg-success">Zweryfikowany</span>
                                    @else
                                        <span class="badge bg-secondary">Niezweryfikowany</span>
                                    @endif
                                </td>
                                <td>{{ $email->created_at ? $email->created_at->format('Y-m-d') : '-' }}</td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewEmailModal{{ $email->id }}"
                                                title="Podgląd e-maila">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editEmailModal{{ $email->id }}"
                                                title="Edytuj e-mail">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteEmailModal{{ $email->id }}"
                                                title="Usuń e-mail">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Paginacja -->
                <div class="mt-3">
                    {{ $emails->links() }}
                </div>

                <!-- Modale podglądu e-maili -->
                @foreach ($emails as $email)
                @php
                    // Usuń BOM (Byte Order Mark) UTF-8 przed walidacją
                    $cleanEmailModal = ltrim($email->email, "\xEF\xBB\xBF");
                    $cleanEmailModal = trim($cleanEmailModal);
                    $isValidEmailModal = filter_var($cleanEmailModal, FILTER_VALIDATE_EMAIL) !== false;
                @endphp
                <div class="modal fade" id="viewEmailModal{{ $email->id }}" tabindex="-1" aria-labelledby="viewEmailModalLabel{{ $email->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="viewEmailModalLabel{{ $email->id }}">
                                    <i class="fas fa-envelope"></i> Podgląd e-maila #{{ $email->id }}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @if(!$isValidEmailModal)
                                <div class="alert alert-warning mb-3">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Uwaga:</strong> Ten adres e-mail ma nieprawidłowy format.
                                </div>
                                @endif
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>ID:</strong>
                                        <p class="mb-0">{{ $email->id }}</p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>E-mail:</strong>
                                        <p class="mb-0">
                                            <strong>{{ $email->email }}</strong>
                                            @if(!$isValidEmailModal)
                                                <span class="badge bg-danger ms-2">
                                                    <i class="fas fa-exclamation-triangle"></i> Nieprawidłowy format
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Liczba szkoleń:</strong>
                                        <p class="mb-0">
                                            <span class="badge bg-primary fs-6">{{ $email->courses_count ?? 0 }}</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Status:</strong>
                                        <p class="mb-0">
                                            @if($email->is_active)
                                                <span class="badge bg-success">Aktywny</span>
                                            @else
                                                <span class="badge bg-danger">Nieaktywny</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Weryfikacja:</strong>
                                        <p class="mb-0">
                                            @if($email->is_verified)
                                                <span class="badge bg-success">Zweryfikowany</span>
                                            @else
                                                <span class="badge bg-secondary">Niezweryfikowany</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Data dodania:</strong>
                                        <p class="mb-0">{{ $email->created_at ? $email->created_at->format('Y-m-d H:i') : '-' }}</p>
                                    </div>
                                    @if($email->firstParticipant)
                                    <div class="col-md-12 mb-3">
                                        <strong>Pierwszy uczestnik:</strong>
                                        <p class="mb-0">
                                            <a href="{{ route('participants.all', ['search' => $email->email]) }}" target="_blank">
                                                {{ $email->firstParticipant->first_name }} {{ $email->firstParticipant->last_name }} (ID: {{ $email->firstParticipant->id }})
                                            </a>
                                        </p>
                                    </div>
                                    @endif
                                    @if($email->notes)
                                    <div class="col-md-12 mb-3">
                                        <strong>Notatki:</strong>
                                        <p class="mb-0">{{ $email->notes }}</p>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="{{ route('participants.all', ['search' => $email->email]) }}" class="btn btn-primary">
                                    <i class="fas fa-users"></i> Pokaż uczestników z tym e-mailem
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal edycji e-maila -->
                <div class="modal fade" id="editEmailModal{{ $email->id }}" tabindex="-1" aria-labelledby="editEmailModalLabel{{ $email->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('participants.emails.update', $email->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                @foreach(request()->query() as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endforeach
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title" id="editEmailModalLabel{{ $email->id }}">
                                        <i class="fas fa-edit"></i> Edytuj e-mail #{{ $email->id }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Uwaga:</strong> Zmiana tego adresu e-mail zaktualizuje go również w:
                                        <ul class="mb-0 mt-2">
                                            <li>Tabeli <code>participants</code></li>
                                            <li>Tabeli <code>form_orders</code> (pola <code>participant_email</code> i <code>orderer_email</code>)</li>
                                            <li>Tabeli <code>form_order_participants</code></li>
                                        </ul>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email_{{ $email->id }}" class="form-label fw-bold">Aktualny adres e-mail:</label>
                                        <input type="text" 
                                               class="form-control" 
                                               value="{{ $email->email }}" 
                                               readonly 
                                               style="background-color: #e9ecef;">
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_email_{{ $email->id }}" class="form-label fw-bold">Nowy adres e-mail: <span class="text-danger">*</span></label>
                                        <input type="email" 
                                               class="form-control @error('email') is-invalid @enderror" 
                                               id="new_email_{{ $email->id }}" 
                                               name="email" 
                                               value="{{ old('email', $email->email) }}" 
                                               required 
                                               autocomplete="off"
                                               placeholder="np. agatakolodziej4@wp.pl">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">
                                            Wprowadź poprawny adres e-mail. Zostanie on zaktualizowany we wszystkich powiązanych tabelach.
                                        </small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Anuluj
                                    </button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save"></i> Zapisz zmiany
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal usuwania e-maila -->
                <div class="modal fade" id="deleteEmailModal{{ $email->id }}" tabindex="-1" aria-labelledby="deleteEmailModalLabel{{ $email->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('participants.emails.destroy', $email->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                @foreach(request()->query() as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endforeach
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteEmailModalLabel{{ $email->id }}">
                                        <i class="fas fa-exclamation-triangle"></i> Potwierdzenie usunięcia
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Czy na pewno chcesz usunąć adres e-mail <strong>{{ $email->email }}</strong>?</p>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Uwaga:</strong> To usunięcie jest "soft delete" - adres e-mail zostanie oznaczony jako usunięty, ale dane w tabelach <code>participants</code>, <code>form_orders</code> i <code>form_order_participants</code> pozostaną bez zmian.
                                    </div>
                                    <div class="bg-light p-3 rounded">
                                        <h6 class="mb-2">Szczegóły:</h6>
                                        <ul class="mb-0">
                                            <li><strong>ID:</strong> {{ $email->id }}</li>
                                            <li><strong>E-mail:</strong> {{ $email->email }}</li>
                                            <li><strong>Liczba szkoleń:</strong> {{ $email->courses_count ?? 0 }}</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Anuluj
                                    </button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Usuń e-mail
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            @else
                <div class="text-center py-5">
                    <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                    @if(request('search') || request('filter_active') || request('filter_verified'))
                        <h4 class="text-muted">Brak wyników wyszukiwania</h4>
                        <p class="text-muted">Nie znaleziono e-maili spełniających wybrane kryteria.</p>
                        <a href="{{ route('participants.emails-list') }}" class="btn btn-primary">
                            <i class="fas fa-list me-1"></i> Pokaż wszystkie e-maile
                        </a>
                    @else
                        <h4 class="text-muted">Brak e-maili w bazie</h4>
                        <p class="text-muted">Nie ma e-maili w bazie. Użyj przycisku "Zbierz bazę e-mail" w widoku listy uczestników.</p>
                        <a href="{{ route('participants.all') }}" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-1"></i> Przejdź do listy uczestników
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


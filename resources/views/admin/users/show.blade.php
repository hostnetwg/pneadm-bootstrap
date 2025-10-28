<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-person-circle me-2"></i>
                Podgląd użytkownika
            </h2>
            <div>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>
                    Powrót do listy
                </a>
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning">
                    <i class="bi bi-pencil me-2"></i>
                    Edytuj
                </a>
            </div>
        </div>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <!-- Informacje podstawowe -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-person me-2"></i>
                                Informacje podstawowe
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">ID użytkownika:</label>
                                        <p class="form-control-plaintext">
                                            <span class="badge bg-secondary fs-6">{{ $user->id }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Imię i nazwisko:</label>
                                        <p class="form-control-plaintext fs-5">{{ $user->name }}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Email:</label>
                                        <p class="form-control-plaintext">
                                            <a href="mailto:{{ $user->email }}" class="text-decoration-none">
                                                <i class="bi bi-envelope me-2"></i>
                                                {{ $user->email }}
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Rola:</label>
                                        <p class="form-control-plaintext">
                                            @if($user->role)
                                                <span class="badge bg-{{ $user->role->name === 'super_admin' ? 'danger' : ($user->role->name === 'admin' ? 'warning' : ($user->role->name === 'manager' ? 'info' : 'secondary')) }} fs-6">
                                                    <i class="bi bi-shield-check me-1"></i>
                                                    {{ $user->role->display_name }}
                                                </span>
                                                @if($user->role->description)
                                                    <br><small class="text-muted">{{ $user->role->description }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-dark fs-6">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    Brak roli
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Status konta:</label>
                                        <p class="form-control-plaintext">
                                            @if($user->is_active)
                                                <span class="badge bg-success fs-6">
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Aktywny
                                                </span>
                                                <br><small class="text-muted">Może się logować</small>
                                            @else
                                                <span class="badge bg-danger fs-6">
                                                    <i class="bi bi-x-circle me-1"></i>
                                                    Nieaktywny
                                                </span>
                                                <br><small class="text-muted">Nie może się logować</small>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Poziom uprawnień:</label>
                                        <p class="form-control-plaintext">
                                            @if($user->role)
                                                <span class="badge bg-info fs-6">
                                                    <i class="bi bi-layers me-1"></i>
                                                    Poziom {{ $user->role->level }}
                                                </span>
                                            @else
                                                <span class="text-muted">Brak</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informacje o sesji -->
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Informacje o sesji
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Ostatnie logowanie:</label>
                                        <p class="form-control-plaintext">
                                            @if($user->last_login_at)
                                                <i class="bi bi-calendar-check me-2"></i>
                                                {{ $user->last_login_at->format('d.m.Y H:i:s') }}
                                                <br><small class="text-muted">{{ $user->last_login_at->diffForHumans() }}</small>
                                            @else
                                                <span class="text-muted">
                                                    <i class="bi bi-dash-circle me-2"></i>
                                                    Nigdy się nie logował
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">IP ostatniego logowania:</label>
                                        <p class="form-control-plaintext">
                                            @if($user->last_login_ip)
                                                <i class="bi bi-globe me-2"></i>
                                                <code>{{ $user->last_login_ip }}</code>
                                            @else
                                                <span class="text-muted">
                                                    <i class="bi bi-dash-circle me-2"></i>
                                                    Brak danych
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel boczny -->
                <div class="col-md-4">
                    <!-- Awatar i akcje -->
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                                <i class="bi bi-person text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">{{ $user->name }}</h5>
                            <p class="text-muted">{{ $user->email }}</p>
                            
                            <div class="d-grid gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning">
                                    <i class="bi bi-pencil me-2"></i>
                                    Edytuj użytkownika
                                </a>
                                @if($user->id !== auth()->id())
                                    <button type="button" class="btn btn-outline-danger w-100" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal">
                                        <i class="bi bi-trash me-2"></i>
                                        Usuń użytkownika
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Informacje o koncie -->
                    <div class="card mt-4">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Informacje o koncie
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Data utworzenia:</label>
                                <p class="form-control-plaintext">
                                    <i class="bi bi-calendar-plus me-2"></i>
                                    {{ $user->created_at->format('d.m.Y H:i:s') }}
                                    <br><small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Ostatnia aktualizacja:</label>
                                <p class="form-control-plaintext">
                                    <i class="bi bi-calendar-check me-2"></i>
                                    {{ $user->updated_at->format('d.m.Y H:i:s') }}
                                    <br><small class="text-muted">{{ $user->updated_at->diffForHumans() }}</small>
                                </p>
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-bold">Email zweryfikowany:</label>
                                <p class="form-control-plaintext">
                                    @if($user->email_verified_at)
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Tak
                                        </span>
                                        <br><small class="text-muted">{{ $user->email_verified_at->format('d.m.Y H:i') }}</small>
                                    @else
                                        <span class="badge bg-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Nie
                                        </span>
                                    @endif
                                </p>
                            </div>
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
                    <p>Czy na pewno chcesz usunąć użytkownika <strong>#{{ $user->id }}</strong>?</p>
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">Szczegóły użytkownika:</h6>
                        <ul class="mb-0">
                            <li><strong>Nazwa:</strong> {{ $user->name }}</li>
                            <li><strong>Email:</strong> {{ $user->email }}</li>
                            <li><strong>Rola:</strong> {{ $user->role ? $user->role->display_name : 'Brak roli' }}</li>
                            <li><strong>Poziom uprawnień:</strong> {{ $user->role ? $user->role->level : 'Brak' }}</li>
                            <li><strong>Status:</strong> {{ $user->is_active ? 'Aktywny' : 'Nieaktywny' }}</li>
                            <li><strong>Ostatnie logowanie:</strong> {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'Nigdy' }}</li>
                            <li><strong>Data utworzenia:</strong> {{ $user->created_at->format('d.m.Y H:i') }}</li>
                            <li><strong>Email zweryfikowany:</strong> {{ $user->email_verified_at ? 'Tak' : 'Nie' }}</li>
                        </ul>
                    </div>
                    @if($user->role && $user->role->level >= auth()->user()->role->level)
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Uwaga:</strong> Nie możesz usunąć użytkownika o równym lub wyższym poziomie uprawnień.
                        </div>
                    @elseif($user->isSuperAdmin())
                        <div class="alert alert-danger mt-3">
                            <i class="bi bi-shield-exclamation"></i>
                            <strong>Uwaga:</strong> To jest Super Administrator. Upewnij się, że nie jest to ostatni Super Admin w systemie.
                        </div>
                    @else
                        <p class="text-muted mt-3">
                            <i class="bi bi-info-circle"></i>
                            Użytkownik zostanie trwale usunięty z systemu. Ta operacja jest nieodwracalna!
                        </p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Anuluj
                    </button>
                    @if($user->role && $user->role->level >= auth()->user()->role->level)
                        <button type="button" class="btn btn-danger" disabled>
                            <i class="bi bi-shield-exclamation"></i> Brak uprawnień
                        </button>
                    @else
                        <form action="{{ route('admin.users.destroy', $user) }}" 
                              method="POST" 
                              class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Usuń użytkownika
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

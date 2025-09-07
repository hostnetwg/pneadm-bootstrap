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
                                <div class="col-md-6">
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
                                <div class="col-md-6">
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
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="bi bi-trash me-2"></i>
                                            Usuń użytkownika
                                        </button>
                                    </form>
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
</x-app-layout>

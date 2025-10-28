<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-people me-2"></i>
            Zarządzanie użytkownikami
        </h2>
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

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Nagłówek z przyciskiem dodawania -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h4 class="text-primary mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Lista użytkowników
                    </h4>
                    <small class="text-muted">Zarządzaj użytkownikami systemu</small>
                </div>
                <div class="col-md-6 text-end">
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>
                        Dodaj użytkownika
                    </a>
                </div>
            </div>

            <!-- Tabela użytkowników -->
            <div class="card">
                <div class="card-body">
                    @if($users->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nazwa</th>
                                        <th>Email</th>
                                        <th>Rola</th>
                                        <th>Status</th>
                                        <th>Ostatnie logowanie</th>
                                        <th>Ostatnia modyfikacja</th>
                                        <th class="text-center">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $user)
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">{{ $user->id }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person text-primary"></i>
                                                </div>
                                                <div>
                                                    <strong>{{ $user->name }}</strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="mailto:{{ $user->email }}" class="text-decoration-none">
                                                {{ $user->email }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($user->role)
                                                <span class="badge bg-{{ $user->role->name === 'super_admin' ? 'danger' : ($user->role->name === 'admin' ? 'warning' : ($user->role->name === 'manager' ? 'info' : 'secondary')) }}">
                                                    {{ $user->role->display_name }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-dark">Brak roli</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($user->is_active)
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Aktywny
                                                </span>
                                            @else
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-x-circle me-1"></i>
                                                    Nieaktywny
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($user->last_login_at)
                                                <div class="d-flex flex-column">
                                                    <small class="text-dark fw-medium">
                                                        <i class="bi bi-calendar-check me-1"></i>
                                                        {{ $user->last_login_at->format('d.m.Y H:i') }}
                                                    </small>
                                                    @if($user->last_login_ip)
                                                        <small class="text-muted">
                                                            <i class="bi bi-globe me-1"></i>
                                                            {{ $user->last_login_ip }}
                                                        </small>
                                                    @endif
                                                </div>
                                            @else
                                                <small class="text-muted">
                                                    <i class="bi bi-dash-circle me-1"></i>
                                                    Nigdy
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $user->updated_at->format('d.m.Y H:i') }}
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-info btn-sm" title="Podgląd">
                                                    <i class="bi bi-eye fs-6"></i>
                                                </a>
                                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-warning btn-sm" title="Edytuj">
                                                    <i class="bi bi-pencil fs-6"></i>
                                                </a>
                                                @if($user->id !== auth()->id())
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            title="Usuń"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal{{ $user->id }}">
                                                        <i class="bi bi-trash fs-6"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                            title="Nie możesz usunąć samego siebie" 
                                                            disabled>
                                                        <i class="bi bi-shield-exclamation fs-6"></i>
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
                        <div class="d-flex justify-content-center mt-4">
                            {{ $users->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">Brak użytkowników</h4>
                            <p class="text-muted">Nie ma jeszcze żadnych użytkowników w systemie.</p>
                            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>
                                Dodaj pierwszego użytkownika
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Statystyki -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-people fs-1 mb-2"></i>
                            <h3 class="mb-0">{{ $users->total() }}</h3>
                            <small>Łączna liczba użytkowników</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-person-check fs-1 mb-2"></i>
                            <h3 class="mb-0">{{ $users->where('created_at', '>=', now()->startOfMonth())->count() }}</h3>
                            <small>Nowi w tym miesiącu</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-day fs-1 mb-2"></i>
                            <h3 class="mb-0">{{ $users->where('created_at', '>=', now()->startOfDay())->count() }}</h3>
                            <small>Nowi dzisiaj</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-clock fs-1 mb-2"></i>
                            <h3 class="mb-0">{{ $users->where('updated_at', '>=', now()->subDays(7))->count() }}</h3>
                            <small>Aktywni w ostatnim tygodniu</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modale potwierdzenia usunięcia użytkowników --}}
    @foreach ($users as $user)
    <div class="modal fade" id="deleteModal{{ $user->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $user->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel{{ $user->id }}">
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
    @endforeach
</x-app-layout>

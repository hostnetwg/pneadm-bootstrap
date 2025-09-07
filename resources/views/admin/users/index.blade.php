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
                                        <th>Data utworzenia</th>
                                        <th>Ostatnia aktualizacja</th>
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
                                            <small class="text-muted">
                                                {{ $user->created_at->format('d.m.Y H:i') }}
                                            </small>
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
                                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Usuń">
                                                        <i class="bi bi-trash fs-6"></i>
                                                    </button>
                                                </form>
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
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            <i class="bi bi-people me-2"></i>
            Użytkownicy pnedu
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container-fluid" style="max-width: 1200px;">
            <p class="text-muted small mb-3">
                Konta zarejestrowane na stronie pnedu.pl (baza <code>pnedu</code>, tabela <code>users</code>).
            </p>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">E-mail</th>
                                    <th scope="col">Imię i nazwisko</th>
                                    <th scope="col">E-mail zweryfikowany</th>
                                    <th scope="col">Rejestracja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr>
                                        <td class="text-muted">{{ $user->id }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->full_name }}</td>
                                        <td>
                                            @if($user->email_verified_at)
                                                <span class="badge text-bg-success">Tak</span>
                                                <span class="text-muted small">{{ $user->email_verified_at->format('Y-m-d H:i') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">Nie</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">
                                            {{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Brak zarejestrowanych użytkowników.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($users->hasPages())
                    <div class="card-footer bg-white border-top-0 py-3">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

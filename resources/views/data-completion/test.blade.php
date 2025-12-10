<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Uzupełnienie danych - Test') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Tryb testowy</strong> - bezpieczne testowanie procesu bez masowej wysyłki do realnych uczestników.
            </div>

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

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Kursy certgen_Publigo</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Poniżej znajduje się lista kursów z <code>source_id_old = "certgen_Publigo"</code>.
                        Możesz wybrać kurs i przetestować proces wysyłki próśb o uzupełnienie danych.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nazwa kursu</th>
                                    <th>Data szkolenia</th>
                                    <th>Instruktor</th>
                                    <th>Uczestnicy z brakami</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($courses as $course)
                                    <tr>
                                        <td>{{ str_replace('&nbsp;', ' ', $course['title']) }}</td>
                                        <td>
                                            @if($course['start_date'])
                                                {{ \Carbon\Carbon::parse($course['start_date'])->format('d.m.Y') }}
                                            @else
                                                <span class="text-muted">Brak daty</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($course['instructor']) && $course['instructor'])
                                                {{ $course['instructor']['full_name'] ?? ($course['instructor']['first_name'] . ' ' . $course['instructor']['last_name']) }}
                                            @else
                                                <span class="text-muted">Brak</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">
                                                {{ $course['stats']['participants_with_missing_data'] ?? 0 }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('data-completion.simulate-test', $course['id']) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> Symuluj
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#testEmailModal{{ $course['id'] }}">
                                                    <i class="fas fa-envelope"></i> Test email
                                                </button>
                                            </div>

                                            <!-- Modal dla testowego emaila -->
                                            <div class="modal fade" id="testEmailModal{{ $course['id'] }}" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="{{ route('data-completion.send-test-email', $course['id']) }}" method="POST">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Wyślij testowy email</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label for="test_email{{ $course['id'] }}" class="form-label">Adres email uczestnika</label>
                                                                    <input type="email" 
                                                                           class="form-control" 
                                                                           id="test_email{{ $course['id'] }}" 
                                                                           name="test_email" 
                                                                           required
                                                                           placeholder="uczestnik@example.com">
                                                                    <div class="form-text">
                                                                        Wpisz email uczestnika, który ma braki w danych dla tego kursu.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                                                                <button type="submit" class="btn btn-primary">Wyślij</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            Brak kursów z source_id_old = "certgen_Publigo"
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $courses->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


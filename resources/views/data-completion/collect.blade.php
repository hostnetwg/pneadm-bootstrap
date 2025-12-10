<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Uzupełnienie danych - Zbierz') }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Tryb produkcyjny</strong> - wysyłka realnych maili do uczestników.
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Kursy certgen_Publigo</h5>
                    <form action="{{ route('data-completion.refresh-bd-certgen-stats') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Odśwież statystyki dla kursów BD:Certgen-education">
                            <i class="fas fa-sync-alt"></i> Odśwież statystyki BD:Certgen-education
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    
                    <!-- Globalne Statystyki -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Wszystkie Kursy</h5>
                                    <p class="display-6">{{ $globalStats['total_courses'] }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Braki Danych</h5>
                                    <p class="display-6">{{ $globalStats['missing_data'] }}</p>
                                    <small>osób</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Uzupełniono</h5>
                                    <p class="display-6">
                                        {{ $globalStats['completed_data'] }}
                                        @if($globalStats['requests_sent'] > 0)
                                            <span class="fs-6 opacity-75">
                                                ({{ round(($globalStats['completed_data'] / $globalStats['requests_sent']) * 100, 1) }}%)
                                            </span>
                                        @endif
                                    </p>
                                    <small>osób</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Wysłano Próśb</h5>
                                    <p class="display-6">{{ $globalStats['requests_sent'] }}</p>
                                    <small>unikalnych adresów</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="text-lg font-bold">Kursy do uzupełnienia danych (tryb produkcyjny)</h3>
                            <p class="text-sm text-gray-600">
                                Poniżej znajduje się lista kursów z <code>source_id_old = "certgen_Publigo"</code>.
                                Kliknij "Poproś o uzupełnienie", aby wysłać prośby do uczestników z brakującymi danymi.
                            </p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nazwa kursu</th>
                                    <th>Data szkolenia</th>
                                    <th>Instruktor</th>
                                    <th>Uczestnicy z brakami</th>
                                    <th>Wysłano próśb</th>
                                    <th>Uzupełniono</th>
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
                                            <span class="badge bg-info">
                                                {{ $course['stats']['requests_sent'] ?? 0 }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                {{ $course['stats']['completed'] ?? 0 }}
                                            </span>
                                        </td>
                                        <td>
                                            <form action="{{ route('data-completion.send-for-course', $course['id']) }}" 
                                                  method="POST" 
                                                  onsubmit="return confirm('Czy na pewno chcesz wysłać prośby o uzupełnienie danych dla tego kursu?');"
                                                  class="d-inline">
                                                @csrf
                                                <button type="submit" 
                                                        class="btn btn-sm btn-primary"
                                                        {{ ($course['stats']['participants_with_missing_data'] ?? 0) == 0 ? 'disabled' : '' }}>
                                                    <i class="fas fa-envelope"></i> Poproś o uzupełnienie
                                                </button>
                                            </form>
                                            @if(($course['stats']['requests_sent'] ?? 0) > 0)
                                                <form action="{{ route('data-completion.send-for-course', $course['id']) }}" 
                                                      method="POST" 
                                                      onsubmit="return confirm('Czy na pewno chcesz WYSLAC PONOWNIE prośby o uzupełnienie danych? Zostaną wysłane do wszystkich uczestników z brakami (również tych, którym już wysłano wcześniej).');"
                                                      class="d-inline ms-1">
                                                    @csrf
                                                    <input type="hidden" name="force_resend" value="1">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-warning"
                                                            title="Wysyła ponownie do wszystkich, nawet jeśli już otrzymali prośbę wcześniej">
                                                        <i class="fas fa-redo"></i> Wyślij ponownie
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
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

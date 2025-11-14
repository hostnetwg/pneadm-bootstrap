<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                <i class="bi bi-bar-chart me-2"></i>
                Statystyki aktywności
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('activity-logs.index') }}" class="btn btn-primary">
                    <i class="bi bi-list"></i> Lista logów
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        {{-- Filtr okresu --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('activity-logs.statistics') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="period" class="form-label">Okres</label>
                        <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                            <option value="1" {{ $period == 1 ? 'selected' : '' }}>Ostatni dzień</option>
                            <option value="7" {{ $period == 7 ? 'selected' : '' }}>Ostatnie 7 dni</option>
                            <option value="30" {{ $period == 30 ? 'selected' : '' }}>Ostatnie 30 dni</option>
                            <option value="90" {{ $period == 90 ? 'selected' : '' }}>Ostatnie 90 dni</option>
                            <option value="365" {{ $period == 365 ? 'selected' : '' }}>Ostatni rok</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        {{-- Karty statystyk --}}
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Wszystkie logi</h6>
                                <h3 class="mb-0">{{ number_format($totalLogs, 0, ',', ' ') }}</h3>
                            </div>
                            <div>
                                <i class="bi bi-activity" style="font-size: 3rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Logowania</h6>
                                <h3 class="mb-0">{{ $logsByType['login'] ?? 0 }}</h3>
                            </div>
                            <div>
                                <i class="bi bi-box-arrow-in-right" style="font-size: 3rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Aktualizacje</h6>
                                <h3 class="mb-0">{{ $logsByType['update'] ?? 0 }}</h3>
                            </div>
                            <div>
                                <i class="bi bi-pencil" style="font-size: 3rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Usunięcia</h6>
                                <h3 class="mb-0">{{ $logsByType['delete'] ?? 0 }}</h3>
                            </div>
                            <div>
                                <i class="bi bi-trash" style="font-size: 3rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statystyki według typu akcji --}}
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-pie-chart me-2"></i>
                            Rozkład typów akcji
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Typ akcji</th>
                                    <th class="text-end">Liczba</th>
                                    <th class="text-end">Procent</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $typeNames = [
                                        'login' => 'Logowanie',
                                        'logout' => 'Wylogowanie',
                                        'create' => 'Utworzenie',
                                        'update' => 'Aktualizacja',
                                        'delete' => 'Usunięcie',
                                        'restore' => 'Przywrócenie',
                                        'view' => 'Wyświetlenie',
                                        'custom' => 'Niestandardowa',
                                    ];
                                @endphp

                                @foreach($logsByType as $type => $count)
                                    @php
                                        $percentage = $totalLogs > 0 ? round(($count / $totalLogs) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $typeNames[$type] ?? $type }}</td>
                                        <td class="text-end">{{ number_format($count, 0, ',', ' ') }}</td>
                                        <td class="text-end">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: {{ $percentage }}%;" 
                                                     aria-valuenow="{{ $percentage }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ $percentage }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach

                                @if($logsByType->isEmpty())
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Brak danych</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Najpopularniejsze modele --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-database me-2"></i>
                            Najpopularniejsze modele
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Model</th>
                                    <th class="text-end">Liczba akcji</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topModels as $item)
                                    <tr>
                                        <td>{{ $item['model'] }}</td>
                                        <td class="text-end">{{ number_format($item['count'], 0, ',', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">Brak danych</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Najbardziej aktywni użytkownicy --}}
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>
                    Najbardziej aktywni użytkownicy (Top 10)
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 30%;">Użytkownik</th>
                            <th style="width: 35%;">Email</th>
                            <th style="width: 15%;" class="text-end">Liczba akcji</th>
                            <th style="width: 15%;">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topUsers as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->user->name ?? 'Nieznany' }}</td>
                                <td>{{ $item->user->email ?? '—' }}</td>
                                <td class="text-end">{{ number_format($item->count, 0, ',', ' ') }}</td>
                                <td>
                                    <a href="{{ route('activity-logs.user-logs', $item->user_id) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Zobacz logi
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">Brak danych</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Aktywność według dni --}}
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="bi bi-calendar3 me-2"></i>
                    Aktywność według dni
                </h5>
            </div>
            <div class="card-body">
                @if($activityByDay->isNotEmpty())
                    @php
                        $maxCount = $activityByDay->max('count');
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Data</th>
                                    <th style="width: 15%;" class="text-end">Liczba akcji</th>
                                    <th style="width: 65%;">Wykres</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activityByDay as $day)
                                    @php
                                        $percentage = $maxCount > 0 ? round(($day->count / $maxCount) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($day->date)->format('d.m.Y') }}</td>
                                        <td class="text-end">{{ number_format($day->count, 0, ',', ' ') }}</td>
                                        <td>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar bg-primary" role="progressbar" 
                                                     style="width: {{ $percentage }}%;" 
                                                     aria-valuenow="{{ $percentage }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ $percentage }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-center text-muted">Brak danych o aktywności</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>













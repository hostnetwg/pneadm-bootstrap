<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Szkolenia ClickMeeting') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">

            {{-- Tytuł + przycisk odświeżania --}}
            <h2>Lista szkoleń pobranych z ClickMeeting</h2>
            <div class="d-flex justify-content-between mb-3">
                <a href="{{ route('clickmeeting.trainings.index') }}" class="btn btn-primary">
                    Odśwież listę
                </a>
            </div>

            {{-- ======================  Tabela  ====================== --}}
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 140px;">Data startu 🕑</th>
                        <th style="width: 90px;">ID</th>
                        <th style="width: 320px;">Tytuł</th>
                        <th style="width: 140px;">PIN pokoju</th>
                        <th style="width: 150px;">Typ pokoju</th>
                        <th style="width: 110px;">Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($trainings as $t)
                        <tr>
                            <td>{{ $t['pretty_date'] }}</td>
                            <td>{{ $t['id'] }}</td>
                            <td>{{ $t['name'] }}</td>
                            <td>{{ $t['room_pin'] ?? '—' }}</td>
                            <td>{{ ucfirst($t['room_type'] ?? '—') }}</td>
                            <td>{{ ucfirst($t['status'] ?? 'aktywne') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Brak szkoleń do wyświetlenia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>
    </div>
</x-app-layout>

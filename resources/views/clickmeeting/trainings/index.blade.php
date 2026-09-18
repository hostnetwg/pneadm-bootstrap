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

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <p class="text-muted">
                ClickMeeting jest źródłem zaplanowanych szkoleń online. Tu widać, które wydarzenia mają już rekord w
                <a href="{{ route('courses.index') }}">szkoleniach</a>.
            </p>
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
                        <th style="width: 280px;">Tytuł</th>
                        <th style="width: 120px;">PIN pokoju</th>
                        <th style="width: 130px;">Typ pokoju</th>
                        <th style="width: 100px;">Status</th>
                        <th>Szkolenie w ADM</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($trainings as $t)
                        @php
                            $linkedCourse = $t['linked_course'] ?? null;
                        @endphp
                        <tr>
                            <td>{{ $t['pretty_date'] }}</td>
                            <td>{{ $t['id'] }}</td>
                            <td>{{ $t['name'] }}</td>
                            <td>{{ $t['room_pin'] ?? '—' }}</td>
                            <td>{{ ucfirst($t['room_type'] ?? '—') }}</td>
                            <td>{{ ucfirst($t['status'] ?? 'aktywne') }}</td>
                            <td>
                                @if($linkedCourse)
                                    <span class="badge text-bg-success">W courses</span>
                                    @if(!empty($t['meeting_link_stale']))
                                        <span class="badge text-bg-warning ms-1">Link nieaktualny</span>
                                    @endif
                                    <a href="{{ route('courses.edit', $linkedCourse->id) }}" class="d-block small mt-1">
                                        #{{ $linkedCourse->id }} {{ $linkedCourse->title }}
                                    </a>
                                    @if(!empty($t['meeting_link_stale']))
                                        <form action="{{ route('clickmeeting.trainings.sync-room-url', $t['id']) }}"
                                              method="POST"
                                              class="mt-2"
                                              data-loading-submit>
                                            @csrf
                                            <button type="submit"
                                                    class="btn btn-sm btn-warning"
                                                    data-loading-text="Aktualizuję…">
                                                Aktualizuj link z ClickMeeting
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <a href="{{ route('clickmeeting.trainings.create-course', $t['id']) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       target="_blank"
                                       rel="noopener">
                                        Dodaj do szkoleń
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">Brak szkoleń do wyświetlenia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>
    </div>
</x-app-layout>

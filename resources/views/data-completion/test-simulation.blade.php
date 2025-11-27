<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Symulacja testowa - ') }}{{ str_replace('&nbsp;', ' ', $course['title']) }}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Symulacja</strong> - poniżej znajduje się lista uczestników, którzy spełniają kryteria. 
                Żadne maile nie zostały wysłane.
            </div>

            <div class="mb-3">
                <a href="{{ route('data-completion.test') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Powrót do listy
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Uczestnicy z brakującymi danymi</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Znaleziono <strong>{{ $participants->count() }}</strong> uczestników z brakującymi danymi.
                    </p>

                    @if($exampleParticipant)
                        <div class="alert alert-light border">
                            <h6>Przykładowy email dla: {{ $exampleParticipant['full_name'] }}</h6>
                            <p class="mb-2"><strong>Email:</strong> {{ $exampleParticipant['email'] }}</p>
                            <p class="mb-2"><strong>Liczba kursów:</strong> {{ $exampleParticipant['courses_count'] }}</p>
                            <p class="mb-0"><strong>Kursy:</strong></p>
                            <ul class="mb-0">
                                @foreach($exampleParticipant['courses'] as $courseItem)
                                    <li>
                                        {{ str_replace('&nbsp;', ' ', $courseItem->title) }}
                                        @if($courseItem->start_date)
                                            ({{ \Carbon\Carbon::parse($courseItem->start_date)->format('d.m.Y') }})
                                        @endif
                                        @if($courseItem->instructor)
                                            - {{ $courseItem->instructor->full_name ?? ($courseItem->instructor->first_name . ' ' . $courseItem->instructor->last_name) }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Imię i nazwisko</th>
                                    <th>Liczba kursów</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($participants as $participant)
                                    <tr>
                                        <td>{{ $participant['email'] }}</td>
                                        <td>{{ $participant['full_name'] }}</td>
                                        <td>
                                            <span class="badge bg-info">{{ $participant['courses_count'] }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">
                                            Brak uczestników spełniających kryteria
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


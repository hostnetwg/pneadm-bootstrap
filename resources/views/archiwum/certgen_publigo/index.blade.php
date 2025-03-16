<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista szkoleń Publigo') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h2>Lista szkoleń (baza: certgen, tabela: publigo)</h2>
            <!-- Przycisk dodawania nowego szkolenia i eksportu -->
            <div class="d-flex justify-content-between mb-3">
                <a href="{{ route('certgen_publigo.create') }}" class="btn btn-primary">Dodaj nowe szkolenie</a>
                <a href="{{ route('courses.importPubligo') }}" class="btn btn-success">Certgen - PUBLIGO EXPORT</a>
            </div>            
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <!-- Kolumna Data z domyślną ikoną sortowania -->
                        <th style="width: 150px;">
                            <a href="{{ route('archiwum.certgen_publigo.index', ['sort' => 'start_date', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none">
                                Data
                                @if(request('sort') == 'start_date')
                                    @if(request('order') == 'asc')
                                        🔼 <!-- Ikona sortowania rosnącego -->
                                    @else
                                        🔽 <!-- Ikona sortowania malejącego -->
                                    @endif
                                @else
                                    🔽 <!-- Domyślna ikona sortowania malejącego -->
                                @endif
                            </a>
                        </th>

                        <!-- ID ze starej bazy -->
                        <th style="width: 100px;">ID Publigo</th>

                        <!-- Tytuł -->
                        <th style="width: 250px;">Tytuł</th>

                        <!-- Kolumna zbiorcza (is_paid, type, category, is_active) -->
                        <th style="width: 200px;">Szczegóły</th>

                        <!-- Lokalizacja: Adres offline lub Platforma online -->
                        <th style="width: 200px;">Lokalizacja</th>

                        <!-- Instruktor -->
                        <th style="width: 200px;">Instruktor</th>

                        <!-- Akcje -->
                        <th style="width: 150px;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($szkolenia as $szkolenie)
                        <tr>
                            <!-- Start Date -->
                            <td>{{ $szkolenie->start_date }}</td>

                            <!-- ID Old -->
                            <td>{{ $szkolenie->id_old }}</td>

                            <!-- Tytuł -->
                            <td>{{ $szkolenie->title }}</td>

                            <!-- Szczegóły -->
                            <td>
                                <b>Płatne:</b> {{ $szkolenie->is_paid ? 'Tak' : 'Nie' }} <br>
                                <b>Rodzaj:</b> {{ ucfirst($szkolenie->type) }} <br>
                                <b>Kategoria:</b> {{ ucfirst($szkolenie->category) }} <br>
                                <b>Aktywne:</b> {{ $szkolenie->is_active ? 'Tak' : 'Nie' }}
                            </td>

                            <!-- Lokalizacja -->
                            <td>
                                @if($szkolenie->type == 'online')
                                    <b>Platforma:</b> {{ $szkolenie->platform }}
                                @else
                                    <b>Miejsce:</b> {{ $szkolenie->location_name }} <br>
                                    <b>Adres:</b> {{ $szkolenie->address }} <br>
                                    <b>Kod pocztowy:</b> {{ $szkolenie->postal_code }} <br>
                                    <b>Poczta:</b> {{ $szkolenie->post_office }}
                                @endif
                            </td>

                            <!-- Instruktor -->
                            <td>{{ $instructors[$szkolenie->instructor_id] ?? 'Brak' }}</td>

                            <!-- Akcje -->
                            <td>
                                <div class="d-grid gap-2">
                                    <a href="{{ route('certgen_publigo.edit', $szkolenie->id) }}" class="btn btn-warning btn-sm w-100">
                                        <i class="fas fa-edit"></i> Edytuj
                                    </a>
                                    <form action="{{ route('certgen_publigo.destroy', $szkolenie->id) }}" method="POST"
                                          onsubmit="return confirm('Czy na pewno chcesz usunąć to szkolenie?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-100">
                                            <i class="fas fa-trash"></i> Usuń
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Paginacja -->
            <div class="d-flex justify-content-center mt-3">
                {{ $szkolenia->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

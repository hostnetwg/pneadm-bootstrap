<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista szkoleń Publigo') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between mb-3">
                <h2>Lista szkoleń Publigo</h2>
                <a href="{{ route('certgen_publigo.create') }}" class="btn btn-success">
                    <i class="fas fa-plus"></i> Dodaj nowe szkolenie
                </a>
            </div>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                       <!-- Przycisk sortowania dla kolumny Data -->
                       <th>
                        <a href="{{ route('archiwum.certgen_publigo.index', ['sort' => 'start_date', 'order' => request('order') == 'asc' ? 'desc' : 'asc']) }}">
                            Data
                            @if(request('sort') == 'start_date')
                                @if(request('order') == 'asc')
                                    🔼 <!-- Ikona sortowania rosnącego -->
                                @else
                                    🔽 <!-- Ikona sortowania malejącego -->
                                @endif
                            @endif
                        </a>
                    </th>                        
                        <th>Tytuł</th>
                        <th>Opis</th>
                        <th>Płatne?</th>
                        <th>Rodzaj</th>
                        <th>Kategoria</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($szkolenia as $szkolenie)
                        <tr>
                            <td>{{ $szkolenie->id }}</td>
                            <td>{{ $szkolenie->start_date }}</td>
                            <td>{{ $szkolenie->title }}</td>
                            <td>{{ $szkolenie->description }}</td>
                            <td>{{ $szkolenie->is_paid ? 'Tak' : 'Nie' }}</td>
                            <td>{{ ucfirst($szkolenie->type) }}</td>
                            <td>{{ ucfirst($szkolenie->category) }}</td>
                            <td>
                                <a href="{{ route('certgen_publigo.edit', $szkolenie->id) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Edytuj
                                </a>
                                <!-- Przycisk otwierający modal -->
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $szkolenie->id }}">
                                    <i class="fas fa-trash"></i> Usuń
                                </button>

                                <!-- Modal potwierdzający usunięcie -->
                                <div class="modal fade" id="deleteModal{{ $szkolenie->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $szkolenie->id }}" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel{{ $szkolenie->id }}">Potwierdzenie usunięcia</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                            </div>
                                            <div class="modal-body">
                                                Czy na pewno chcesz usunąć szkolenie: <strong>{{ $szkolenie->title }}</strong>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                                                <form action="{{ route('certgen_publigo.destroy', $szkolenie->id) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">Usuń</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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

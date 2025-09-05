<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h2 class="mb-4">Lista szkoleń (baza: certgen, tabela: education)</h2>
            {{-- Wyświetlanie komunikatów sukcesu --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {!! session('success') !!}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Wyświetlanie komunikatów błędów --}}
            @if (session('error'))
                <div class="alert alert-danger">
                    {!! session('error') !!}
                </div>
            @endif

            {{-- formularz do filtrowania --}}
            <form method="GET" action="{{ route('education.index') }}" class="mb-4">
                <div class="input-group">
                    <input type="text" name="type" class="form-control" placeholder="Wpisz typ..." value="{{ request('type') }}">
                    <button class="btn btn-primary" type="submit">Filtruj</button>
                    <a href="{{ route('education.index') }}" class="btn btn-secondary">Wyczyść</a>
                </div>
            </form>    
            <div class="mb-3">
                <a href="{{ route('education.export', ['type' => request('type')]) }}" class="btn btn-success">
                    Eksportuj dane do Courses
                </a>
            </div>            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>LP</th>
                        <th>Tytuł</th>
                        <th>Opis</th>
                        <th>Data</th>
                        <th>Typ</th>
                        <th>Uczestnicy</th>                        
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($educations as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->lp }}</td>
                            <td>{{ $item->title }}</td>
                            <td>{{ $item->zagadnienia }}</td>
                            <td>{{ $item->data }}</td>
                            <td>{{ $item->type }}</td>
                            <td>{{ $item->participants_count }}</td>                            
                            <td>
                                <a href="{{ route('education.exportParticipants', ['id' => $item->id]) }}" class="btn btn-sm btn-primary">
                                    Eksportuj uczestników
                                </a>
                            </td>                            
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            {{-- Paginacja --}}
            <div class="d-flex justify-content-center mt-4">
                {{ $educations->appends(['type' => request('type')])->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

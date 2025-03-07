<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Lista szkoleń NODN') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <h2 class="mb-4">Lista szkoleń (baza: certgen, tabela: NODN_szkolenia_lista)</h2>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {!! session('success') !!}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {!! session('error') !!}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Przycisk eksportu -->
            <div class="mb-3">
                <a href="{{ route('nodn.szkolenia.export') }}" class="btn btn-success">
                    Eksportuj dane do Courses
                </a>
            </div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tytuł</th>
                        <th>Opis</th>
                        <th>Data</th>
                        <th>Uczestnicy</th>
                        <th>Online</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($szkolenia as $szkolenie)
                        <tr>
                            <td>{{ $szkolenie->id }}</td>
                            <td>{{ $szkolenie->nazwa }}</td>
                            <td>{{ $szkolenie->zakres }}</td>
                            <td>{{ $szkolenie->termin }}</td>
                            <td>{{ $szkolenie->participants_count ?? 0 }}</td>
                            <td>{{ $szkolenie->online }}</td> 
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

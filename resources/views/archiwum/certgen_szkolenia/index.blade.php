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
{{--            
            <div class="mb-3">
                <a href="{{ route('nodn.szkolenia.export') }}" class="btn btn-success">
                    Eksportuj wszystkie dane do Courses
                </a>
            </div>
--}}        
            <!-- Formularz do eksportu zaznaczonych kursów -->
            <form id="exportForm" method="POST" action="{{ route('nodn.szkolenia.export.selected') }}">
                @csrf                
                <div class="mb-3 d-flex">
                    <button type="submit" class="btn btn-success me-2">
                        Eksportuj zaznaczone do Courses
                    </button>
                </div>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll">
                            </th>
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
                                <td>
                                    <input type="checkbox" name="selected_courses[]" value="{{ $szkolenie->id }}" class="courseCheckbox">
                                </td>
                                <td>{{ $szkolenie->id }}</td>
                                <td>{{ $szkolenie->nazwa }}</td>
                                <td>{{ $szkolenie->zakres }}</td>
                                <td>{{ $szkolenie->termin }}</td>
                                <td>{{ $szkolenie->participants_count ?? 0 }}</td>
                                <td>{{ $szkolenie->online }}</td> 
                                <td>
                                    <a href="{{ route('exportParticipants', $szkolenie->id) }}" class="btn btn-sm btn-primary">Eksportuj uczestników</a>
                                    <a href="{{ route('exportCourse', $szkolenie->id) }}" class="btn btn-sm btn-warning">Eksportuj kurs</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- Skrypt do obsługi checkboxów -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const selectAllCheckbox = document.getElementById("selectAll");
            const toggleSelectAllButton = document.getElementById("toggleSelectAll");
            const checkboxes = document.querySelectorAll(".courseCheckbox");

            selectAllCheckbox.addEventListener("change", function () {
                checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
            });

            toggleSelectAllButton.addEventListener("click", function () {
                const allChecked = [...checkboxes].every(checkbox => checkbox.checked);
                checkboxes.forEach(checkbox => checkbox.checked = !allChecked);
            });
        });
    </script>

</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark mb-0">Import wyciągu mBank</h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header fw-semibold">Wgraj CSV mBank</div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Eksport z mBank: <code>lista_operacji_*.csv</code> (UTF-8, separator <code>;</code>).
                                Po imporcie dopasowania zatwierdzasz ręcznie — bez rejestracji wpłat w iFirma.
                                Duży plik (kilka tysięcy wierszy) może zająć ok. 1–2 minuty.
                            </p>
                            <form method="POST" action="{{ route('accounting.bank-imports.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Plik CSV</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Importuj i dopasuj</button>
                                <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary">Windykacja</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">Ostatnie importy</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Unikalny numer rekordu importu w bazie (bank_statement_imports.id).">
                                        ID
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Oryginalna nazwa wgranego pliku CSV z mBank (lista_operacji_*.csv). Kopia pliku jest też zapisana na serwerze.">
                                        Plik
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Zakres dat operacji odczytany z wyciągu (period_from → period_to). Pokazuje, jaki okres obejmuje ten import.">
                                        Okres
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Liczba nowo zapisanych wpływów (kwota &gt; 0) względem wszystkich wierszy z tego pliku: wpływy / wszystkie wiersze. Nie obejmuje operacji pominiętych jako duplikaty.">
                                        Wpływy
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ile wpływów z tego importu ma obecnie co najmniej jedną automatyczną sugestię dopasowania (status suggested). Ustawiane przy imporcie i aktualizowane po „Przelicz sugestie”. Nie oznacza jeszcze zaakceptowania.">
                                        Sugestie
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Ile wierszy z pliku pominięto, bo taka sama operacja (data + kwota + znormalizowany opis) była już w bazie. Nie tworzymy drugiego przelewu — wcześniejsza akceptacja/ignorowanie zostaje.">
                                        Duplikaty
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Status przetwarzania importu: „Sparsowany” = plik wczytany do bazy i gotowy do ręcznego przeglądu; „Przejrzany” = oznaczony jako przejrzany (jeśli używane).">
                                        Status
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Użytkownik panelu, który wgrał ten plik CSV.">
                                        Kto
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Data i godzina utworzenia rekordu importu (kiedy plik został wgrany).">
                                        Kiedy
                                    </th>
                                    <th scope="col"
                                        class="text-nowrap"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Wejście do podglądu transakcji tego importu — kolejka dopasowań, akceptacja, ignorowanie, ręczne powiązanie.">
                                        <span class="visually-hidden">Akcje</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($imports as $import)
                                    <tr>
                                        <td>{{ $import->id }}</td>
                                        <td class="small">{{ $import->original_filename }}</td>
                                        <td class="small">
                                            @if($import->period_from || $import->period_to)
                                                {{ $import->period_from?->format('Y-m-d') ?? '—' }}
                                                →
                                                {{ $import->period_to?->format('Y-m-d') ?? '—' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $import->rows_incoming }} / {{ $import->rows_total }}</td>
                                        <td>{{ $import->rows_matched }}</td>
                                        <td>{{ $import->rows_duplicate }}</td>
                                        <td><span class="badge text-bg-light border">{{ $import->statusLabel() }}</span></td>
                                        <td class="small">{{ $import->uploader?->name ?? '—' }}</td>
                                        <td class="small">{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('accounting.bank-imports.show', $import) }}" class="btn btn-sm btn-outline-primary">Podgląd</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-muted text-center py-4">Brak importów.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($imports->hasPages())
                    <div class="card-footer">{{ $imports->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.bootstrap || !bootstrap.Tooltip) {
                return;
            }
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        });
    </script>
</x-app-layout>

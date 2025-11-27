<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Konflikty danych uczestników') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <div class="mb-4">
                        <h3 class="text-lg font-bold mb-2">Wykryte konflikty</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Poniższa lista zawiera adresy e-mail, które w systemie są przypisane do więcej niż jednego imienia i nazwiska.
                            Może to sugerować błąd w danych lub współdzielenie adresu e-mail przez różne osoby.
                        </p>

                        <!-- Filtr -->
                        <form action="{{ route('data-completion.conflicts') }}" method="GET" class="row g-3 align-items-center mb-4 p-3 bg-light border rounded">
                            <div class="col-auto">
                                <label for="source_id" class="col-form-label fw-bold">Filtruj wg rodzaju szkolenia (source_id_old):</label>
                            </div>
                            <div class="col-auto">
                                <select name="source_id" id="source_id" class="form-select">
                                    <option value="">-- Wszystkie --</option>
                                    @foreach($sourceTypes as $type)
                                        <option value="{{ $type }}" {{ $filterSourceId == $type ? 'selected' : '' }}>
                                            {{ $type }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Filtruj</button>
                                @if($filterSourceId)
                                    <a href="{{ route('data-completion.conflicts') }}" class="btn btn-outline-secondary ms-2">Wyczyść filtr</a>
                                @endif
                            </div>
                        </form>
                    </div>

                    @if($conflicts->isEmpty())
                        <div class="alert alert-success">
                            Nie znaleziono konfliktów w bazie danych
                            @if($filterSourceId)
                                dla wybranego filtra: <strong>{{ $filterSourceId }}</strong>
                            @endif.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">Lp.</th>
                                        <th>Adres E-mail</th>
                                        <th>Znalezione warianty nazwisk (Liczba wystąpień)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $counter = 1; @endphp
                                    @foreach($conflicts as $email => $names)
                                        <tr>
                                            <td>{{ $counter++ }}</td>
                                            <td class="fw-bold">{{ $email }}</td>
                                            <td>
                                                <ul class="list-unstyled mb-0">
                                                    @foreach($names as $nameInfo)
                                                        <li class="mb-2 d-flex align-items-center">
                                                            <span class="badge bg-warning text-dark me-2" title="Liczba wystąpień">{{ $nameInfo['count'] }}</span>
                                                            
                                                            <!-- Używamy pre-wrap i ramki, żeby pokazać spacje -->
                                                            <div class="d-flex gap-1 align-items-center font-monospace small">
                                                                <span class="bg-light border px-1 rounded" style="white-space: pre;" title="Imię (wraz ze spacjami)">{{ $nameInfo['first_name'] }}</span>
                                                                <span class="bg-light border px-1 rounded" style="white-space: pre;" title="Nazwisko (wraz ze spacjami)">{{ $nameInfo['last_name'] }}</span>
                                                            </div>
                                                            
                                                            <!-- Formularz ujednolicania -->
                                                            <form action="{{ route('data-completion.unify-conflict') }}" method="POST" class="d-inline-block ms-3">
                                                                @csrf
                                                                <input type="hidden" name="email" value="{{ $email }}">
                                                                <input type="hidden" name="first_name" value="{{ trim($nameInfo['first_name']) }}">
                                                                <input type="hidden" name="last_name" value="{{ trim($nameInfo['last_name']) }}">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="return confirm('Czy na pewno chcesz ujednolicić WSZYSTKIE rekordy dla adresu {{ $email }} do: {{ trim($nameInfo['first_name']) }} {{ trim($nameInfo['last_name']) }}?')">
                                                                    Ujednolić do
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                                    * Ramki pokazują dokładną zawartość pól (uwzględniając spacje).
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4 text-muted small">
                            Liczba znalezionych adresów e-mail z konfliktami: {{ $conflicts->count() }}
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>


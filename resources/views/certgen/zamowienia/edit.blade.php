<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj zakup') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Edytuj zakup ID: {{ $zamowienie->id }}</h3>
                <div>
                    <a href="{{ route('certgen.zamowienia.index') }}" class="btn btn-secondary">
                        ← Powrót do listy
                    </a>
                    <a href="{{ route('certgen.zamowienia.show', $zamowienie->id) }}" class="btn btn-info">
                        👁️ Szczegóły
                    </a>
                </div>
            </div>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Uwaga!</strong> Wystąpiły błędy w formularzu:
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Formularz edycji</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('certgen.zamowienia.update', $zamowienie->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <!-- ID Zamówienia -->
                            <div class="col-md-6 mb-3">
                                <label for="id_zam" class="form-label">ID Zamówienia</label>
                                <input type="text" 
                                       class="form-control @error('id_zam') is-invalid @enderror" 
                                       id="id_zam" 
                                       name="id_zam" 
                                       value="{{ old('id_zam', $zamowienie->id_zam ?? '') }}"
                                       placeholder="ID zamówienia">
                                @error('id_zam')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Data Wpłaty -->
                            <div class="col-md-6 mb-3">
                                <label for="data_wplaty" class="form-label">Data Wpłaty</label>
                                <input type="datetime-local" 
                                       class="form-control @error('data_wplaty') is-invalid @enderror" 
                                       id="data_wplaty" 
                                       name="data_wplaty" 
                                       value="{{ old('data_wplaty', $zamowienie->data_wplaty ? \Carbon\Carbon::parse($zamowienie->data_wplaty)->format('Y-m-d\TH:i') : '') }}">
                                @error('data_wplaty')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3 text-primary">Dane osobowe</h6>
                        <div class="row">
                            <!-- Imię -->
                            <div class="col-md-4 mb-3">
                                <label for="imie" class="form-label">Imię</label>
                                <input type="text" 
                                       class="form-control @error('imie') is-invalid @enderror" 
                                       id="imie" 
                                       name="imie" 
                                       value="{{ old('imie', $zamowienie->imie ?? '') }}"
                                       placeholder="Imię">
                                @error('imie')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Nazwisko -->
                            <div class="col-md-4 mb-3">
                                <label for="nazwisko" class="form-label">Nazwisko</label>
                                <input type="text" 
                                       class="form-control @error('nazwisko') is-invalid @enderror" 
                                       id="nazwisko" 
                                       name="nazwisko" 
                                       value="{{ old('nazwisko', $zamowienie->nazwisko ?? '') }}"
                                       placeholder="Nazwisko">
                                @error('nazwisko')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="col-md-4 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       id="email" 
                                       name="email" 
                                       value="{{ old('email', $zamowienie->email ?? '') }}"
                                       placeholder="email@example.com">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3 text-primary">Adres</h6>
                        <div class="row">
                            <!-- Kod -->
                            <div class="col-md-4 mb-3">
                                <label for="kod" class="form-label">Kod pocztowy</label>
                                <input type="text" 
                                       class="form-control @error('kod') is-invalid @enderror" 
                                       id="kod" 
                                       name="kod" 
                                       value="{{ old('kod', $zamowienie->kod ?? '') }}"
                                       placeholder="00-000">
                                @error('kod')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Poczta -->
                            <div class="col-md-4 mb-3">
                                <label for="poczta" class="form-label">Poczta</label>
                                <input type="text" 
                                       class="form-control @error('poczta') is-invalid @enderror" 
                                       id="poczta" 
                                       name="poczta" 
                                       value="{{ old('poczta', $zamowienie->poczta ?? '') }}"
                                       placeholder="Miejscowość">
                                @error('poczta')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Adres -->
                            <div class="col-md-4 mb-3">
                                <label for="adres" class="form-label">Adres</label>
                                <input type="text" 
                                       class="form-control @error('adres') is-invalid @enderror" 
                                       id="adres" 
                                       name="adres" 
                                       value="{{ old('adres', $zamowienie->adres ?? '') }}"
                                       placeholder="Ulica i numer">
                                @error('adres')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3 text-primary">Produkt</h6>
                        <div class="row">
                            <!-- ID Produktu -->
                            <div class="col-md-4 mb-3">
                                <label for="produkt_id" class="form-label">ID Produktu</label>
                                <input type="text" 
                                       class="form-control @error('produkt_id') is-invalid @enderror" 
                                       id="produkt_id" 
                                       name="produkt_id" 
                                       value="{{ old('produkt_id', $zamowienie->produkt_id ?? '') }}"
                                       placeholder="ID produktu">
                                @error('produkt_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Nazwa Produktu -->
                            <div class="col-md-4 mb-3">
                                <label for="produkt_nazwa" class="form-label">Nazwa Produktu</label>
                                <input type="text" 
                                       class="form-control @error('produkt_nazwa') is-invalid @enderror" 
                                       id="produkt_nazwa" 
                                       name="produkt_nazwa" 
                                       value="{{ old('produkt_nazwa', $zamowienie->produkt_nazwa ?? '') }}"
                                       placeholder="Nazwa produktu">
                                @error('produkt_nazwa')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Cena Produktu -->
                            <div class="col-md-4 mb-3">
                                <label for="produkt_cena" class="form-label">Cena Produktu (zł)</label>
                                <input type="number" 
                                       class="form-control @error('produkt_cena') is-invalid @enderror" 
                                       id="produkt_cena" 
                                       name="produkt_cena" 
                                       value="{{ old('produkt_cena', $zamowienie->produkt_cena ?? '') }}"
                                       step="0.01"
                                       min="0"
                                       placeholder="0.00">
                                @error('produkt_cena')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3 text-primary">Dodatkowe informacje</h6>
                        <div class="row">
                            <!-- Wysyłka -->
                            <div class="col-md-3 mb-3">
                                <label for="wysylka" class="form-label">Wysyłka</label>
                                <input type="number" 
                                       class="form-control @error('wysylka') is-invalid @enderror" 
                                       id="wysylka" 
                                       name="wysylka" 
                                       value="{{ old('wysylka', $zamowienie->wysylka ?? '') }}"
                                       placeholder="0">
                                @error('wysylka')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- ID Edu -->
                            <div class="col-md-3 mb-3">
                                <label for="id_edu" class="form-label">ID Edu</label>
                                <input type="number" 
                                       class="form-control @error('id_edu') is-invalid @enderror" 
                                       id="id_edu" 
                                       name="id_edu" 
                                       value="{{ old('id_edu', $zamowienie->id_edu ?? '') }}"
                                       placeholder="0">
                                @error('id_edu')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- NR -->
                            <div class="col-md-3 mb-3">
                                <label for="NR" class="form-label">NR</label>
                                <input type="text" 
                                       class="form-control @error('NR') is-invalid @enderror" 
                                       id="NR" 
                                       name="NR" 
                                       value="{{ old('NR', $zamowienie->NR ?? '') }}"
                                       placeholder="NR">
                                @error('NR')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Przyciski -->
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('certgen.zamowienia.show', $zamowienie->id) }}" class="btn btn-secondary">
                                ❌ Anuluj
                            </a>
                            <button type="submit" class="btn btn-primary">
                                💾 Zapisz zmiany
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


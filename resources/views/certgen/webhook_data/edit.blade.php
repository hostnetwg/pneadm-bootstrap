<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj rekord webhook') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Edytuj rekord ID: {{ $webhookRecord->id }}</h3>
                <div>
                    <a href="{{ route('certgen.webhook_data.index') }}" class="btn btn-secondary">
                        ← Powrót do listy
                    </a>
                    <a href="{{ route('certgen.webhook_data.show', $webhookRecord->id) }}" class="btn btn-info">
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

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Formularz edycji</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('certgen.webhook_data.update', $webhookRecord->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <!-- ID Produktu -->
                            <div class="col-md-6 mb-3">
                                <label for="id_produktu" class="form-label">ID Produktu</label>
                                <input type="number" 
                                       class="form-control @error('id_produktu') is-invalid @enderror" 
                                       id="id_produktu" 
                                       name="id_produktu" 
                                       value="{{ old('id_produktu', $webhookRecord->id_produktu ?? '') }}"
                                       placeholder="ID produktu na Publigo">
                                @error('id_produktu')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Data -->
                            <div class="col-md-6 mb-3">
                                <label for="data" class="form-label">Data i godzina szkolenia</label>
                                <input type="datetime-local" 
                                       class="form-control @error('data') is-invalid @enderror" 
                                       id="data" 
                                       name="data" 
                                       value="{{ old('data', $webhookRecord->data ? \Carbon\Carbon::parse($webhookRecord->data)->format('Y-m-d\TH:i') : '') }}">
                                @error('data')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <!-- ID Sendy -->
                            <div class="col-md-6 mb-3">
                                <label for="id_sendy" class="form-label">ID Sendy</label>
                                <input type="text" 
                                       class="form-control @error('id_sendy') is-invalid @enderror" 
                                       id="id_sendy" 
                                       name="id_sendy" 
                                       value="{{ old('id_sendy', $webhookRecord->id_sendy ?? '') }}"
                                       placeholder="ID listy e-mailowej na SENDY">
                                @error('id_sendy')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- ClickMeeting -->
                            <div class="col-md-6 mb-3">
                                <label for="clickmeeting" class="form-label">ClickMeeting</label>
                                <input type="number" 
                                       class="form-control @error('clickmeeting') is-invalid @enderror" 
                                       id="clickmeeting" 
                                       name="clickmeeting" 
                                       value="{{ old('clickmeeting', $webhookRecord->clickmeeting ?? '') }}"
                                       placeholder="ID spotkania ClickMeeting">
                                @error('clickmeeting')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Temat -->
                        <div class="mb-3">
                            <label for="temat" class="form-label">Temat szkolenia</label>
                            <textarea class="form-control @error('temat') is-invalid @enderror" 
                                      id="temat" 
                                      name="temat" 
                                      rows="3" 
                                      placeholder="Temat szkolenia">{{ old('temat', $webhookRecord->temat ?? '') }}</textarea>
                            @error('temat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Instruktor -->
                        <div class="mb-3">
                            <label for="instruktor" class="form-label">Instruktor</label>
                            <input type="text" 
                                   class="form-control @error('instruktor') is-invalid @enderror" 
                                   id="instruktor" 
                                   name="instruktor" 
                                   value="{{ old('instruktor', $webhookRecord->instruktor ?? '') }}"
                                   placeholder="Imię i nazwisko instruktora">
                            @error('instruktor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Przyciski -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('certgen.webhook_data.show', $webhookRecord->id) }}" class="btn btn-secondary">
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

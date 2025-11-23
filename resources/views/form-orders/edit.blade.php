<x-app-layout>
    {{-- ======================  Nagłówek strony  ====================== --}}
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edycja zamówienia') }} #{{ $zamowienie->id }} <span class="text-danger">(PNEADM)</span>
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">

            {{-- Breadcrumb --}}
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('form-orders.index') }}">Zamówienia</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('form-orders.show', $zamowienie->id) }}">Zamówienie #{{ $zamowienie->id }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Edycja
                    </li>
                </ol>
            </nav>

            {{-- Komunikaty --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Formularz edycji --}}
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil"></i> Edycja zamówienia #{{ $zamowienie->id }}
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('form-orders.update', $zamowienie->id) }}">
                        @csrf
                        @method('PUT')
                        
                        {{-- Ukryte pole oznaczające pochodzenie --}}
                        <input type="hidden" name="from_edit_page" value="1">
                        
                        {{-- Przekazanie parametrów filtrów --}}
                        @if(request('filter_new'))
                            <input type="hidden" name="filter_new" value="{{ request('filter_new') }}">
                        @endif
                        @if(request('course_id'))
                            <input type="hidden" name="course_id" value="{{ request('course_id') }}">
                        @endif

                        {{-- Informacje o szkoleniu --}}
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar-event"></i> Informacje o szkoleniu
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label for="product_name" class="form-label">Nazwa szkolenia</label>
                                        <input type="text" class="form-control" 
                                               id="product_name" name="product_name" 
                                               value="{{ old('product_name', $zamowienie->product_name) }}" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="product_price" class="form-label">Cena (PLN)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="product_price" name="product_price" 
                                               value="{{ old('product_price', $zamowienie->product_price) }}">
                                    </div>
                                </div>
                                @if($zamowienie->publigo_product_id)
                                    <div class="mt-2">
                                        <span class="badge bg-info">Publigo Product ID: {{ $zamowienie->publigo_product_id }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- UCZESTNIK --}}
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-person"></i> UCZESTNIK
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="participant_firstname" class="form-label">Imię <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('participant_firstname') is-invalid @enderror" 
                                               id="participant_firstname" name="participant_firstname" 
                                               value="{{ old('participant_firstname', $participant->participant_firstname ?? '') }}" required>
                                        @error('participant_firstname')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="participant_lastname" class="form-label">Nazwisko <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('participant_lastname') is-invalid @enderror" 
                                               id="participant_lastname" name="participant_lastname" 
                                               value="{{ old('participant_lastname', $participant->participant_lastname ?? '') }}" required>
                                        @error('participant_lastname')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="participant_email" class="form-label">Email uczestnika - tu zostaną wysłane dane dostępowe do szkolenia <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control @error('participant_email') is-invalid @enderror" 
                                               id="participant_email" name="participant_email" 
                                               value="{{ old('participant_email', $zamowienie->participant_email) }}" required>
                                        @error('participant_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ZAMAWIAJĄCY --}}
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="bi bi-telephone"></i> ZAMAWIAJĄCY (KONTAKT)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="orderer_name" class="form-label">Nazwa / Imię i nazwisko <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('orderer_name') is-invalid @enderror" 
                                               id="orderer_name" name="orderer_name" 
                                               value="{{ old('orderer_name', $zamowienie->orderer_name) }}" required>
                                        @error('orderer_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_phone" class="form-label">Telefon kontaktowy <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control @error('orderer_phone') is-invalid @enderror" 
                                               id="orderer_phone" name="orderer_phone" 
                                               value="{{ old('orderer_phone', $zamowienie->orderer_phone) }}" required>
                                        @error('orderer_phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_email" class="form-label">E-mail - tu prześlemy fakturę <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control @error('orderer_email') is-invalid @enderror" 
                                               id="orderer_email" name="orderer_email" 
                                               value="{{ old('orderer_email', $zamowienie->orderer_email) }}" required>
                                        @error('orderer_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- NABYWCA --}}
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-building"></i> NABYWCA (DO FAKTURY)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="buyer_name" class="form-label">Nazwa / Imię i nazwisko <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_name') is-invalid @enderror" 
                                               id="buyer_name" name="buyer_name" 
                                               value="{{ old('buyer_name', $zamowienie->buyer_name) }}" required>
                                        @error('buyer_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <label for="buyer_postal_code" class="form-label">Kod pocztowy <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_postal_code') is-invalid @enderror" 
                                               id="buyer_postal_code" name="buyer_postal_code" 
                                               value="{{ old('buyer_postal_code', $zamowienie->buyer_postal_code) }}" required>
                                        @error('buyer_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="buyer_city" class="form-label">Miasto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_city') is-invalid @enderror" 
                                               id="buyer_city" name="buyer_city" 
                                               value="{{ old('buyer_city', $zamowienie->buyer_city) }}" required>
                                        @error('buyer_city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="buyer_address" class="form-label">Adres <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_address') is-invalid @enderror" 
                                               id="buyer_address" name="buyer_address" 
                                               value="{{ old('buyer_address', $zamowienie->buyer_address) }}" required>
                                        @error('buyer_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="buyer_nip" class="form-label">NIP</label>
                                        <input type="text" class="form-control @error('buyer_nip') is-invalid @enderror" 
                                               id="buyer_nip" name="buyer_nip" 
                                               value="{{ old('buyer_nip', $zamowienie->buyer_nip) }}">
                                        @error('buyer_nip')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ODBIORCA --}}
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-geo-alt"></i> ODBIORCA
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="recipient_name" class="form-label">Nazwa / Imię i nazwisko</label>
                                        <input type="text" class="form-control @error('recipient_name') is-invalid @enderror" 
                                               id="recipient_name" name="recipient_name" 
                                               value="{{ old('recipient_name', $zamowienie->recipient_name) }}">
                                        @error('recipient_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <label for="recipient_postal_code" class="form-label">Kod pocztowy</label>
                                        <input type="text" class="form-control @error('recipient_postal_code') is-invalid @enderror" 
                                               id="recipient_postal_code" name="recipient_postal_code" 
                                               value="{{ old('recipient_postal_code', $zamowienie->recipient_postal_code) }}">
                                        @error('recipient_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="recipient_city" class="form-label">Miasto</label>
                                        <input type="text" class="form-control @error('recipient_city') is-invalid @enderror" 
                                               id="recipient_city" name="recipient_city" 
                                               value="{{ old('recipient_city', $zamowienie->recipient_city) }}">
                                        @error('recipient_city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="recipient_address" class="form-label">Adres</label>
                                        <input type="text" class="form-control @error('recipient_address') is-invalid @enderror" 
                                               id="recipient_address" name="recipient_address" 
                                               value="{{ old('recipient_address', $zamowienie->recipient_address) }}">
                                        @error('recipient_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="recipient_nip" class="form-label">NIP (opcjonalnie) - proszę podać tylko jeżeli ma znaleźć się na fakturze</label>
                                        <input type="text" class="form-control @error('recipient_nip') is-invalid @enderror" 
                                               id="recipient_nip" name="recipient_nip" 
                                               value="{{ old('recipient_nip', $zamowienie->recipient_nip) }}">
                                        @error('recipient_nip')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- UWAGI DO FAKTURY --}}
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-receipt"></i> UWAGI DO FAKTURY
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="invoice_notes" class="form-label">Uwagi do faktury</label>
                                        <textarea class="form-control @error('invoice_notes') is-invalid @enderror" 
                                                  id="invoice_notes" name="invoice_notes" rows="3">{{ old('invoice_notes', $zamowienie->invoice_notes) }}</textarea>
                                        @error('invoice_notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label for="invoice_payment_delay" class="form-label">Proszę o wystawienie faktury z odroczonym terminem płatności</label>
                                        <div class="input-group">
                                            <input type="number" min="0" max="31" class="form-control @error('invoice_payment_delay') is-invalid @enderror" 
                                                   id="invoice_payment_delay" name="invoice_payment_delay" 
                                                   value="{{ old('invoice_payment_delay', $zamowienie->invoice_payment_delay ?? 14) }}">
                                            <span class="input-group-text">dni</span>
                                        </div>
                                        @error('invoice_payment_delay')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Zakres: 0-31 dni</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Faktura --}}
                        <div class="card mb-4">
                            <div class="card-header" style="background-color: #17a2b8; color: white;">
                                <h6 class="mb-0">
                                    <i class="bi bi-receipt"></i> Faktura
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">Numer faktury</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="invoice_number" 
                                               name="invoice_number" 
                                               value="{{ old('invoice_number', $zamowienie->invoice_number) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Status i notatki --}}
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-clipboard-check"></i> Status i notatki
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="status_completed" 
                                                   name="status_completed" 
                                                   value="1" 
                                                   {{ old('status_completed', $zamowienie->status_completed) == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label" for="status_completed">
                                                <strong>Zamówienie zakończone</strong>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="notes" class="form-label">Notatki wewnętrzne</label>
                                        <textarea class="form-control" 
                                                  id="notes" 
                                                  name="notes" 
                                                  rows="4" 
                                                  placeholder="Dodaj notatki...">{{ old('notes', $zamowienie->notes) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Przyciski akcji --}}
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('form-orders.show', array_merge(['id' => $zamowienie->id], array_filter(['filter_new' => request('filter_new'), 'course_id' => request('course_id')]))) }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Anuluj
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Zapisz zmiany
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>



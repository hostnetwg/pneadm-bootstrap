<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj nowe zamówienie') }} <span class="text-danger">(PNEADM)</span>
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
                    <li class="breadcrumb-item active" aria-current="page">
                        Dodaj nowe zamówienie
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

            {{-- Formularz --}}
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-plus-circle"></i> Nowe zamówienie
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('form-orders.store') }}" method="POST">
                        @csrf
                        
                        {{-- SZKOLENIE --}}
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar-event"></i> SZKOLENIE
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label for="course_id" class="form-label">Wybierz szkolenie <span class="text-danger">*</span></label>
                                        <select class="form-control @error('course_id') is-invalid @enderror" 
                                                id="course_id" name="course_id" required>
                                            <option value="">-- Wybierz szkolenie --</option>
                                            @foreach($courses as $course)
                                                <option value="{{ $course->id }}" 
                                                        {{ old('course_id') == $course->id ? 'selected' : '' }}
                                                        data-title="{{ $course->title }}"
                                                        data-description="{{ $course->description }}"
                                                        data-start-date="{{ $course->start_date->format('Y-m-d') }}">
                                                    {{ $course->id_old }} - {!! $course->title !!} 
                                                    @if($course->start_date)
                                                        [{{ $course->start_date->format('Y-m-d') }}]
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('course_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="product_price" class="form-label">Cena (PLN)</label>
                                        <input type="number" step="0.01" min="0" class="form-control @error('product_price') is-invalid @enderror" 
                                               id="product_price" name="product_price" 
                                               value="{{ old('product_price') }}" 
                                               placeholder="Opcjonalnie">
                                        @error('product_price')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div id="course-info" class="alert alert-light" style="display: none;">
                                            <h6 class="mb-2">Informacje o wybranym szkoleniu:</h6>
                                            <div id="course-details"></div>
                                        </div>
                                    </div>
                                </div>
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
                                               value="{{ old('participant_firstname') }}" required>
                                        @error('participant_firstname')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="participant_lastname" class="form-label">Nazwisko <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('participant_lastname') is-invalid @enderror" 
                                               id="participant_lastname" name="participant_lastname" 
                                               value="{{ old('participant_lastname') }}" required>
                                        @error('participant_lastname')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="participant_email" class="form-label">Email uczestnika - tu zostaną wysłane dane dostępowe do szkolenia <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control @error('participant_email') is-invalid @enderror" 
                                               id="participant_email" name="participant_email" 
                                               value="{{ old('participant_email') }}" required>
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
                                               value="{{ old('orderer_name') }}" required>
                                        @error('orderer_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_phone" class="form-label">Telefon kontaktowy <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control @error('orderer_phone') is-invalid @enderror" 
                                               id="orderer_phone" name="orderer_phone" 
                                               value="{{ old('orderer_phone') }}" required>
                                        @error('orderer_phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orderer_email" class="form-label">E-mail - tu prześlemy fakturę <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control @error('orderer_email') is-invalid @enderror" 
                                               id="orderer_email" name="orderer_email" 
                                               value="{{ old('orderer_email') }}" required>
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
                                               value="{{ old('buyer_name') }}" required>
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
                                               value="{{ old('buyer_postal_code') }}" required>
                                        @error('buyer_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="buyer_city" class="form-label">Miasto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('buyer_city') is-invalid @enderror" 
                                               id="buyer_city" name="buyer_city" 
                                               value="{{ old('buyer_city') }}" required>
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
                                               value="{{ old('buyer_address') }}" required>
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
                                               value="{{ old('buyer_nip') }}">
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
                                    <i class="bi bi-geo-alt"></i> ODBIORCA (opcjonalnie, jeśli inny niż nabywca)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="recipient_name" class="form-label">Nazwa / Imię i nazwisko</label>
                                        <input type="text" class="form-control @error('recipient_name') is-invalid @enderror" 
                                               id="recipient_name" name="recipient_name" 
                                               value="{{ old('recipient_name') }}">
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
                                               value="{{ old('recipient_postal_code') }}">
                                        @error('recipient_postal_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-9">
                                        <label for="recipient_city" class="form-label">Miasto</label>
                                        <input type="text" class="form-control @error('recipient_city') is-invalid @enderror" 
                                               id="recipient_city" name="recipient_city" 
                                               value="{{ old('recipient_city') }}">
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
                                               value="{{ old('recipient_address') }}">
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
                                               value="{{ old('recipient_nip') }}">
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
                                                  id="invoice_notes" name="invoice_notes" rows="3">{{ old('invoice_notes') }}</textarea>
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
                                                   value="{{ old('invoice_payment_delay', 14) }}">
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

                        {{-- NOTATKI --}}
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="bi bi-sticky"></i> NOTATKI
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="notes" class="form-label">Dodatkowe notatki</label>
                                        <textarea class="form-control @error('notes') is-invalid @enderror" 
                                                  id="notes" name="notes" rows="3" 
                                                  placeholder="Dodaj notatki dotyczące zamówienia...">{{ old('notes') }}</textarea>
                                        @error('notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- PRZYCISKI --}}
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('form-orders.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Anuluj
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Utwórz zamówienie
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript dla wyświetlania informacji o kursie --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('course_id');
            const courseInfo = document.getElementById('course-info');
            const courseDetails = document.getElementById('course-details');

            courseSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                if (this.value) {
                    const title = selectedOption.getAttribute('data-title');
                    const description = selectedOption.getAttribute('data-description');
                    const startDate = selectedOption.getAttribute('data-start-date');
                    
                    courseDetails.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Tytuł:</strong> ${title}
                            </div>
                            <div class="col-md-6">
                                <strong>Data rozpoczęcia:</strong> [${startDate}]
                            </div>
                        </div>
                        ${description ? `
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Opis:</strong><br>
                                    <small class="text-muted">${description}</small>
                                </div>
                            </div>
                        ` : ''}
                    `;
                    courseInfo.style.display = 'block';
                } else {
                    courseInfo.style.display = 'none';
                }
            });

            // Wyświetl informacje jeśli kurs jest już wybrany (przy błędach walidacji)
            if (courseSelect.value) {
                courseSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</x-app-layout>

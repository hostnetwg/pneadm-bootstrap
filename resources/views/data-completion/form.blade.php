<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uzupełnienie danych - {{ config('app.name') }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding: 2rem 0;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <!-- Logo i dane kontaktowe -->
            <div class="text-center mb-4">
                @php
                    $logoPath = 'certificates/logos/1759876024_logo-pne-czarne.png';
                    $logoFile = storage_path('app/public/' . $logoPath);
                    if (file_exists($logoFile)) {
                        $logoSrc = asset('storage/' . $logoPath);
                    } else {
                        // Fallback do logo z public/images jeśli nie ma w storage
                        $logoSrc = asset('images/logo.png');
                    }
                @endphp
                <img src="{{ $logoSrc }}" alt="Logo PNE" style="max-width: 200px; height: auto; margin-bottom: 20px;">
                
                <div class="contact-info" style="color: #333; font-size: 0.95rem; line-height: 1.5;">
                    <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli</strong><br>
                    <em>"Platforma Nowoczesnej Edukacji"</em><br>
                    ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>
                    kontakt: <a href="mailto:kontakt@nowoczesna-edukacja.pl" style="color: #0d6efd;">kontakt@nowoczesna-edukacja.pl</a>, tel. <a href="tel:501654274" style="color: #0d6efd;">501 654 274</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>
                        Uzupełnienie danych do rejestru zaświadczeń
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Prosimy o uzupełnienie poniższych danych, które są wymagane do prowadzenia rejestru wydanych zaświadczeń.
                    </p>

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('data-completion.form.store', $token->token) }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Adres email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   value="{{ $email }}" 
                                   disabled>
                            <div class="form-text">Email nie może być zmieniony</div>
                        </div>

                        <div class="mb-3">
                            <label for="birth_date" class="form-label">
                                Data urodzenia <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control @error('birth_date') is-invalid @enderror" 
                                   id="birth_date" 
                                   name="birth_date" 
                                   placeholder="DD-MM-RRRR (np. 15-03-1985)"
                                   value="{{ old('birth_date') }}"
                                   maxlength="10"
                                   required>
                            <div class="form-text">Format: DD-MM-RRRR (np. 15-03-1985)</div>
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="birth_place" class="form-label">
                                Miejsce urodzenia <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control @error('birth_place') is-invalid @enderror" 
                                   id="birth_place" 
                                   name="birth_place" 
                                   placeholder="np. Warszawa"
                                   value="{{ old('birth_place') }}"
                                   maxlength="255"
                                   required>
                            @error('birth_place')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check me-2"></i>
                                Zapisz dane
                            </button>
                        </div>
                    </form>

                    @if(isset($courses) && $courses->count() > 0)
                        <div class="alert alert-warning mt-4" style="border-left: 4px solid #ffc107;">
                            <strong>Ważne:</strong> Po uzupełnieniu danych, zostaną one automatycznie zaktualizowane 
                            we wszystkich zaświadczeniach dotyczących szkoleń wymienionych poniżej.
                        </div>

                        <div class="mt-3 pt-3 border-top">
                            <h6 class="mb-3">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Szkolenia, w których brałeś/aś udział:
                            </h6>
                            <div class="list-group">
                                @foreach($courses as $course)
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ str_replace('&nbsp;', ' ', $course->title) }}</h6>
                                        </div>
                                        <div class="text-muted small">
                                            @if($course->start_date)
                                                <i class="fas fa-calendar me-1"></i>
                                                {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}
                                            @endif
                                            @if($course->instructor)
                                                @if($course->start_date) | @endif
                                                <i class="fas fa-user me-1"></i>
                                                {{ $course->instructor->full_name ?? ($course->instructor->first_name . ' ' . $course->instructor->last_name) }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Informacje RODO -->
            <div class="card mt-3" style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                <div class="card-body" style="padding: 1rem;">
                    <h6 class="mb-2" style="font-size: 0.9rem; font-weight: bold;">Informacja o przetwarzaniu danych osobowych (RODO)</h6>
                    <div style="font-size: 0.8rem; color: #6c757d; line-height: 1.5;">
                        <p class="mb-2">
                            <strong>Administrator danych:</strong> Niepubliczny Ośrodek Doskonalenia Nauczycieli "Platforma Nowoczesnej Edukacji", 
                            ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń
                        </p>
                        <p class="mb-2">
                            <strong>Cel przetwarzania:</strong> Prowadzenie rejestru wydanych zaświadczeń zgodnie z przepisami prawa oświatowego.
                        </p>
                        <p class="mb-2">
                            <strong>Podstawa prawna:</strong> Art. 6 ust. 1 lit. c RODO (wypełnienie obowiązku prawnego ciążącego na administratorze) 
                            oraz przepisy prawa oświatowego dotyczące prowadzenia dokumentacji szkoleń.
                        </p>
                        <p class="mb-2">
                            <strong>Okres przechowywania:</strong> Dane będą przechowywane przez okres wymagany przepisami prawa dotyczącymi 
                            dokumentacji szkoleń i wydanych zaświadczeń.
                        </p>
                        <p class="mb-2">
                            <strong>Prawa osoby, której dane dotyczą:</strong> Masz prawo do dostępu do swoich danych, ich sprostowania, 
                            usunięcia lub ograniczenia przetwarzania, a także prawo do wniesienia sprzeciwu wobec przetwarzania oraz prawo 
                            do przenoszenia danych. Masz również prawo do wniesienia skargi do organu nadzorczego (UODO).
                        </p>
                        <p class="mb-0">
                            <strong>Kontakt w sprawach RODO:</strong> 
                            <a href="mailto:kontakt@nowoczesna-edukacja.pl" style="color: #0d6efd;">kontakt@nowoczesna-edukacja.pl</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">
                    {{ config('app.name') }} &copy; {{ date('Y') }}
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Formatowanie daty -->
    <script>
        document.getElementById('birth_date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '-' + value.substring(2);
            }
            if (value.length >= 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 9);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>


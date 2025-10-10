<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    
    <!-- Zabezpieczenia przed indeksowaniem -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="googlebot" content="noindex, nofollow">
    <meta name="bingbot" content="noindex, nofollow">
    <meta name="slurp" content="noindex, nofollow">
    <meta name="duckduckbot" content="noindex, nofollow">
    
    <!-- Dodatkowe zabezpieczenia -->
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="X-Robots-Tag" content="noindex, nofollow">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stats-counter {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
        }
        .floating-animation {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Hero Section -->
    <div class="hero-gradient text-white py-5">
        <div class="container">
            <div class="row align-items-center min-vh-50">
                <div class="col-lg-6">
                    <div class="floating-animation">
                        <h1 class="display-3 fw-bold mb-4">
                            <i class="bi bi-mortarboard-fill me-3"></i>
                            {{ config('app.name') }}
                        </h1>
                        <p class="lead mb-4">
                            Profesjonalna platforma do zarządzania szkoleniami, certyfikatami i zamówieniami. 
                            Wszystko w jednym miejscu.
                        </p>
                        <div class="d-flex gap-3 flex-wrap">
                            @if (Route::has('login'))
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="btn btn-light btn-lg px-4">
                                        <i class="bi bi-speedometer2 me-2"></i>
                                        Przejdź do Dashboard
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="btn btn-light btn-lg px-4">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>
                                        Zaloguj się
                                    </a>
                                @endauth
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="floating-animation" style="animation-delay: 1s;">
                        <i class="bi bi-laptop display-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container py-5">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="display-5 fw-bold text-dark mb-3">Nasze możliwości</h2>
                <p class="lead text-muted">Kompleksowe rozwiązanie dla nowoczesnej edukacji</p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-lg-4 col-md-6">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-book text-primary fs-1"></i>
                        </div>
                        <h4 class="card-title fw-bold">Zarządzanie szkoleniami</h4>
                        <p class="card-text text-muted">
                            Twórz, edytuj i zarządzaj szkoleniami online i stacjonarnymi. 
                            Pełna kontrola nad harmonogramem i uczestnikami.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-award text-success fs-1"></i>
                        </div>
                        <h4 class="card-title fw-bold">Certyfikaty</h4>
                        <p class="card-text text-muted">
                            Automatyczne generowanie certyfikatów ukończenia szkoleń. 
                            Profesjonalne szablony i personalizacja.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-cart-check text-info fs-1"></i>
                        </div>
                        <h4 class="card-title fw-bold">Zamówienia</h4>
                        <p class="card-text text-muted">
                            Integracja z Publigo.pl, zarządzanie zamówieniami online 
                            i formularzami z odroczonym terminem płatności.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-people text-warning fs-1"></i>
                        </div>
                        <h4 class="card-title fw-bold">Uczestnicy</h4>
                        <p class="card-text text-muted">
                            Zarządzaj listą uczestników, śledź postępy i 
                            utrzymuj kontakt z kursantami.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-graph-up text-danger fs-1"></i>
                        </div>
                        <h4 class="card-title fw-bold">Raporty</h4>
                        <p class="card-text text-muted">
                            Szczegółowe raporty sprzedaży, statystyki zamówień 
                            i analiza popularności szkoleń.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-secondary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-shield-check text-secondary fs-1"></i>
                        </div>
                        <h4 class="card-title fw-bold">Bezpieczeństwo</h4>
                        <p class="card-text text-muted">
                            Zaawansowane zabezpieczenia, kontrola dostępu 
                            i szyfrowanie danych.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="stats-counter">500+</div>
                                <p class="text-muted mb-0">Zrealizowanych szkoleń</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-counter">1000+</div>
                                <p class="text-muted mb-0">Zadowolonych uczestników</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-counter">50+</div>
                                <p class="text-muted mb-0">Wykwalifikowanych instruktorów</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-counter">99%</div>
                                <p class="text-muted mb-0">Pozytywnych opinii</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm hero-gradient text-white">
                    <div class="card-body text-center p-5">
                        <h3 class="display-6 fw-bold mb-3">
                            <i class="bi bi-rocket-takeoff me-3"></i>
                            Gotowy na start?
                        </h3>
                        <p class="lead mb-4">
                            Dołącz do tysięcy zadowolonych użytkowników i rozpocznij zarządzanie 
                            swoimi szkoleniami już dziś!
                        </p>
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="btn btn-light btn-lg px-5">
                                    <i class="bi bi-speedometer2 me-2"></i>
                                    Przejdź do Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-light btn-lg px-5">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Zaloguj się teraz
                                </a>
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="bi bi-c-circle me-1"></i>
                        {{ date('Y') }} {{ config('app.name') }}. Wszystkie prawa zastrzeżone.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        Powered by Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
                    </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

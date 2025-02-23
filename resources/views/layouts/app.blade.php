<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/sidebars.css') }}">

    <style>
        body {
            overflow: hidden; /* Blokujemy przewijanie na poziomie body */
            height: 100vh; /* Pełna wysokość viewport */
        }
        
        .sidebar-offset {
            margin-left: 16.666667%;
            height: 100vh; /* Pełna wysokość viewport */
            overflow: hidden; /* Blokujemy przewijanie na tym poziomie */
        }
        
        @media (max-width: 768px) {
            .sidebar-offset {
                margin-left: 0;
            }
        }
        
        .main-content {
            overflow-y: auto; /* Tylko ten element będzie przewijany */
            height: calc(100vh - 70px); /* Wysokość viewport minus wysokość nagłówka */
            padding-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid g-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <aside class="col-md-2 bg-dark text-light p-1 position-fixed h-100" style="z-index: 1000;">
                @include('layouts.navigation')
            </aside>

            <!-- Main Content -->
            <div class="col-md-10 sidebar-offset">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow-sm">
                        <div class="py-4 px-4">
                            <h1 class="mb-0 fs-3 fw-bold text-primary">{{ $header }}</h1>
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="py-2 px-2 main-content">
                    <div class="bg-white rounded shadow-sm p-4">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </div>  
    <script src="{{ asset('js/sidebars.js') }}"></script>
</body>
</html>

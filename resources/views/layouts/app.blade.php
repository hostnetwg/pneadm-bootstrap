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
            overflow: hidden;
            height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1030;
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar-offset {
            margin-left: 280px;
            height: 100vh;
            overflow: hidden;
            transition: margin-left 0.3s ease-in-out;
        }
        
        .main-content {
            overflow-y: auto;
            height: calc(100vh - 70px);
            padding-bottom: 2rem;
        }
        
        #sidebar-toggle {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1040;
            background-color: #343a40;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar-offset {
                margin-left: 0;
            }
            
            #sidebar-toggle {
                display: block;
            }
            
            .main-content {
                height: calc(100vh - 60px);
            }
            
            /* Dodajemy overlay przy otwartym menu */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1025;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-light">
    <button id="sidebar-toggle" class="text-light">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
        </svg>
    </button>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="container-fluid g-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <aside class="col-md-2 bg-dark text-light p-1 sidebar" id="sidebar">
                @include('layouts.navigation')
            </aside>

            <!-- Main Content -->
            <div class="col-md-10 sidebar-offset" id="main-content">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow-sm">
                        <div class="py-3 px-4">
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            // Funkcja do przełączania widoczności sidebara
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
            
            // Obsługa kliknięcia przycisku toggle
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Zamykanie sidebara po kliknięciu na overlay
            overlay.addEventListener('click', function() {
                toggleSidebar();
            });
            
            // Zamykanie sidebara po kliknięciu linku w menu (tylko na mobilnych)
            if (window.innerWidth <= 768) {
                const menuLinks = document.querySelectorAll('.sidebar a');
                menuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        setTimeout(function() {
                            toggleSidebar();
                        }, 150); // Małe opóźnienie, żeby link zdążył zadziałać
                    });
                });
            }
            
            // Dostosowanie przy zmianie rozmiaru okna
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
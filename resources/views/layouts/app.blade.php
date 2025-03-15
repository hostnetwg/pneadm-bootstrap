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
        
        /* Przycisk do zwijania/rozwijania menu */
        .sidebar-collapse-btn {
            position: absolute;
            top: 50%;
            right: -16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #343a40;
            border: 2px solid #6c757d;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 1031;
            transition: right 0.3s ease-in-out;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
        }
        
        .sidebar-collapse-btn .collapse-icon {
            transition: transform 0.3s;
        }
        
        /* Stan z schowanym menu (dla wszystkich rozdzielczości) */
        body.sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }
        
        body.sidebar-collapsed .sidebar-offset {
            margin-left: 0;
        }
        
        body.sidebar-collapsed .sidebar-collapse-btn {
            right: -50px; /* Przesuń poza widok, pojawia się nowa ikona w lewym górnym rogu */
        }
        
        /* Ikona do rozwijania menu (w lewym górnym rogu) */
        #sidebar-toggle {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1040;
            background-color: #343a40;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }
        
        body.sidebar-collapsed #sidebar-toggle {
            opacity: 1;
            pointer-events: auto;
        }
        
        /* Widok mobilny */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            body.sidebar-expanded .sidebar {
                transform: translateX(0);
            }
            
            .sidebar-offset {
                margin-left: 0;
            }
            
            .main-content {
                height: calc(100vh - 60px);
            }
            
            /* Pokazuj przycisk w lewym górnym rogu na mobilnych */
            #sidebar-toggle {
                opacity: 1;
                pointer-events: auto;
            }
            
            /* Ukryj przycisk na boku sidebara na mobilnych */
            .sidebar-collapse-btn {
                display: none;
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
            
            body.sidebar-expanded .sidebar-overlay {
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
                <!-- Przycisk do zwijania menu -->
                <div class="sidebar-collapse-btn" id="sidebar-collapse-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-left collapse-icon" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                    </svg>
                </div>
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
            const sidebarCollapseBtn = document.getElementById('sidebar-collapse-btn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const collapseIcon = document.querySelector('.collapse-icon');
            const body = document.body;
            
            // Sprawdź, czy w localStorage jest zapisany stan menu
            const savedState = localStorage.getItem('sidebarState');
            if (savedState === 'collapsed') {
                body.classList.add('sidebar-collapsed');
            } else if (window.innerWidth <= 768) {
                // Na mobilnych domyślnie schowane
                body.classList.remove('sidebar-expanded');
            } else {
                // Na desktopach domyślnie rozwinięte (jeśli nie zapisano inaczej)
                body.classList.remove('sidebar-collapsed');
            }
            
            // Funkcja do przełączania widoczności sidebara
            function toggleSidebar() {
                if (window.innerWidth <= 768) {
                    body.classList.toggle('sidebar-expanded');
                } else {
                    body.classList.toggle('sidebar-collapsed');
                }
                
                // Zapisz stan menu w localStorage
                if ((window.innerWidth <= 768 && body.classList.contains('sidebar-expanded')) || 
                    (window.innerWidth > 768 && !body.classList.contains('sidebar-collapsed'))) {
                    localStorage.setItem('sidebarState', 'expanded');
                } else {
                    localStorage.setItem('sidebarState', 'collapsed');
                }
            }
            
            // Obsługa kliknięcia przycisku toggle w lewym górnym rogu (mobilne)
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Obsługa kliknięcia przycisku na boku sidebara (desktop)
            sidebarCollapseBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Zamykanie sidebara po kliknięciu na overlay (tylko na mobilnych)
            overlay.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    body.classList.remove('sidebar-expanded');
                }
            });
            
            // Zamykanie sidebara po kliknięciu linku w menu (tylko na mobilnych)
            if (window.innerWidth <= 768) {
                const menuLinks = document.querySelectorAll('.sidebar a');
                menuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        setTimeout(function() {
                            body.classList.remove('sidebar-expanded');
                        }, 150); // Małe opóźnienie, żeby link zdążył zadziałać
                    });
                });
            }
            
            // Dostosowanie przy zmianie rozmiaru okna
            window.addEventListener('resize', function() {
                // Reset klas przy zmianie z mobile->desktop lub odwrotnie
                if (window.innerWidth <= 768) {
                    body.classList.remove('sidebar-collapsed');
                    if (localStorage.getItem('sidebarState') === 'collapsed') {
                        body.classList.remove('sidebar-expanded');
                    } else {
                        body.classList.add('sidebar-expanded');
                    }
                } else {
                    body.classList.remove('sidebar-expanded');
                    if (localStorage.getItem('sidebarState') === 'collapsed') {
                        body.classList.add('sidebar-collapsed');
                    } else {
                        body.classList.remove('sidebar-collapsed');
                    }
                }
            });
        });
    </script>
</body>
</html>
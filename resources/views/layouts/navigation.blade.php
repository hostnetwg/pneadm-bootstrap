<div class="flex-shrink-0 p-3 text-bg-dark h-100 overflow-auto" style="width: 100%;" data-bs-theme="dark">
    <div class="d-flex align-items-center pb-3 mb-3 link-light text-decoration-none border-bottom">
        <svg class="bi pe-none me-2" width="30" height="24" fill="white">
            <use xlink:href="#bootstrap"></use>
        </svg>
        <span class="fs-5 fw-semibold">Panel Administracyjny</span>
        
        <!-- Dodajemy przycisk zamykania na mobilnych -->
        <button id="close-sidebar" class="btn btn-link text-light ms-auto d-md-none">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </button>
    </div>
    <ul class="list-unstyled ps-0">

        <!-- Dashboard -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('dashboard') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#dashboard-collapse"
                    aria-expanded="{{ request()->routeIs('dashboard') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#home"></use>
                </svg>
                Dashboard
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('dashboard') ? 'show' : '' }}" id="dashboard-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li>
                        <a href="{{ route('dashboard') }}" class="link-light d-inline-flex text-decoration-none rounded"
                           onclick="event.stopPropagation();">Przegląd</a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- Szkolenia -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('courses.*') || request()->routeIs('participants.*') || request()->routeIs('education.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#courses-collapse"
                    aria-expanded="{{ request()->routeIs('courses.*') || request()->routeIs('participants.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#speedometer2"></use>
                </svg>
                Szkolenia
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('courses.*') || request()->routeIs('participants.*') ? 'show' : '' }}" id="courses-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="{{ route('courses.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Harmonogram szkoleń</a></li>
                    <li><a href="{{ route('courses.instructors.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Instruktorzy</a></li>
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Zaświadczenia</a></li>
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Uczestnicy</a></li>
                </ul>
            </div>
        </li>

        <!-- Marketing i reklama -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('marketing.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#marketing-collapse"
                    aria-expanded="{{ request()->routeIs('marketing.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#table"></use>
                </svg>
                Marketing i reklama
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('marketing.*') ? 'show' : '' }}" id="marketing-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Działania marketingowe</a></li>
                </ul>
            </div>
        </li>

        <!-- Archiwum -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('archiwum.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#archiwum-collapse"
                    aria-expanded="{{ request()->routeIs('archiwum.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#table"></use>
                </svg>
                Archiwum i import
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('education.index*') || request()->routeIs('archiwum.certgen_szkolenia.index*') ? 'show' : '' }}" id="archiwum-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="{{ route('education.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Webinary TIK BD:Certgen</a></li>
                    <li>
                        <a href="{{ route('archiwum.certgen_szkolenia.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           NODN - Lista szkoleń
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('archiwum.certgen_publigo.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Certgen - PUBLIGO
                        </a>
                    </li> 
                </ul>             
            </div>
        </li>

        <!-- Sprzedaż -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('sales.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#sales-collapse"
                    aria-expanded="{{ request()->routeIs('sales.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#speedometer2"></use>
                </svg>
                Sprzedaż
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('sales.*') ? 'show' : '' }}" id="sales-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Nowe</a></li>
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Przetworzone</a></li>
                </ul>
            </div>
        </li>

        <li class="border-top my-3"></li>

        <!-- Konto -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('profile.edit') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#account-collapse"
                    aria-expanded="{{ request()->routeIs('profile.edit') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#people-circle"></use>
                </svg>
                Konto
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('profile.edit') ? 'show' : '' }}" id="account-collapse">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li>
                        <a href="{{ route('profile.edit') }}"
                           class="link-light d-inline-flex text-decoration-none rounded"
                           onclick="event.stopPropagation();">
                           Edytuj Profil
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('logout') }}"
                           class="link-light d-inline-flex text-decoration-none rounded"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                           Wyloguj
                        </a>
                    </li>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </ul>
            </div>
        </li>
    </ul>
</div>

<script>
    // Dodajemy obsługę przycisku zamykania sidebara na mobilnych
    document.addEventListener('DOMContentLoaded', function() {
        const closeButton = document.getElementById('close-sidebar');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    });
</script>
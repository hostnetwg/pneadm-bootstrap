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
    <ul class="list-unstyled ps-0" id="menuAccordion">

        <!-- Dashboard -->
        <li class="mb-1">
            <a href="{{ route('dashboard') }}" class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#home"></use>
                </svg>
                Dashboard
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </a>
        </li>

        <!-- Szkolenia -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('courses.*') || request()->routeIs('participants.*') || request()->routeIs('surveys.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#courses-collapse"
                    aria-expanded="{{ request()->routeIs('courses.*') || request()->routeIs('participants.*') || request()->routeIs('surveys.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#speedometer2"></use>
                </svg>
                Szkolenia
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('courses.*') || request()->routeIs('participants.*') || request()->routeIs('surveys.*') ? 'show' : '' }}" id="courses-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="{{ route('courses.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Harmonogram szkoleń</a></li>
                    <li><a href="{{ route('courses.instructors.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Instruktorzy</a></li>
                    <li><a href="{{ route('surveys.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Ankiety</a></li>
                </ul>
            </div>
        </li>

        <li class="border-top my-3"></li>


        <!-- Sprzedaż -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('sales.*') || request()->routeIs('certgen.zamowienia.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#sales-collapse"
                    aria-expanded="{{ request()->routeIs('sales.*') || request()->routeIs('certgen.zamowienia.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#cart3"></use>
                </svg>
                Sprzedaż
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('sales.*') || request()->routeIs('certgen.zamowienia.*') || request()->routeIs('form-orders.*') ? 'show' : '' }}" id="sales-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="{{ route('form-orders.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Zamówienia FORM (pneadm)</a></li>
                    <li><a href="{{ route('sales.index') }}" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Zamówienia FORM (certgen)</a></li>
                    <li><a href="{{ route('certgen.zamowienia.index') }}" class="link-light d-inline-flex text-decoration-none rounded">Zakupy NE.pl</a></li>
                </ul>
            </div>
        </li>
        <!-- Marketing i reklama -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('marketing.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#marketing-collapse"
                    aria-expanded="{{ request()->routeIs('marketing.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#bullseye"></use>
                </svg>
                Marketing i reklama
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('marketing.*') ? 'show' : '' }}" id="marketing-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">Działania marketingowe</a></li>
                </ul>
            </div>
        </li>

        <!-- Baza Certgen -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('education.*') || request()->routeIs('certgen.webhook_data.*') || request()->routeIs('archiwum.certgen_szkolenia.*') || request()->routeIs('archiwum.certgen_publigo.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#certgen-collapse"
                    aria-expanded="{{ request()->routeIs('education.*') || request()->routeIs('certgen.webhook_data.*') || request()->routeIs('archiwum.certgen_szkolenia.*') || request()->routeIs('archiwum.certgen_publigo.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white"><use xlink:href="#grid"></use></svg>
                Baza certgen
                <svg class="bi pe-none ms-auto" width="16" height="16"><use xlink:href="#chevron-right"></use></svg>
            </button>
            <div class="collapse {{ request()->routeIs('education.*') || request()->routeIs('certgen.webhook_data.*') || request()->routeIs('archiwum.certgen_szkolenia.*') || request()->routeIs('archiwum.certgen_publigo.*') ? 'show' : '' }}" id="certgen-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="{{ route('education.index') }}" class="link-light d-inline-flex text-decoration-none rounded">Webinary TIK BD:Certgen</a></li>
                    <li>
                        <a href="{{ route('archiwum.certgen_szkolenia.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           NODN - Lista szkoleń
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('archiwum.certgen_publigo.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Archiwum Szkoleń PUBLIGO
                        </a>
                    </li> 
                    <li><a href="{{ route('certgen.webhook_data.index') }}" class="link-light d-inline-flex text-decoration-none rounded">Dane dla webhook</a></li>
                </ul>
            </div>
        </li>
        <li class="border-top my-3"></li>
        <!-- Publigo NE.pl -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('publigo.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#publigo-ne-collapse"
                    aria-expanded="{{ request()->routeIs('publigo.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white"><use xlink:href="#cloud-arrow-down"></use></svg>
                Publigo NE.pl
                <svg class="bi pe-none ms-auto" width="16" height="16"><use xlink:href="#chevron-right"></use></svg>
            </button>
            <div class="collapse {{ request()->routeIs('publigo.*') ? 'show' : '' }}" id="publigo-ne-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li>
                        <a href="{{ route('publigo.products.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Produkty (API)
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('publigo.webhooks') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Webhooki
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('publigo.test-api') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Test API
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- Sendy -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('sendy.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#sendy-collapse"
                    aria-expanded="{{ request()->routeIs('sendy.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white"><use xlink:href="#envelope"></use></svg>
                Sendy
                <svg class="bi pe-none ms-auto" width="16" height="16"><use xlink:href="#chevron-right"></use></svg>
            </button>
            <div class="collapse {{ request()->routeIs('sendy.*') ? 'show' : '' }}" id="sendy-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li>
                        <a href="{{ route('sendy.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Listy
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- ClickMeeting -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('clickmeeting.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#clickmeeting-collapse"
                    aria-expanded="{{ request()->routeIs('clickmeeting.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white"><use xlink:href="#camera-video"></use></svg>
                ClickMeeting
                <svg class="bi pe-none ms-auto" width="16" height="16"><use xlink:href="#chevron-right"></use></svg>
            </button>
            <div class="collapse {{ request()->routeIs('clickmeeting.*') ? 'show' : '' }}" id="clickmeeting-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li>
                        <a href="{{ route('clickmeeting.trainings.index') }}" class="link-light d-inline-flex text-decoration-none rounded">
                           Szkolenia
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="border-top my-3"></li>
        
        <!-- Ustawienia -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('settings.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#settings-collapse"
                    aria-expanded="{{ request()->routeIs('settings.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#sliders"></use>
                </svg>
                Ustawienia
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('settings.*') ? 'show' : '' }}" id="settings-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="#" class="link-light d-inline-flex text-decoration-none rounded" onclick="event.stopPropagation();">AI</a></li>
                </ul>
            </div>
        </li>
        
        <!-- Admin -->
        <li class="mb-1">
            <button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 text-light {{ request()->routeIs('admin.*') ? '' : 'collapsed' }}"
                    data-bs-toggle="collapse" data-bs-target="#admin-collapse"
                    aria-expanded="{{ request()->routeIs('admin.*') ? 'true' : 'false' }}">
                <svg class="bi pe-none me-2" width="16" height="16" fill="white">
                    <use xlink:href="#gear"></use>
                </svg>
                Admin
                <svg class="bi pe-none ms-auto" width="16" height="16">
                    <use xlink:href="#chevron-right"></use>
                </svg>
            </button>
            <div class="collapse {{ request()->routeIs('admin.*') ? 'show' : '' }}" id="admin-collapse" data-bs-parent="#menuAccordion">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                    <li><a href="{{ route('admin.users.index') }}" class="link-light d-inline-flex text-decoration-none rounded">Użytkownicy</a></li>
                    <li><a href="{{ route('admin.certificate-templates.index') }}" class="link-light d-inline-flex text-decoration-none rounded">Szablony Certyfikatów</a></li>
                </ul>
            </div>
        </li>

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
            <div class="collapse {{ request()->routeIs('profile.edit') ? 'show' : '' }}" id="account-collapse" data-bs-parent="#menuAccordion">
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
<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            Edytuj szkolenie: {!! $course->title !!}
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <h1>Edytuj szkolenie</h1>

            <!-- Komunikat o sukcesie -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Komunikat o błędzie -->
            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning">
                    {{ session('warning') }}
                </div>
            @endif

            <!-- Błędy walidacji -->
            @if($errors->any())
                <div class="alert alert-danger">
                    <h5 class="alert-heading">Wystąpiły błędy walidacji:</h5>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Formularz edycji kursu -->
            <form action="{{ route('courses.update', $course->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <!-- Zachowaj parametry filtrów -->
                @foreach(request()->query() as $key => $value)
                    @if(!in_array($key, ['page']))
                        <input type="hidden" name="filter_{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł</label>
                    <input type="text" name="title" class="form-control" id="title" value="{{ $course->title }}" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Zakres szkolenia / Zagadnienia</label>
                    <textarea name="description" class="form-control" id="description" rows="6">{{ $course->description }}</textarea>
                    <div class="form-text">
                        Treść na zaświadczeniu PDF, gdy w szablonie włączona jest opcja „Pokaż zakres szkolenia”.
                        Możesz wpisać listę numerowaną (każdy punkt od nowej linii, np. <code>1. Zagadnienie</code>).
                    </div>
                </div>

                <!-- Sekcja opisu oferty -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Opis oferty dla klientów</h5>
                        <small class="text-muted">Pełny opis oferty wyświetlany na stronie pnedu.pl</small>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="offer_summary" class="form-label">Krótkie podsumowanie oferty</label>
                            <textarea name="offer_summary" class="form-control" id="offer_summary" rows="2" placeholder="Krótki opis oferty (max 500 znaków)">{{ old('offer_summary', $course->offer_summary) }}</textarea>
                            <div class="form-text">Krótkie podsumowanie wyświetlane w liście szkoleń</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="offer_description_html" class="form-label">Pełny opis oferty (HTML)</label>
                            <div class="btn-toolbar mb-2" role="toolbar">
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')" title="Pogrubienie">
                                        <i class="fas fa-bold"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')" title="Kursywa">
                                        <i class="fas fa-italic"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')" title="Podkreślenie">
                                        <i class="fas fa-underline"></i>
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTag('h3')" title="Nagłówek 3">
                                        H3
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTag('h4')" title="Nagłówek 4">
                                        H4
                                    </button>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertList('ul')" title="Lista punktowana">
                                        <i class="fas fa-list-ul"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertList('ol')" title="Lista numerowana">
                                        <i class="fas fa-list-ol"></i>
                                    </button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertLink()" title="Link">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewHtml()" title="Podgląd">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <textarea name="offer_description_html" class="form-control @error('offer_description_html') is-invalid @enderror" id="offer_description_html" rows="10" placeholder="Wpisz pełny opis oferty z formatowaniem HTML...">{{ old('offer_description_html', $course->offer_description_html) }}</textarea>
                            @error('offer_description_html')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Możesz używać tagów HTML: &lt;section&gt;, &lt;div&gt;, &lt;h1&gt;-&lt;h6&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;a&gt;, &lt;img&gt;, &lt;hr&gt;, &lt;button&gt;, &lt;code&gt;, &lt;small&gt; i inne standardowe tagi HTML oraz Bootstrap 5.
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Terminy i szczegóły -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="start_date" class="form-label fw-bold">Data rozpoczęcia *</label>
                            <input type="datetime-local" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $course->start_date) }}" required>
                            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label for="end_date" class="form-label fw-bold">Data zakończenia *</label>
                            <input type="datetime-local" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $course->end_date) }}" required>
                            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label for="is_paid" class="form-label fw-bold">Płatność</label>
                            <select name="is_paid" class="form-select @error('is_paid') is-invalid @enderror">
                                <option value="1" {{ old('is_paid', $course->is_paid) == 1 ? 'selected' : '' }}>Płatne</option>
                                <option value="0" {{ old('is_paid', $course->is_paid) == 0 ? 'selected' : '' }}>Bezpłatne</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="category" class="form-label fw-bold">Kategoria *</label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="open" {{ old('category', $course->category) == 'open' ? 'selected' : '' }}>Otwarte</option>
                                <option value="closed" {{ old('category', $course->category) == 'closed' ? 'selected' : '' }}>Zamknięte</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="type" class="form-label fw-bold">Rodzaj *</label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" onchange="toggleCourseFields()" required>
                                <option value="offline" {{ old('type', $course->type) == 'offline' ? 'selected' : '' }}>Stacjonarne</option>
                                <option value="online" {{ old('type', $course->type) == 'online' ? 'selected' : '' }}>Online</option>
                            </select>
                        </div>
                    </div>

                    <div id="online-fields" style="display: {{ old('type', $course->type) == 'online' ? 'block' : 'none' }};">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="platform" class="form-label">Platforma</label>
                                <input type="text" name="platform" class="form-control" id="platform" 
                                    value="{{ $course->onlineDetails->platform ?? '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="meeting_link" class="form-label">Link do spotkania</label>
                                <input type="text" name="meeting_link" class="form-control" id="meeting_link" 
                                    value="{{ $course->onlineDetails->meeting_link ?? '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                                <input type="text" name="meeting_password" class="form-control" id="meeting_password" 
                                    value="{{ $course->onlineDetails->meeting_password ?? '' }}">
                            </div>
                        </div>
                    </div>

                <div class="row">
                    <div class="col-md-4 mb-3" id="clickmeeting-event-wrapper" style="display: {{ old('type', $course->type) == 'online' ? 'block' : 'none' }};">
                        <label for="clickmeeting_event_id" class="form-label">ID wydarzenia ClickMeeting</label>
                        <input type="text" name="clickmeeting_event_id" class="form-control" id="clickmeeting_event_id"
                            value="{{ old('clickmeeting_event_id', $course->onlineDetails->clickmeeting_event_id ?? '') }}">
                        <small class="text-muted d-block">Wypełnij dla kursów online prowadzonych na ClickMeeting.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="sendy_suppression_list_id" class="form-label">ID listy na SENDY</label>
                        <input type="text" name="sendy_suppression_list_id" id="sendy_suppression_list_id"
                            class="form-control font-monospace @error('sendy_suppression_list_id') is-invalid @enderror"
                            value="{{ old('sendy_suppression_list_id', $course->sendy_suppression_list_id) }}"
                            maxlength="255" autocomplete="off">
                        @error('sendy_suppression_list_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Wpisz ID listy z Sendy dla tego szkolenia. Na tej liście przygotuj segment dla tego terminu — np. nazwa „2026-05-07 Roman Lorens”, a jako warunek ustaw (data is 2026-05-07 - pamiętaj że pole data jest typu TEXT). W kampanii dodaj ten segment do wykluczeń, żeby nie wysyłać ponownie oferty już zapisanym osobom.
                        </div>
                    </div>
                </div>

                <div class="card mb-4" id="post-end-access-card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Dostęp po zakończeniu szkolenia</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Jeżeli pola okresu są puste, system użyje globalnego ustawienia z „Zakupy pnedu.pl”. Przydatne głównie dla szkoleń bezpłatnych bez wariantów cenowych — rejestracja zaświadczenia, ręczne dodanie uczestnika i „Dodaj tylko do PNEDU”. Wariant cenowy ma zawsze pierwszeństwo.
                        </p>
                        <div class="form-check mb-3">
                            <input type="checkbox"
                                   class="form-check-input"
                                   name="post_end_access_unlimited"
                                   id="post_end_access_unlimited"
                                   value="1"
                                   {{ old('post_end_access_unlimited', ($course->post_end_access_rule ?? 'duration') === 'unlimited') ? 'checked' : '' }}>
                            <label class="form-check-label" for="post_end_access_unlimited">Dostęp bezterminowy</label>
                        </div>
                        <div class="row g-3" id="postEndAccessDurationFields">
                            <div class="col-md-3">
                                <label for="post_end_access_duration_value" class="form-label">Okres</label>
                                <input type="number"
                                       min="1"
                                       max="999"
                                       name="post_end_access_duration_value"
                                       id="post_end_access_duration_value"
                                       class="form-control @error('post_end_access_duration_value') is-invalid @enderror"
                                       value="{{ old('post_end_access_duration_value', $course->post_end_access_duration_value) }}">
                                @error('post_end_access_duration_value')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="post_end_access_duration_unit" class="form-label">Jednostka</label>
                                <select name="post_end_access_duration_unit"
                                        id="post_end_access_duration_unit"
                                        class="form-select @error('post_end_access_duration_unit') is-invalid @enderror">
                                    @foreach(['days' => 'Dni', 'weeks' => 'Tygodnie', 'months' => 'Miesiące', 'years' => 'Lata'] as $unit => $label)
                                        <option value="{{ $unit }}" {{ old('post_end_access_duration_unit', $course->post_end_access_duration_unit ?? 'months') === $unit ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('post_end_access_duration_unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div id="offline-fields" style="display: {{ old('type', $course->type) == 'offline' ? 'block' : 'none' }};">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="location_name">Nazwa lokalizacji</label>
                                <input type="text" name="location_name" id="location_name" class="form-control"
                                    value="{{ old('location_name', $course->location->location_name ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="address">Adres</label>
                                <input type="text" name="address" id="address" class="form-control"
                                    value="{{ old('address', $course->location->address ?? '') }}">
                            </div>
                        </div>
                        <div class="row mt-2">                     
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control" 
                                value="{{ $course->location->postal_code ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" name="post_office" id="post_office" class="form-control" 
                                value="{{ $course->location->post_office ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" name="country" id="country" class="form-control" 
                                value="{{ $course->location->country ?? 'Polska' }}">
                        </div>
                    </div>
                </div>                

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="instructor_id" class="form-label">Instruktor</label>
                        <select name="instructor_id" class="form-control" id="instructor_id">
                            <option value="">Brak</option>
                            @foreach($instructors as $instructor)
                                <option value="{{ $instructor->id }}" {{ $course->instructor_id == $instructor->id ? 'selected' : '' }}>
                                    {{ $instructor->first_name }} {{ $instructor->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="image" class="form-label">Obrazek kursu</label>                
                        <input type="file" name="image" class="form-control" id="image">
                    
                        @if (!empty($course->image))
                            <div class="mt-2">
                                <img src="{{ asset('storage/' . $course->image) }}" alt="Obrazek kursu" class="img-thumbnail" style="max-width: 200px;">
                    
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image">
                                    <label class="form-check-label" for="remove_image">Usuń grafikę z kursu</label>
                                </div>
                            </div>
                        @endif                   
                    </div>
                    
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="certificate_format" class="form-label">Format numeracji zaświadczeń</label>
                            <input type="text" name="certificate_format" id="certificate_format" class="form-control" 
                                   value="{{ old('certificate_format', isset($course) ? $course->certificate_format : '{nr}/{course_id}/{year}/PNE') }}" 
                                   placeholder="Wpisz format, np. RL/{nr}/{course_id}/2/{year}/PNE">
                            <small class="form-text text-muted">
                                Możesz używać zmiennych: <code>{nr}</code>, <code>{course_id}</code>, <code>{year}</code>.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="issue_date_certyficates" class="form-label">Data wydania zaświadczeń</label>
                            <input type="date" name="issue_date_certyficates" id="issue_date_certyficates" class="form-control @error('issue_date_certyficates') is-invalid @enderror" value="{{ old('issue_date_certyficates', $course->issue_date_certyficates ? $course->issue_date_certyficates->format('Y-m-d') : '') }}">
                            @error('issue_date_certyficates')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="form-text text-muted">
                                Globalna data wydania zaświadczeń dla tego szkolenia
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="certificate_template_id">Szablon certyfikatu</label>
                    <select name="certificate_template_id" id="certificate_template_id" class="form-control">
                        <option value="">Domyślny szablon</option>
                        @foreach($certificateTemplates as $template)
                            <option value="{{ $template->id }}" 
                                {{ old('certificate_template_id', $course->certificate_template_id) == $template->id ? 'selected' : '' }}>
                                {{ $template->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Wybierz szablon wyglądu certyfikatu dla tego kursu.
                        <a href="{{ route('admin.certificate-templates.index') }}" target="_blank">Zarządzaj szablonami</a>
                    </small>
                </div>

                <div class="mb-3">
                    <label for="certificate_download_status" class="form-label">Status zaświadczeń</label>
                    <select name="certificate_download_status" id="certificate_download_status" class="form-select">
                        <option value="download_enabled" {{ old('certificate_download_status', $course->certificate_download_status) === 'download_enabled' ? 'selected' : '' }}>Udostępnij pobieranie zaświadczeń (link na pnedu.pl)</option>
                        <option value="in_preparation" {{ old('certificate_download_status', $course->certificate_download_status) === 'in_preparation' ? 'selected' : '' }}>Zaświadczenie w przygotowaniu</option>
                        <option value="no_certificate" {{ old('certificate_download_status', $course->certificate_download_status) === 'no_certificate' ? 'selected' : '' }}>Brak zaświadczenia</option>
                    </select>
                    <small class="form-text text-muted d-block">Określa, czy uczestnicy mogą pobierać zaświadczenia przez link z tokenem oraz jak wyświetlać status na pnedu.pl.</small>
                </div>

                @if($course->category === 'closed')
                    @include('courses.partials.closed-course-billing', ['course' => $course, 'context' => 'edit'])
                @endif

                <!-- Rejestracja zaświadczenia (formularz na pnedu.pl) -->
                <div class="card mb-4" id="certificate-registration">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-1"></i> Rejestracja zaświadczenia</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Publiczny formularz „Rejestracja zaświadczenia” na pnedu.pl zapisuje uczestnika na liście szkolenia (tabela <code>participants</code>). Zaświadczenia nadal tworzysz i udostępniasz jak dotychczas z panelu.</p>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="certificate_registration_open" class="form-check-input" id="certificate_registration_open" {{ old('certificate_registration_open', $course->certificate_registration_open) ? 'checked' : '' }}>
                            <label class="form-check-label" for="certificate_registration_open">Włącz rejestrację zaświadczenia</label>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="certificate_registration_starts_at" class="form-label">Rejestracja od (opcjonalnie)</label>
                                <input type="datetime-local" name="certificate_registration_starts_at" id="certificate_registration_starts_at" class="form-control" value="{{ old('certificate_registration_starts_at', $course->certificate_registration_starts_at ? $course->certificate_registration_starts_at->format('Y-m-d\TH:i') : '') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="certificate_registration_ends_at" class="form-label">Rejestracja do (opcjonalnie)</label>
                                <input type="datetime-local" name="certificate_registration_ends_at" id="certificate_registration_ends_at" class="form-control" value="{{ old('certificate_registration_ends_at', $course->certificate_registration_ends_at ? $course->certificate_registration_ends_at->format('Y-m-d\TH:i') : '') }}">
                            </div>
                        </div>
                        <div class="border rounded p-3 mb-3 bg-light">
                            <p class="small text-muted mb-2">Formularz na pnedu.pl — dodatkowe pola</p>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="certificate_registration_collect_birth_data" class="form-check-input" id="certificate_registration_collect_birth_data" {{ old('certificate_registration_collect_birth_data', $course->certificate_registration_collect_birth_data) ? 'checked' : '' }}>
                                <label class="form-check-label" for="certificate_registration_collect_birth_data">Pokaż pola: data i miejsce urodzenia</label>
                            </div>
                            <div class="form-check ms-4">
                                <input type="checkbox" name="certificate_registration_birth_data_required" class="form-check-input" id="certificate_registration_birth_data_required" {{ old('certificate_registration_birth_data_required', $course->certificate_registration_birth_data_required) ? 'checked' : '' }}>
                                <label class="form-check-label" for="certificate_registration_birth_data_required">Te pola są obowiązkowe</label>
                            </div>
                            <p class="small text-muted mb-0 mt-2">Druga opcja działa tylko gdy włączone jest pokazywanie pól; po zapisie kursu wyłączenie pierwszej opcji wyzeruje wymaganie obowiązkowości.</p>
                        </div>
                        <script>
                            (function () {
                                var c = document.getElementById('certificate_registration_collect_birth_data');
                                var r = document.getElementById('certificate_registration_birth_data_required');
                                if (!c || !r) return;
                                function sync() {
                                    r.disabled = !c.checked;
                                    if (!c.checked) r.checked = false;
                                }
                                c.addEventListener('change', sync);
                                sync();
                            })();
                        </script>
                        @if($course->certificate_registration_token)
                            <div class="mb-3">
                                <label class="form-label">Token (tylko do odczytu)</label>
                                <input type="text" class="form-control font-monospace" value="{{ $course->certificate_registration_token }}" readonly>
                            </div>
                            @php
                                $regUrl = rtrim(config('services.pnedu_frontend_url', ''), '/') . '/certificate-registration/' . $course->certificate_registration_token;
                            @endphp
                            <div class="mb-3">
                                <label class="form-label">Link do formularza</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace" id="certificate-registration-url" value="{{ $regUrl }}" readonly>
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('certificate-registration-url').value); this.textContent='Skopiowano!'; setTimeout(() => this.textContent='Kopiuj link', 2000);">
                                        Kopiuj link
                                    </button>
                                </div>
                                <a href="{{ $regUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-link btn-sm mt-1 px-0">
                                    <i class="fas fa-external-link-alt me-1"></i>Otwórz formularz w nowej karcie
                                </a>
                            </div>
                        @else
                            <p class="text-muted small mb-0">Zapisz kurs z włączoną rejestracją – wygeneruje się token i pojawi się link.</p>
                        @endif
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" {{ $course->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>

                <div class="form-check mb-3">
                    <input type="hidden" name="show_on_pnedu" value="0">
                    <input type="checkbox" name="show_on_pnedu" value="1" class="form-check-input" id="show_on_pnedu" {{ old('show_on_pnedu', $course->show_on_pnedu) ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_on_pnedu">Pokaż na stronie głównej pnedu.pl</label>
                </div>
                @php
                    $pneduOfferUrl = rtrim(config('services.pnedu_frontend_url', ''), '/') . '/courses/' . $course->id;
                @endphp
                <div class="small mb-3">
                    <a href="{{ $pneduOfferUrl }}" target="_blank" rel="noopener noreferrer" class="link-primary">
                        Podgląd oferty na pnedu.pl <i class="fas fa-external-link-alt ms-1" aria-hidden="true"></i>
                    </a>
                    <span class="text-muted">(nie zależy od checkboxa powyżej)</span>
                </div>

                <!-- Pola dla integracji z zewnętrznymi systemami -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_old" class="form-label">ID ze starej bazy</label>
                        <input type="text" name="id_old" class="form-control" id="id_old" value="{{ old('id_old', $course->id_old) }}" placeholder="np. 12345">
                        <div class="form-text">ID kursu w zewnętrznym systemie (opcjonalne)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="source_id_old" class="form-label">Źródło danych</label>
                        <select name="source_id_old" class="form-control" id="source_id_old">
                            <option value="">Brak</option>
                            <option value="certgen_Publigo" {{ old('source_id_old', $course->source_id_old) == 'certgen_Publigo' ? 'selected' : '' }}>Publigo</option>
                            <option value="certgen_NODN" {{ old('source_id_old', $course->source_id_old) == 'certgen_NODN' ? 'selected' : '' }}>NODN</option>
                            <option value="BD:Certgen-education" {{ old('source_id_old', $course->source_id_old) == 'BD:Certgen-education' ? 'selected' : '' }}>Webinar TIK</option>
                        </select>
                        <div class="form-text">Źródło danych kursu (opcjonalne)</div>
                    </div>
                </div>

                <!-- Pole Notatki -->
                <div class="mb-3">
                    <label for="notatki" class="form-label">Notatki techniczne</label>
                    <textarea name="notatki" id="notatki" class="form-control" rows="4" placeholder="Dodatkowe informacje techniczne związane z danym szkoleniem...">{{ old('notatki', $course->notatki) }}</textarea>
                    <div class="form-text">Pole przeznaczone na dodatkowe informacje techniczne związane z danym szkoleniem</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="save_action" value="stay_editing" class="btn btn-primary">Zapisz zmiany</button>
                    <button type="submit" name="save_action" value="close" class="btn btn-outline-secondary">Zapisz i zamknij formularz</button>
                    <a href="{{ route('courses.index', request()->query()) }}" class="btn btn-secondary">Anuluj</a>
                    <a href="{{ route('participants.index', $course) }}" class="btn btn-primary">
                        <i class="fas fa-users me-1"></i> Uczestnicy ({{ $course->participants->count() }})
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<script>
function toggleCourseFields() {
    const type = document.getElementById('type').value;
    const online = type === 'online';
    document.getElementById('online-fields').style.display = online ? 'block' : 'none';
    document.getElementById('offline-fields').style.display = type === 'offline' ? 'block' : 'none';
    const cm = document.getElementById('clickmeeting-event-wrapper');
    if (cm) {
        cm.style.display = online ? 'block' : 'none';
    }
}

// Funkcje edytora HTML
function formatText(command) {
    const textarea = document.getElementById('offer_description_html');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    let formattedText = '';
    switch(command) {
        case 'bold':
            formattedText = `<strong>${selectedText || 'pogrubiony tekst'}</strong>`;
            break;
        case 'italic':
            formattedText = `<em>${selectedText || 'tekst kursywą'}</em>`;
            break;
        case 'underline':
            formattedText = `<u>${selectedText || 'podkreślony tekst'}</u>`;
            break;
    }
    
    textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
    textarea.focus();
}

function insertTag(tag) {
    const textarea = document.getElementById('offer_description_html');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    const formattedText = `<${tag}>${selectedText || 'nagłówek'}</${tag}>`;
    textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
    textarea.focus();
}

function insertList(type) {
    const textarea = document.getElementById('offer_description_html');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    const listHtml = `<${type}>
    <li>Pierwszy punkt</li>
    <li>Drugi punkt</li>
    <li>Trzeci punkt</li>
</${type}>`;
    
    textarea.value = textarea.value.substring(0, start) + listHtml + textarea.value.substring(end);
    textarea.focus();
}

function insertLink() {
    const textarea = document.getElementById('offer_description_html');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    const url = prompt('Podaj URL:', 'https://');
    if (url) {
        const linkText = selectedText || 'tekst linku';
        const linkHtml = `<a href="${url}">${linkText}</a>`;
        textarea.value = textarea.value.substring(0, start) + linkHtml + textarea.value.substring(end);
        textarea.focus();
    }
}

function previewHtml() {
    const textarea = document.getElementById('offer_description_html');
    const htmlContent = textarea.value;
    
    if (!htmlContent.trim()) {
        alert('Brak treści do podglądu');
        return;
    }
    
    const newWindow = window.open('', '_blank', 'width=800,height=600');
    newWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Podgląd opisu oferty</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                h3, h4 { color: #333; margin-top: 20px; }
                ul, ol { margin-left: 20px; }
                a { color: #007bff; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            ${htmlContent}
        </body>
        </html>
    `);
    newWindow.document.close();
}

window.onload = toggleCourseFields;
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let startDateInput = document.querySelector('input[name="start_date"]');
        let endDateInput = document.querySelector('input[name="end_date"]');

        function validateDates() {
            // Sprawdź czy oba pola są wypełnione
            if (!startDateInput.value || !endDateInput.value) {
                return;
            }

            let startDate = new Date(startDateInput.value);
            let endDate = new Date(endDateInput.value);

            // Sprawdź czy daty są poprawne
            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                return;
            }

            if (endDate <= startDate) {
                alert("Data zakończenia musi być późniejsza niż data rozpoczęcia!");
                endDateInput.value = ""; // Resetowanie błędnej wartości
            }
        }

        // Użyj blur zamiast change - walidacja uruchomi się gdy użytkownik opuści pole
        endDateInput.addEventListener("blur", validateDates);
        startDateInput.addEventListener("blur", validateDates);
    });
</script>
<script>
    function toggleCoursePostEndAccessFields() {
        const unlimited = document.getElementById('post_end_access_unlimited');
        const fields = document.getElementById('postEndAccessDurationFields');
        if (! unlimited || ! fields) {
            return;
        }

        const disabled = unlimited.checked;
        fields.querySelectorAll('input, select').forEach((el) => {
            el.disabled = disabled;
        });
        fields.style.opacity = disabled ? '0.5' : '1';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const unlimited = document.getElementById('post_end_access_unlimited');
        if (unlimited) {
            unlimited.addEventListener('change', toggleCoursePostEndAccessFields);
            toggleCoursePostEndAccessFields();
        }
    });
</script>

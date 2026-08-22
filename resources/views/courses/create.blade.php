<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            @if(!empty($sourceOffer))
                {{ __('Dodaj szkolenie z oferty') }}
            @else
                {{ __('Dodaj nowe szkolenie') }}
            @endif
        </h2>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <!-- Formularz dodawania kursu -->
            <form action="{{ route('courses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                @php
                    $sourceOffer = $sourceOffer ?? null;
                    $defaultIsPaid = old('is_paid', $sourceOffer ? '1' : '1');
                    $defaultCategory = old('category', $sourceOffer->default_course_category ?? 'open');
                    $defaultInstructorId = old('instructor_id', $sourceOffer->instructor_id ?? '');
                    $copyImageDefault = old('copy_image_from_offer', $sourceOffer && $sourceOffer->image ? '1' : '0');
                @endphp

                @if($sourceOffer)
                    <div class="alert alert-info d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <strong>Tworzenie szkolenia z oferty.</strong>
                            Pola zostały uzupełnione z oferty „{{ $sourceOffer->title }}”.
                            Uzupełnij daty rozpoczęcia i zakończenia, wybierz rodzaj szkolenia i zapisz.
                            Publikacja na pnedu.pl jest domyślnie wyłączona.
                        </div>
                        <a href="{{ route('training-offers.show', $sourceOffer) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                            Wróć do oferty
                        </a>
                    </div>
                    <input type="hidden" name="training_offer_id" value="{{ old('training_offer_id', $sourceOffer->id) }}">
                @endif

                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł kursu</label>
                    <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $sourceOffer->title ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Zakres szkolenia / Zagadnienia</label>
                    <textarea name="description" id="description" class="form-control" rows="6">{{ old('description', $sourceOffer->scope ?? '') }}</textarea>
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
                            <textarea name="offer_summary" class="form-control" id="offer_summary" rows="2" placeholder="Krótki opis oferty (max 500 znaków)">{{ old('offer_summary', $sourceOffer->summary ?? '') }}</textarea>
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
                            <textarea name="offer_description_html" class="form-control" id="offer_description_html" rows="10" placeholder="Wpisz pełny opis oferty z formatowaniem HTML...">{{ old('offer_description_html', $sourceOffer->description_html ?? '') }}</textarea>
                            <div class="form-text">
                                Możesz używać podstawowych tagów HTML: &lt;strong&gt;, &lt;em&gt;, &lt;h3&gt;, &lt;h4&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;a&gt;, &lt;p&gt;, &lt;br&gt;
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row row mb-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data rozpoczęcia</label>
                        <input type="datetime-local" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Data zakończenia</label>
                        <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $course->end_date ?? '') }}">
                        @error('end_date')
                            <div class="text-danger">{{ $message }}</div> <!-- ✅ Wyświetlanie błędu -->
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label for="is_paid" class="form-label">Płatność</label>
                        <select name="is_paid" class="form-control" id="is_paid" required>
                            <option value="1" {{ (string) $defaultIsPaid === '1' ? 'selected' : '' }}>Płatne</option>
                            <option value="0" {{ (string) $defaultIsPaid === '0' ? 'selected' : '' }}>Bezpłatne</option>
                        </select>
                    </div>                    
                    <div class="col-md-2">
                        <label for="category" class="form-label">Kategoria</label>
                        <select name="category" class="form-control" id="category" required>
                            <option value="open" {{ $defaultCategory === 'open' ? 'selected' : '' }}>Otwarte</option>
                            <option value="closed" {{ $defaultCategory === 'closed' ? 'selected' : '' }}>Zamknięte</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Rodzaj kursu</label>
                        <select name="type" id="type" class="form-control" onchange="toggleCourseFields()">
                            <option value="online" selected>Online</option>
                            <option value="offline">Stacjonarne</option>
                        </select>
                    </div>
                </div>

                <!-- Pola dla kursów online -->
                <div id="onlineFields" class="row">
                    <div class="col-md-4">
                        <label for="platform" class="form-label">Platforma</label>
                        <input type="text" name="platform" id="platform" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="meeting_link" class="form-label">Link do spotkania</label>
                        <input type="url" name="meeting_link" id="meeting_link" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="meeting_password" class="form-label">Hasło do spotkania</label>
                        <input type="text" name="meeting_password" id="meeting_password" class="form-control">
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-md-4 mb-3" id="clickmeeting-event-wrapper">
                        <label for="clickmeeting_event_id" class="form-label">ID wydarzenia ClickMeeting</label>
                        <input type="text" name="clickmeeting_event_id" id="clickmeeting_event_id" class="form-control" value="{{ old('clickmeeting_event_id') }}">
                        <small class="text-muted d-block">Uzupełnij tylko dla kursów online na ClickMeeting.</small>
                        @php
                            $liveRoomMode = old('live_room_mode', 'clickmeeting');
                            $embedEmailLinkEnabled = old('embed_email_link_enabled', true);
                        @endphp
                        <div class="mt-2">
                            <div class="form-label mb-1">Wejście do pokoju dla uczestnika</div>
                            <div class="form-check">
                                <input type="radio"
                                       class="form-check-input"
                                       name="live_room_mode"
                                       id="live_room_mode_clickmeeting"
                                       value="clickmeeting"
                                       {{ $liveRoomMode === 'clickmeeting' ? 'checked' : '' }}>
                                <label class="form-check-label" for="live_room_mode_clickmeeting">
                                    Pokój na ClickMeeting
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="radio"
                                       class="form-check-input"
                                       name="live_room_mode"
                                       id="live_room_mode_embed"
                                       value="embed_pnedu"
                                       {{ $liveRoomMode === 'embed_pnedu' ? 'checked' : '' }}>
                                <label class="form-check-label" for="live_room_mode_embed">
                                    Osadzony pokój na pnedu.pl
                                </label>
                            </div>
                            <div class="form-text">
                                Tylko jedna opcja: klasyczny ClickMeeting albo pokój osadzony na koncie.
                            </div>
                            <div class="form-check mt-2" id="embed-email-link-wrapper">
                                <input type="hidden" name="embed_email_link_enabled" value="0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       name="embed_email_link_enabled"
                                       id="embed_email_link_enabled"
                                       value="1"
                                       {{ $embedEmailLinkEnabled ? 'checked' : '' }}>
                                <label class="form-check-label" for="embed_email_link_enabled">
                                    Link w e-mailu do osadzonego w PNEDU pokoju
                                </label>
                                <div class="form-text">
                                    Gdy włączone, e-maile „Dodaj uczestnika do PNEDU” i „Wyślij link do live” prowadzą głównie do pokoju w pnedu.pl, z alternatywnym linkiem bezpośrednim do ClickMeeting.
                                </div>
                            </div>
                            @error('live_room_mode')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="sendy_suppression_list_id" class="form-label">ID listy na SENDY</label>
                        <input type="text" name="sendy_suppression_list_id" id="sendy_suppression_list_id"
                            class="form-control font-monospace @error('sendy_suppression_list_id') is-invalid @enderror"
                            value="{{ old('sendy_suppression_list_id') }}"
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
                            Domyślnie pobieramy globalne ustawienie z „Zakupy pnedu.pl”. Przydatne głównie dla szkoleń bezpłatnych bez wariantów cenowych. Wariant cenowy ma zawsze pierwszeństwo.
                        </p>
                        <div class="form-check mb-3">
                            <input type="checkbox"
                                   class="form-check-input"
                                   name="post_end_access_unlimited"
                                   id="post_end_access_unlimited"
                                   value="1"
                                   {{ old('post_end_access_unlimited') ? 'checked' : '' }}>
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
                                       value="{{ old('post_end_access_duration_value', optional($paymentDisplayOptions)->default_post_end_access_duration_value ?? 2) }}">
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
                                        <option value="{{ $unit }}" {{ old('post_end_access_duration_unit', optional($paymentDisplayOptions)->default_post_end_access_duration_unit ?? 'months') === $unit ? 'selected' : '' }}>
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

                <!-- Pola dla kursów stacjonarnych -->
                <div id="offlineFields" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="location_name">Nazwa lokalizacji</label>
                            <input type="text" name="location_name" id="location_name" class="form-control" value="{{ old('location_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="address">Adres</label>
                            <input type="text" name="address" id="address" class="form-control" value="{{ old('address') }}">
                        </div>
                    </div>
                    <div class="row mt-2">                    
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Kod pocztowy</label>
                            <input type="text" name="postal_code" id="postal_code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="post_office" class="form-label">Poczta</label>
                            <input type="text" name="post_office" id="post_office" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="country" class="form-label">Kraj</label>
                            <input type="text" name="country" id="country" class="form-control" value="Polska">
                        </div>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="instructor_id" class="form-label">Instruktor</label>
                            <select name="instructor_id" id="instructor_id" class="form-control">
                                <option value="">Brak</option>
                                @foreach ($instructors as $instructor)
                                    <option value="{{ $instructor->id }}" {{ (string) $defaultInstructorId === (string) $instructor->id ? 'selected' : '' }}>
                                        {{ $instructor->first_name }} {{ $instructor->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="image" class="form-label">Obrazek</label>
                            <input type="file" name="image" id="image" class="form-control">
                            @if($sourceOffer && $sourceOffer->publicImageUrl())
                                <div class="mt-3 p-3 border rounded bg-light">
                                    <div class="small text-muted mb-2">Podgląd grafiki z oferty (zostanie skopiowana przy zapisie, jeśli nie wgrasz nowego pliku):</div>
                                    <img src="{{ $sourceOffer->publicImageUrl() }}" alt="{{ $sourceOffer->title }}" class="img-fluid rounded border" style="max-height: 180px;">
                                    <div class="form-check mt-3 mb-0">
                                        <input type="hidden" name="copy_image_from_offer" value="0">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="copy_image_from_offer"
                                               id="copy_image_from_offer"
                                               value="1"
                                               {{ (string) $copyImageDefault === '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="copy_image_from_offer">
                                            Skopiuj grafikę z oferty przy zapisie szkolenia
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>
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
                            <input type="date" name="issue_date_certyficates" id="issue_date_certyficates" class="form-control" value="{{ old('issue_date_certyficates') }}">
                            @error('issue_date_certyficates')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
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
                            <option value="{{ $template->id }}" {{ old('certificate_template_id') == $template->id ? 'selected' : '' }}>
                                {{ $template->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Wybierz szablon wyglądu certyfikatu dla tego kursu.
                        <a href="{{ route('admin.certificate-templates.index') }}" target="_blank">Zarządzaj szablonami</a>
                    </small>
                </div>

                <!-- Pola dla integracji ze starym systemem -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_old" class="form-label">ID ze starej bazy</label>
                        <input type="text" name="id_old" class="form-control" id="id_old" value="{{ old('id_old') }}" placeholder="np. 12345">
                        <div class="form-text">ID kursu w zewnętrznym systemie (opcjonalne)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="source_id_old" class="form-label">Źródło danych</label>
                        <select name="source_id_old" class="form-control" id="source_id_old">
                            <option value="">Brak</option>
                            <option value="certgen_Publigo" {{ old('source_id_old') == 'certgen_Publigo' ? 'selected' : '' }}>Publigo</option>
                            <option value="certgen_NODN" {{ old('source_id_old') == 'certgen_NODN' ? 'selected' : '' }}>NODN</option>
                            <option value="BD:Certgen-education" {{ old('source_id_old') == 'BD:Certgen-education' ? 'selected' : '' }}>Webinar TIK</option>
                        </select>
                        <div class="form-text">Źródło danych kursu (opcjonalne)</div>
                    </div>
                </div>

                <!-- Pole Notatki -->
                <div class="mb-3">
                    <label for="notatki" class="form-label">Notatki techniczne</label>
                    <textarea name="notatki" id="notatki" class="form-control" rows="4" placeholder="Dodatkowe informacje techniczne związane z danym szkoleniem...">{{ old('notatki', $sourceOffer->internal_notes ?? '') }}</textarea>
                    <div class="form-text">Pole przeznaczone na dodatkowe informacje techniczne związane z danym szkoleniem</div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Aktywny</label>
                </div>

                <div class="form-check mb-3">
                    <input type="hidden" name="show_on_pnedu" value="0">
                    <input type="checkbox" name="show_on_pnedu" value="1" class="form-check-input" id="show_on_pnedu" {{ old('show_on_pnedu', $sourceOffer ? '0' : '0') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_on_pnedu">Pokaż na stronie głównej pnedu.pl</label>
                </div>
                <p class="text-muted small mb-3">Po pierwszym zapisie szkolenia link do podglądu strony oferty na pnedu.pl pojawi się na stronie edycji przy tym ustawieniu.</p>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="save_action" value="stay_editing" class="btn btn-primary">Dodaj kurs</button>
                    <button type="submit" name="save_action" value="close" class="btn btn-outline-secondary">Dodaj kurs i zamknij formularz</button>
                    <a href="{{ route('courses.index') }}" class="btn btn-secondary">Anuluj</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleCourseFields() {
            const type = document.getElementById('type').value;
            const online = type === 'online';
            document.getElementById('onlineFields').style.display = online ? 'flex' : 'none';
            document.getElementById('offlineFields').style.display = (type === 'offline') ? 'block' : 'none';
            const cm = document.getElementById('clickmeeting-event-wrapper');
            if (cm) {
                cm.style.display = online ? '' : 'none';
            }
            toggleEmbedEmailLinkOption();
        }

        function toggleEmbedEmailLinkOption() {
            const wrapper = document.getElementById('embed-email-link-wrapper');
            const embedRadio = document.getElementById('live_room_mode_embed');
            if (! wrapper || ! embedRadio) {
                return;
            }

            wrapper.style.display = embedRadio.checked ? 'block' : 'none';
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

        // Wywołanie funkcji przy załadowaniu strony, aby ukryć/pokazać odpowiednie pola
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('input[name="live_room_mode"]').forEach(function (input) {
                input.addEventListener('change', toggleEmbedEmailLinkOption);
            });
            toggleCourseFields();
        });
    </script>

</x-app-layout>
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

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="fw-semibold fs-4 text-dark mb-1">
                    <i class="fas fa-broadcast-tower me-2"></i>Panel live
                </h2>
                <p class="text-muted mb-0">
                    <strong>{!! $course->title !!}</strong>
                    @if($course->start_date)
                        <span class="ms-2">
                            <i class="fas fa-calendar me-1"></i>
                            {{ $course->start_date->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                        </span>
                    @endif
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('courses.show', $course->id) }}" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i> Szkolenie
                </a>
                <a href="{{ route('participants.index', $course->id) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-users me-1"></i> Uczestnicy
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $flags = $state['flags'];
        $resources = $state['resources'];
        $links = $state['links'];
        $embedEntries = $state['embed_entries'];
        $onlineNow = $state['online_now'] ?? ['count' => 0, 'viewers' => [], 'truncated' => false];
        $cmChat = $state['cm_chat'] ?? ['text' => '', 'empty' => true, 'lines' => []];
    @endphp

    <div class="container py-3" id="course-live-panel"
         data-update-url="{{ route('courses.live.update', $course->id) }}"
         data-offer-update-url="{{ route('courses.live.offer', $course->id) }}"
         data-state-url="{{ route('courses.live', $course->id) }}"
         data-poll-ms="{{ \App\Services\CourseLiveResourceBarService::PANEL_POLL_MS }}">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(! $state['has_online_details'])
            <div class="alert alert-warning">
                To szkolenie nie ma jeszcze danych online.
                <a href="{{ route('courses.edit', $course->id) }}">Ustaw ClickMeeting / osadzony pokój</a>, potem wróć tutaj.
            </div>
        @elseif(! $state['embed_on_pnedu'])
            <div class="alert alert-warning">
                Belka działa tylko na <strong>osadzonym pokoju</strong> (`/transmisja`).
                Teraz radio jest na ClickMeeting — uczestnicy tej belki nie zobaczą.
                <a href="{{ route('courses.edit', $course->id) }}">Zmień w edycji szkolenia</a>.
            </div>
        @endif

        @php
            $offer = $state['offer'] ?? ['enabled' => false, 'course_id' => null, 'course' => null];
            $offerEnabled = (bool) ($offer['enabled'] ?? false);
            $offerItem = $offer['course'] ?? null;
        @endphp
        <div class="card mb-3">
            <div class="card-header">
                <strong>Oferta kolejnego szkolenia</strong>
                <span class="text-muted small d-block">
                    Wybierz inne szkolenie, które chcesz pokazać uczestnikom na transmisji. Bieżące spotkanie nie jest na liście.
                </span>
            </div>
            <div class="card-body py-3">
                <label for="live_offer_course_id" class="form-label mb-1">Szkolenie do oferty</label>
                <select class="form-control" id="live_offer_course_id">
                    @if($offerItem)
                        <option value="{{ $offerItem['id'] }}" selected>
                            #{{ $offerItem['id'] }} · {{ $offerItem['title_text'] }}
                            @if(!empty($offerItem['start_date']))
                                [{{ $offerItem['start_date'] }}]
                            @endif
                        </option>
                    @endif
                </select>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
                    <small class="form-text text-muted mb-0">Domyślnie nadchodzące i trwające. Wpisz tytuł / ID, by szukać też w archiwum.</small>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" id="live_offer_include_archived">
                        <label class="form-check-label small" for="live_offer_include_archived">
                            Pokaż również archiwalne
                        </label>
                    </div>
                </div>
                <div id="live-offer-select-error" class="alert alert-warning mt-2 mb-0 py-2 small d-none" role="alert">
                    Nie udało się uruchomić wyszukiwarki szkoleń. Odśwież stronę.
                </div>

                <div id="live-offer-actions" class="mt-3 pt-3 border-top {{ $offerItem ? '' : 'd-none' }}">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn {{ $offerEnabled ? 'btn-outline-secondary' : 'btn-primary' }}"
                                id="live-offer-toggle"
                                @disabled(! $state['has_online_details'])>
                            @if($offerEnabled)
                                <i class="fas fa-eye-slash me-1"></i> Ukryj ofertę
                            @else
                                <i class="fas fa-bullhorn me-1"></i> Wyświetl uczestnikom
                            @endif
                        </button>
                        <span id="live-offer-badge" class="badge {{ $offerEnabled ? 'text-bg-success' : 'text-bg-light text-muted border' }}">
                            {{ $offerEnabled ? 'Włączona' : 'Ukryta' }}
                        </span>
                    </div>
                    @php
                        $offerAutoHide = ($offer['auto_hide'] ?? true) !== false;
                    @endphp
                    <div class="form-check mt-2 mb-0">
                        <input class="form-check-input" type="checkbox" id="live_offer_auto_hide"
                               @checked($offerAutoHide)
                               @disabled(! $state['has_online_details'])>
                        <label class="form-check-label" for="live_offer_auto_hide">
                            Ukryj ofertę po 2 minutach
                        </label>
                    </div>
                    <div id="live-offer-save-status" class="small text-muted mt-2">
                        @if($offerEnabled)
                            @if($offerAutoHide)
                                Oferta włączona na 2 minuty — potem schowa się sama (chyba że ukryjesz wcześniej).
                            @else
                                Oferta włączona bez limitu czasu — schowa się dopiero po „Ukryj ofertę”.
                            @endif
                        @else
                            Po wybraniu szkolenia włącz ofertę przyciskiem. Domyślnie znika po <strong>2 minutach</strong> (odznacz checkbox, jeśli ma zostać do ręcznego ukrycia).
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                @if(! empty($state['guest_live']['url']))
                    <div class="card mb-3">
                        <div class="card-header"><strong>Link do live bez logowania</strong></div>
                        <div class="card-body py-3">
                            @include('courses.partials.guest-live-link', [
                                'guestLive' => $state['guest_live'],
                                'inputId' => 'live-guest-live-url',
                                'wrapperClass' => 'mb-0',
                            ])
                        </div>
                    </div>
                @endif
                <div class="card">
                    <div class="card-header">
                        <strong>Co pokazać na belce</strong>
                        <span class="text-muted small d-block">Przełącznik działa tylko wtedy, gdy dany zasób jest już na szkoleniu. Link wchodzi i schodzi u uczestnika w ciągu ok. 5 s.</span>
                    </div>
                    <div class="card-body">
                        <form id="course-live-form" method="post" action="{{ route('courses.live.update', $course->id) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="live_bar_attendance_enabled" value="0">
                            <input type="hidden" name="live_bar_certificate_enabled" value="0">
                            <input type="hidden" name="live_bar_materials_enabled" value="0">
                            <input type="hidden" name="live_bar_survey_enabled" value="0">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="live_bar_attendance_enabled" name="live_bar_attendance_enabled" value="1"
                                       @checked($flags['attendance'] && $resources['attendance']['ready'])
                                       @disabled(! $state['has_online_details'] || ! $resources['attendance']['ready'])>
                                <label class="form-check-label {{ $resources['attendance']['ready'] ? '' : 'text-muted' }}" for="live_bar_attendance_enabled">
                                    Rejestracja: lista obecności
                                </label>
                                <div class="small {{ $resources['attendance']['ready'] ? 'text-success' : 'text-muted' }}">
                                    @if($resources['attendance']['parked'] ?? false)
                                        Gość na `/live/{token}` podaje imię, nazwisko i e-mail na formularzu przed wejściem (jak lobby ClickMeeting, plus nazwisko).
                                        Na zalogowanym `/transmisja` belka też nie pokazuje rejestracji — uczestnik jest już na liście.
                                    @elseif($resources['attendance']['ready'])
                                        Goście na `/live/…` zobaczą ten przycisk. Zalogowani na `/transmisja` nadal go nie widzą.
                                    @else
                                        Brak włączonej rejestracji zaświadczenia z tokenem.
                                        <a href="{{ route('courses.edit', $course->id) }}">Ustaw na karcie szkolenia</a>
                                        — dopóki tego nie ma, przełącznik jest nieaktywny.
                                    @endif
                                </div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="live_bar_materials_enabled" name="live_bar_materials_enabled" value="1"
                                       @checked($flags['materials'] && $resources['materials']['ready'])
                                       @disabled(! $state['has_online_details'] || ! $resources['materials']['ready'])>
                                <label class="form-check-label {{ $resources['materials']['ready'] ? '' : 'text-muted' }}" for="live_bar_materials_enabled">
                                    Materiały
                                </label>
                                <div class="small {{ $resources['materials']['ready'] ? 'text-success' : 'text-muted' }}">
                                    @if($resources['materials']['ready'])
                                        {{ count($resources['materials']['items']) }}
                                        {{ count($resources['materials']['items']) === 1 ? 'link' : 'linki' }}
                                        z karty szkolenia (także przed końcem szkolenia — tylko belka, nie dashboard).
                                    @else
                                        Brak linków w materiałach kursu.
                                        <a href="{{ route('courses.show', $course->id) }}">Dodaj na karcie szkolenia</a>
                                        — dopóki tego nie ma, przełącznik jest nieaktywny.
                                    @endif
                                </div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="live_bar_survey_enabled" name="live_bar_survey_enabled" value="1"
                                       @checked($flags['survey'] && $resources['survey']['ready'])
                                       @disabled(! $state['has_online_details'] || ! $resources['survey']['ready'])>
                                <label class="form-check-label {{ $resources['survey']['ready'] ? '' : 'text-muted' }}" for="live_bar_survey_enabled">
                                    Ankieta
                                </label>
                                <div class="small {{ $resources['survey']['ready'] ? 'text-success' : 'text-muted' }}">
                                    @if($resources['survey']['ready'])
                                        Aktywna ankieta w oknie czasowym ({{ count($resources['survey']['items']) }}).
                                    @else
                                        Brak aktywnej ankiety w oknie od–do.
                                        <a href="{{ route('courses.show', $course->id) }}">Dodaj na karcie szkolenia</a>
                                        — dopóki tego nie ma, przełącznik jest nieaktywny.
                                    @endif
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="live_bar_certificate_enabled" name="live_bar_certificate_enabled" value="1"
                                       @checked($flags['certificate'] && $resources['certificate']['ready'])
                                       @disabled(! $state['has_online_details'] || ! $resources['certificate']['ready'])>
                                <label class="form-check-label {{ $resources['certificate']['ready'] ? '' : 'text-muted' }}" for="live_bar_certificate_enabled">
                                    Pobierz zaświadczenie
                                </label>
                                <div class="small {{ $resources['certificate']['ready'] ? 'text-success' : 'text-muted' }}">
                                    @if($resources['certificate']['ready'])
                                        Status zaświadczeń: udostępnione na pnedu.pl.
                                    @else
                                        Status zaświadczeń nie jest ustawiony na „Udostępnij pobieranie zaświadczeń (link na pnedu.pl)”.
                                        <a href="{{ route('courses.edit', $course->id) }}">Zmień na karcie szkolenia</a>
                                        — dopóki tego nie ma, przełącznik jest nieaktywny.
                                    @endif
                                </div>
                            </div>

                            <noscript>
                                <button type="submit" class="btn btn-primary">Zapisz</button>
                            </noscript>
                        </form>
                        <p class="small text-muted mb-0" id="course-live-save-status">Zapis automatyczny po przełączeniu.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header"><strong>Na czat ClickMeeting</strong></div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Osoby na bezpośrednim linku pokoju nie widzą belki. Skopiuj i wklej na czat w ClickMeeting.
                            Tekst zmienia się razem z przełącznikami i ofertą.
                        </p>
                        <textarea class="form-control form-control-sm font-monospace"
                                  id="course-live-cm-chat"
                                  rows="6"
                                  readonly
                                  @disabled($cmChat['empty'] ?? true)>{{ $cmChat['text'] ?? '' }}</textarea>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    id="course-live-cm-chat-copy"
                                    @disabled($cmChat['empty'] ?? true)>
                                Kopiuj na czat
                            </button>
                            <span class="small text-muted" id="course-live-cm-chat-status">
                                @if($cmChat['empty'] ?? true)
                                    Włącz materiały, ankietę, zaświadczenie albo ofertę.
                                @else
                                    Wklej w czat publiczny pokoju ClickMeeting.
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Widoczne teraz na transmisji</strong></div>
                    <div class="card-body" id="course-live-preview">
                        @if(count($links) === 0)
                            <p class="text-muted mb-0">Uczestnik nie widzi żadnego dodatkowego przycisku.</p>
                        @else
                            <ul class="mb-0 ps-3">
                                @foreach($links as $link)
                                    <li>
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">{{ $link['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Wejścia przez osadzony pokój</strong></div>
                    <div class="card-body" id="course-live-embed-counts">
                        <p class="mb-1">
                            Kiedykolwiek na `/transmisja`:
                            <strong id="course-live-embed-ever">{{ $embedEntries['ever'] }}</strong>
                        </p>
                        <p class="mb-0 text-muted">
                            Ostatnie 15 minut:
                            <strong id="course-live-embed-recent">{{ $embedEntries['recent_15min'] }}</strong>
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <strong>Teraz na osadzonym live</strong>
                        <span class="badge text-bg-success" id="course-live-online-count">{{ (int) ($onlineNow['count'] ?? 0) }}</span>
                    </div>
                    <div class="card-body" id="course-live-online-now">
                        @if(($onlineNow['count'] ?? 0) === 0)
                            <p class="text-muted mb-0">Nikt nie ma teraz otwartego `/transmisja`.</p>
                        @else
                            <ul class="mb-0 ps-3">
                                @foreach(($onlineNow['viewers'] ?? []) as $viewer)
                                    <li>
                                        <strong>{{ $viewer['name'] }}</strong>
                                        @if(! empty($viewer['email']))
                                            <span class="text-muted">{{ $viewer['email'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if(! empty($onlineNow['truncated']))
                                <p class="small text-muted mb-0 mt-2">Lista obcięta do 100 osób.</p>
                            @endif
                        @endif
                    </div>
                    <div class="card-footer small text-muted">
                        Heartbeat z `/transmisja` (ok. 25 s) + poll belki. Znika po zamknięciu karty albo po ok. 3 min bez sygnału. Powrót na kartę wznawia belkę i obecność bez odświeżania.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const panel = document.getElementById('course-live-panel');
            if (!panel) {
                return;
            }
            const form = document.getElementById('course-live-form');
            const statusEl = document.getElementById('course-live-save-status');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const updateUrl = panel.getAttribute('data-update-url');
            const stateUrl = panel.getAttribute('data-state-url');
            const attendance = document.getElementById('live_bar_attendance_enabled');
            const certificate = document.getElementById('live_bar_certificate_enabled');
            const materials = document.getElementById('live_bar_materials_enabled');
            const survey = document.getElementById('live_bar_survey_enabled');

            function setStatus(text, isError) {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = text;
                statusEl.classList.toggle('text-danger', !!isError);
                statusEl.classList.toggle('text-muted', !isError);
            }

            function payload() {
                return {
                    live_bar_attendance_enabled: !!(attendance && attendance.checked && !attendance.disabled),
                    live_bar_certificate_enabled: !!(certificate && certificate.checked && !certificate.disabled),
                    live_bar_materials_enabled: !!(materials && materials.checked && !materials.disabled),
                    live_bar_survey_enabled: !!(survey && survey.checked && !survey.disabled),
                };
            }

            function applySwitch(el, on, ready, hasDetails) {
                if (!el) {
                    return;
                }
                const canToggle = !!hasDetails && !!ready;
                el.disabled = !canToggle;
                el.checked = !!on && canToggle;
                const label = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
                if (label) {
                    label.classList.toggle('text-muted', !canToggle);
                }
            }

            function renderState(state) {
                if (!state) {
                    return;
                }
                const hasDetails = !!state.has_online_details;
                applySwitch(attendance, state.flags?.attendance, state.resources?.attendance?.ready, hasDetails);
                applySwitch(certificate, state.flags?.certificate, state.resources?.certificate?.ready, hasDetails);
                applySwitch(materials, state.flags?.materials, state.resources?.materials?.ready, hasDetails);
                applySwitch(survey, state.flags?.survey, state.resources?.survey?.ready, hasDetails);
                const preview = document.getElementById('course-live-preview');
                if (preview) {
                    const links = Array.isArray(state.links) ? state.links : [];
                    if (links.length === 0) {
                        preview.innerHTML = '<p class="text-muted mb-0">Uczestnik nie widzi żadnego dodatkowego przycisku.</p>';
                    } else {
                        const ul = document.createElement('ul');
                        ul.className = 'mb-0 ps-3';
                        links.forEach(function (link) {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.href = link.url;
                            a.target = '_blank';
                            a.rel = 'noopener noreferrer';
                            a.textContent = link.label;
                            li.appendChild(a);
                            ul.appendChild(li);
                        });
                        preview.replaceChildren(ul);
                    }
                }
                const ever = document.getElementById('course-live-embed-ever');
                const recent = document.getElementById('course-live-embed-recent');
                if (ever) {
                    ever.textContent = String(state.embed_entries?.ever ?? 0);
                }
                if (recent) {
                    recent.textContent = String(state.embed_entries?.recent_15min ?? 0);
                }
                renderOnlineNow(state.online_now);
                renderCmChat(state.cm_chat);
                if (state.offer && typeof window.pneLiveSyncOffer === 'function') {
                    window.pneLiveSyncOffer(state.offer);
                }
            }

            function renderCmChat(chat) {
                const box = document.getElementById('course-live-cm-chat');
                const copyBtn = document.getElementById('course-live-cm-chat-copy');
                const status = document.getElementById('course-live-cm-chat-status');
                const data = chat && typeof chat === 'object' ? chat : {};
                const text = typeof data.text === 'string' ? data.text : '';
                const empty = text.trim() === '';
                if (box) {
                    box.value = text;
                    box.disabled = empty;
                }
                if (copyBtn) {
                    copyBtn.disabled = empty;
                    if (copyBtn.dataset.copied !== '1') {
                        copyBtn.textContent = 'Kopiuj na czat';
                    }
                }
                if (status && copyBtn?.dataset.copied !== '1') {
                    status.textContent = empty
                        ? 'Włącz materiały, ankietę, zaświadczenie albo ofertę.'
                        : 'Wklej w czat publiczny pokoju ClickMeeting.';
                }
            }

            const cmChatCopy = document.getElementById('course-live-cm-chat-copy');
            if (cmChatCopy) {
                cmChatCopy.addEventListener('click', function () {
                    const box = document.getElementById('course-live-cm-chat');
                    const text = box && !box.disabled ? box.value : '';
                    if (!text) {
                        return;
                    }
                    const done = function () {
                        cmChatCopy.dataset.copied = '1';
                        cmChatCopy.textContent = 'Skopiowano!';
                        setTimeout(function () {
                            cmChatCopy.dataset.copied = '0';
                            cmChatCopy.textContent = 'Kopiuj na czat';
                        }, 2000);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done).catch(function () {
                            box.focus();
                            box.select();
                        });
                    } else {
                        box.focus();
                        box.select();
                    }
                });
            }

            window.pneLiveRenderState = renderState;

            function renderOnlineNow(online) {
                const box = document.getElementById('course-live-online-now');
                const badge = document.getElementById('course-live-online-count');
                const data = online && typeof online === 'object' ? online : {};
                const viewers = Array.isArray(data.viewers) ? data.viewers : [];
                const count = Number(data.count || 0);
                if (badge) {
                    badge.textContent = String(count);
                }
                if (!box) {
                    return;
                }
                if (count === 0 || viewers.length === 0) {
                    box.innerHTML = '<p class="text-muted mb-0">Nikt nie ma teraz otwartego `/transmisja`.</p>';
                    return;
                }
                const ul = document.createElement('ul');
                ul.className = 'mb-0 ps-3';
                viewers.forEach(function (viewer) {
                    const li = document.createElement('li');
                    const name = document.createElement('strong');
                    name.textContent = viewer.name || ('Uczestnik #' + (viewer.id || ''));
                    li.appendChild(name);
                    if (viewer.email) {
                        li.appendChild(document.createTextNode(' '));
                        const email = document.createElement('span');
                        email.className = 'text-muted';
                        email.textContent = viewer.email;
                        li.appendChild(email);
                    }
                    ul.appendChild(li);
                });
                box.replaceChildren(ul);
                if (data.truncated) {
                    const note = document.createElement('p');
                    note.className = 'small text-muted mb-0 mt-2';
                    note.textContent = 'Lista obcięta do 100 osób.';
                    box.appendChild(note);
                }
            }

            function saveFlags() {
                if (!updateUrl || !form) {
                    return;
                }
                const canSave = [attendance, certificate, materials, survey].some(function (el) {
                    return el && !el.disabled;
                });
                if (!canSave) {
                    return;
                }
                setStatus('Zapisuję…', false);
                fetch(updateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload()),
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    });
                }).then(function (result) {
                    if (!result.ok) {
                        setStatus(result.data?.error || 'Nie udało się zapisać.', true);
                        return;
                    }
                    renderState(result.data?.state);
                    setStatus('Zapisane. Uczestnik zobaczy zmianę w ciągu ok. 5 s.', false);
                }).catch(function () {
                    setStatus('Nie udało się zapisać.', true);
                });
            }

            [attendance, certificate, materials, survey].forEach(function (input) {
                if (!input) {
                    return;
                }
                input.addEventListener('change', saveFlags);
            });

            function refreshState() {
                if (!stateUrl) {
                    return;
                }
                if (document.visibilityState === 'hidden') {
                    return;
                }
                fetch(stateUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }).then(function (res) {
                    return res.ok ? res.json() : null;
                }).then(function (state) {
                    if (state) {
                        renderState(state);
                    }
                }).catch(function () {});
            }

            const pollMs = parseInt(panel.getAttribute('data-poll-ms') || '5000', 10);
            setInterval(refreshState, pollMs);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    refreshState();
                }
            });
        })();
    </script>

    @php
        $liveOfferSearchUrl = route('courses.live.search', ['exclude_id' => $course->id]);
        $liveOfferPreselected = $offerItem ?: null;
        $liveOfferEnabled = $offerEnabled;
        $liveOfferAutoHide = ($offer['auto_hide'] ?? true) !== false;
        $liveOfferHasDetails = (bool) $state['has_online_details'];
    @endphp
    @include('partials.ensure-course-select-init')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchUrl = @json($liveOfferSearchUrl);
            const preselected = @json($liveOfferPreselected);
            const hasOnlineDetails = @json($liveOfferHasDetails);
            const offerUpdateUrl = document.getElementById('course-live-panel')?.getAttribute('data-offer-update-url') || '';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const archivedToggle = document.getElementById('live_offer_include_archived');
            const autoHideToggle = document.getElementById('live_offer_auto_hide');
            const errorEl = document.getElementById('live-offer-select-error');
            const actionsEl = document.getElementById('live-offer-actions');
            const toggleBtn = document.getElementById('live-offer-toggle');
            const badgeEl = document.getElementById('live-offer-badge');
            const statusEl = document.getElementById('live-offer-save-status');
            const STORAGE_KEY = 'courseLive.offerSelect.includeArchived';
            let includeArchived = false;
            let selectedItem = preselected;
            let offerEnabled = @json($liveOfferEnabled);
            let offerAutoHide = @json($liveOfferAutoHide);
            let offerExpiresAt = null;
            let offerCountdownTimer = null;

            try {
                includeArchived = window.localStorage.getItem(STORAGE_KEY) === '1';
            } catch (e) {}
            if (archivedToggle) {
                archivedToggle.checked = includeArchived;
            }
            if (autoHideToggle) {
                autoHideToggle.checked = offerAutoHide;
            }

            function selectedCourseId() {
                return selectedItem && selectedItem.id ? parseInt(selectedItem.id, 10) : null;
            }

            function readAutoHide() {
                return autoHideToggle ? !!autoHideToggle.checked : true;
            }

            function setOfferStatus(text, isError) {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = text;
                statusEl.classList.toggle('text-danger', !!isError);
                statusEl.classList.toggle('text-muted', !isError);
            }

            function clearOfferCountdown() {
                if (offerCountdownTimer) {
                    clearInterval(offerCountdownTimer);
                    offerCountdownTimer = null;
                }
            }

            function formatOfferRemaining(expiresIso) {
                if (!expiresIso) {
                    return null;
                }
                const end = Date.parse(expiresIso);
                if (!Number.isFinite(end)) {
                    return null;
                }
                const sec = Math.max(0, Math.ceil((end - Date.now()) / 1000));
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                return m + ':' + String(s).padStart(2, '0');
            }

            function refreshOfferCountdownText() {
                if (!offerEnabled || !offerAutoHide || !offerExpiresAt) {
                    return;
                }
                const left = formatOfferRemaining(offerExpiresAt);
                if (left === null) {
                    return;
                }
                if (left === '0:00') {
                    setOfferStatus('Oferta wygasa…', false);
                    return;
                }
                setOfferStatus('Oferta włączona — znika za ' + left + ' (albo Ukryj wcześniej).', false);
            }

            function startOfferCountdown(expiresIso) {
                clearOfferCountdown();
                offerExpiresAt = expiresIso || null;
                if (!offerAutoHide || !offerExpiresAt) {
                    offerExpiresAt = null;
                    if (offerEnabled && !offerAutoHide) {
                        setOfferStatus('Oferta włączona bez limitu — schowa się po „Ukryj ofertę”.', false);
                    }
                    return;
                }
                refreshOfferCountdownText();
                offerCountdownTimer = setInterval(refreshOfferCountdownText, 1000);
            }

            function applyOfferFromState(offer) {
                const data = offer && typeof offer === 'object' ? offer : {};
                offerEnabled = !!data.enabled;
                if (typeof data.auto_hide === 'boolean') {
                    offerAutoHide = data.auto_hide;
                    if (autoHideToggle) {
                        autoHideToggle.checked = offerAutoHide;
                    }
                }
                if (data.course && data.course.id) {
                    selectedItem = data.course;
                }
                renderToggle();
                if (offerEnabled) {
                    startOfferCountdown(typeof data.expires_at === 'string' ? data.expires_at : null);
                } else {
                    clearOfferCountdown();
                    offerExpiresAt = null;
                }
            }

            function renderToggle() {
                const hasSelection = !!selectedCourseId();
                if (actionsEl) {
                    actionsEl.classList.toggle('d-none', !hasSelection);
                }
                if (autoHideToggle) {
                    autoHideToggle.disabled = !hasOnlineDetails;
                }
                if (!toggleBtn) {
                    return;
                }
                toggleBtn.disabled = !hasOnlineDetails || !hasSelection;
                if (offerEnabled && hasSelection) {
                    toggleBtn.className = 'btn btn-outline-secondary';
                    toggleBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> Ukryj ofertę';
                    if (badgeEl) {
                        badgeEl.className = 'badge text-bg-success';
                        badgeEl.textContent = 'Włączona';
                    }
                } else {
                    toggleBtn.className = 'btn btn-primary';
                    toggleBtn.innerHTML = '<i class="fas fa-bullhorn me-1"></i> Wyświetl uczestnikom';
                    if (badgeEl) {
                        badgeEl.className = 'badge text-bg-light text-muted border';
                        badgeEl.textContent = 'Ukryta';
                    }
                    clearOfferCountdown();
                    offerExpiresAt = null;
                }
            }

            window.pneLiveSyncOffer = function (offer) {
                const data = offer && typeof offer === 'object' ? offer : {};
                const wasEnabled = offerEnabled;
                applyOfferFromState(data);
                if (!offerEnabled && wasEnabled) {
                    setOfferStatus(
                        offerAutoHide
                            ? 'Oferta ukryta (ręcznie lub po 2 minutach).'
                            : 'Oferta ukryta.',
                        false
                    );
                }
            };

            let saveChain = Promise.resolve();

            function saveOffer(enabled, options) {
                const opts = options && typeof options === 'object' ? options : {};
                if (!offerUpdateUrl || !hasOnlineDetails) {
                    return Promise.resolve();
                }
                const courseId = selectedCourseId();
                if (enabled && !courseId) {
                    setOfferStatus('Najpierw wybierz szkolenie do oferty.', true);
                    return Promise.resolve();
                }
                const autoHide = typeof opts.autoHide === 'boolean' ? opts.autoHide : readAutoHide();
                saveChain = saveChain.then(function () {
                    if (toggleBtn && window.PneButtonLoading && !opts.quiet) {
                        window.PneButtonLoading.setButtonLoading(toggleBtn, true, enabled ? 'Włączam…' : 'Ukrywam…');
                    }
                    if (!opts.quiet) {
                        setOfferStatus('Zapisuję…', false);
                    }
                    return fetch(offerUpdateUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            live_offer_course_id: courseId,
                            live_offer_enabled: !!enabled,
                            live_offer_auto_hide: !!autoHide,
                        }),
                    }).then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, data: data };
                        });
                    }).then(function (result) {
                        if (toggleBtn && window.PneButtonLoading) {
                            window.PneButtonLoading.setButtonLoading(toggleBtn, false);
                        }
                        if (!result.ok) {
                            setOfferStatus(result.data?.error || 'Nie udało się zapisać oferty.', true);
                            if (autoHideToggle) {
                                autoHideToggle.checked = offerAutoHide;
                            }
                            renderToggle();
                            return;
                        }
                        const offer = result.data?.state?.offer || {};
                        applyOfferFromState(offer);
                        if (result.data?.state && typeof window.pneLiveRenderState === 'function') {
                            window.pneLiveRenderState(result.data.state);
                        }
                        if (offerEnabled) {
                            if (offerAutoHide) {
                                if (!offerExpiresAt) {
                                    setOfferStatus('Oferta włączona na 2 minuty. Uczestnicy zobaczą ją w ciągu kilku sekund.', false);
                                }
                            } else {
                                setOfferStatus('Oferta włączona bez limitu — schowa się po „Ukryj ofertę”.', false);
                            }
                        } else if (!opts.quiet) {
                            clearOfferCountdown();
                            setOfferStatus('Oferta ukryta.', false);
                        }
                    }).catch(function () {
                        if (toggleBtn && window.PneButtonLoading) {
                            window.PneButtonLoading.setButtonLoading(toggleBtn, false);
                        }
                        setOfferStatus('Nie udało się zapisać oferty.', true);
                        if (autoHideToggle) {
                            autoHideToggle.checked = offerAutoHide;
                        }
                        renderToggle();
                    });
                });
                return saveChain;
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    saveOffer(!offerEnabled);
                });
            }

            if (autoHideToggle) {
                autoHideToggle.addEventListener('change', function () {
                    offerAutoHide = readAutoHide();
                    if (!hasOnlineDetails || !selectedCourseId()) {
                        return;
                    }
                    // Od razu zapisujemy preferencję (i restartujemy / kasujemy timer, jeśli oferta już włączona).
                    saveOffer(offerEnabled, { autoHide: offerAutoHide, quiet: !offerEnabled });
                });
            }

            renderToggle();

            window.ensureCourseSelectInit().then(function (initFn) {
                if (!initFn) {
                    if (errorEl) {
                        errorEl.classList.remove('d-none');
                    }
                    return;
                }
                const ts = initFn('live_offer_course_id', {
                    searchUrl: searchUrl,
                    preselected: preselected,
                    includeArchived: includeArchived,
                    placeholder: 'Wybierz szkolenie do oferty (nie to, które trwa)…',
                    onCourseChanged: function (item) {
                        const wasEnabled = offerEnabled;
                        selectedItem = item && item.id ? item : null;
                        offerEnabled = false;
                        renderToggle();
                        if (!hasOnlineDetails) {
                            return;
                        }
                        if (!selectedItem || wasEnabled) {
                            saveOffer(false);
                        }
                    },
                });
                if (ts && archivedToggle) {
                    archivedToggle.addEventListener('change', function () {
                        const checked = !!archivedToggle.checked;
                        try { window.localStorage.setItem(STORAGE_KEY, checked ? '1' : '0'); } catch (e) {}
                        if (typeof ts.setIncludeArchived === 'function') {
                            ts.setIncludeArchived(checked);
                        }
                    });
                }
            }).catch(function () {
                if (errorEl) {
                    errorEl.classList.remove('d-none');
                }
            });
        });
    </script>
</x-app-layout>

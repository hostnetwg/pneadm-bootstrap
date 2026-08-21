<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ClickMeeting embed PoC (local)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        #cm-embed-shell:not(.is-fullscreen) {
            min-height: 60vh;
        }
        #cm-embed-shell.is-fullscreen {
            position: fixed;
            inset: 0;
            z-index: 1050;
            background: #000;
            padding: 0.75rem;
        }
        #cm-embed-shell.is-fullscreen #cm-embed-container {
            height: calc(100vh - 3.5rem);
        }
        #cm-embed-container {
            min-height: 480px;
            height: 60vh;
            position: relative;
            background: #111;
        }
        #cm-embed-container iframe,
        #cm-embed-container #flashroomIframe {
            width: 100% !important;
            height: 100% !important;
            border: 0;
            display: block;
            background: #111;
        }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h1 class="h4 mb-1">ClickMeeting embed — PoC (tylko local)</h1>
                    <p class="text-muted small mb-0">
                        Test osadzenia pokoju i auto-login. Klucz API nie trafia do przeglądarki.
                    </p>
                </div>
                <span class="badge text-bg-warning">DEV</span>
            </div>

            @if ($errors !== [])
                <div class="alert alert-danger" role="alert">
                    <strong>Problemy konfiguracji / API:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="alert alert-warning small" role="note">
                <strong>Uwaga techniczna:</strong>
                <code>embed_room_url</code> z API to adres <strong>skryptu JS</strong>
                (<code>Content-Type: application/javascript</code>), nie HTML.
                Wstawienie go jako <code>iframe src</code> pokazuje kod źródłowy — tak wyglądał poprzedni błąd PoC.
                Poprawne warianty: iframe na <code>/{room_pin}</code> albo oficjalny <code>&lt;script src=&quot;embed_room_url&quot;&gt;</code>.
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <input type="hidden" name="key" value="{{ $secret }}">
                        <div class="col-md-3">
                            <label for="room_id" class="form-label">Room ID (CM)</label>
                            <input type="text" class="form-control form-control-sm" id="room_id" name="room_id"
                                   value="{{ $roomId }}" required>
                        </div>
                        <div class="col-md-3">
                            <label for="email" class="form-label">E-mail uczestnika</label>
                            <input type="email" class="form-control form-control-sm" id="email" name="email"
                                   value="{{ $email }}" required>
                        </div>
                        <div class="col-md-2">
                            <label for="nickname" class="form-label">Nickname</label>
                            <input type="text" class="form-control form-control-sm" id="nickname" name="nickname"
                                   value="{{ $nickname }}">
                        </div>
                        <div class="col-md-2">
                            <label for="variant" class="form-label">Wariant osadzenia</label>
                            <select class="form-select form-select-sm" id="variant" name="variant">
                                <option value="iframe_autologin" @selected($variant === 'iframe_autologin')>iframe + auto-login</option>
                                <option value="iframe_plain" @selected($variant === 'iframe_plain')>iframe bez auto-login</option>
                                <option value="official_script" @selected($variant === 'official_script')>oficjalny &lt;script&gt;</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Odśwież</button>
                        </div>
                    </form>

                    <dl class="row small mt-3 mb-0">
                        <dt class="col-sm-3">Wydarzenie</dt>
                        <dd class="col-sm-9">{{ $conferenceName !== '' ? $conferenceName : '—' }}</dd>
                        <dt class="col-sm-3">access_type</dt>
                        <dd class="col-sm-9">{{ $accessTypeLabel }}</dd>
                        <dt class="col-sm-3">room_pin</dt>
                        <dd class="col-sm-9"><code>{{ $roomPin ?? '—' }}</code></dd>
                        <dt class="col-sm-3">Token (maskowany)</dt>
                        <dd class="col-sm-9">{{ $tokenMasked ?? '—' }}</dd>
                        <dt class="col-sm-3">Aktywny URL</dt>
                        <dd class="col-sm-9">
                            @if ($useOfficialScript)
                                <code class="user-select-all">script → {{ $embedScriptUrl }}</code>
                            @elseif ($activeIframeSrc)
                                <code class="user-select-all">{{ $activeIframeSrc }}</code>
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="alert alert-info small" role="status">
                <strong>Mobile:</strong> embed nie jest wspierany w wersji RWD ClickMeeting — na telefonie użyj linku
                „Otwórz pokój CM (auto-login)” poniżej.
            </div>

            <div id="cm-embed-shell" class="card shadow-sm mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                    <span class="fw-semibold small">Osadzony pokój ClickMeeting</span>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cm-fullscreen-btn">
                            Pełny ekran
                        </button>
                        @if ($roomAutologinUrl)
                            <a href="{{ $roomAutologinUrl }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">
                                Otwórz pokój CM (auto-login)
                            </a>
                        @endif
                        @if ($roomTokenUrl && $roomTokenUrl !== $roomAutologinUrl)
                            <a href="{{ $roomTokenUrl }}" class="btn btn-outline-dark btn-sm" target="_blank" rel="noopener noreferrer">
                                Otwórz pokój CM (token w URL)
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="cm-embed-container">
                        @if ($useOfficialScript && $embedScriptUrl)
                            <script>
                                var __cm_room_width = '100%';
                                var __cm_room_height = document.getElementById('cm-embed-container').clientHeight || 480;
                            </script>
                            <script src="{{ $embedScriptUrl }}"></script>
                        @elseif ($activeIframeSrc)
                            <iframe
                                id="cm-embed-frame"
                                src="{{ $activeIframeSrc }}"
                                title="ClickMeeting embed PoC"
                                allow="microphone; camera; display-capture; fullscreen; autoplay; encrypted-media"
                                allowfullscreen
                                referrerpolicy="no-referrer"
                            ></iframe>
                        @else
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted small p-4">
                                Brak poprawnego URL embed — popraw konfigurację powyżej.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body small text-muted">
                    <p class="mb-2"><strong>Checklist manualny:</strong> A/V, czat, Q&A, ankieta, screen share, fullscreen, Chrome/Firefox/Safari, telefon (fallback link).</p>
                    <p class="mb-0">Trasa działa wyłącznie przy <code>APP_ENV=local</code> i poprawnym <code>?key=</code>. Nie commituj sekretu do repo.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const shell = document.getElementById('cm-embed-shell');
    const btn = document.getElementById('cm-fullscreen-btn');
    if (!shell || !btn) {
        return;
    }

    btn.addEventListener('click', function () {
        const target = shell;
        if (document.fullscreenElement) {
            document.exitFullscreen();
            return;
        }
        if (target.requestFullscreen) {
            target.requestFullscreen();
        }
    });

    document.addEventListener('fullscreenchange', function () {
        shell.classList.toggle('is-fullscreen', document.fullscreenElement === shell);
        btn.textContent = document.fullscreenElement === shell ? 'Wyjdź z pełnego ekranu' : 'Pełny ekran';
    });
})();
</script>
</body>
</html>

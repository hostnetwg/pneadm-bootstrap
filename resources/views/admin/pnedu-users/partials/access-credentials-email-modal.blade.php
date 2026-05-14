@php
    $pneduBase = rtrim((string) config('services.pnedu_frontend_url'), '/');
    $accessEmailUrls = [
        'login' => $pneduBase.'/login',
        'forgot' => $pneduBase.'/forgot-password',
        'profile' => $pneduBase.'/profile',
    ];
@endphp

<div class="mb-4 pb-4 border-bottom">
    <h6 class="fw-semibold">Wiadomość z danymi dostępowymi (pnedu.pl)</h6>
    <p class="text-muted small mb-2">
        Szablon wiadomości dla uczestnika z adresem logowania i — opcjonalnie — nowym hasłem zapisanym w bazie pnedu.
    </p>
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#accessCredentialsEmailModal">
        <i class="bi bi-envelope-paper me-1"></i> Wyślij e-mail z danymi dostępowymi
    </button>
</div>

<div class="modal fade" id="accessCredentialsEmailModal" tabindex="-1" aria-labelledby="accessCredentialsEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="accessCredentialsEmailModalLabel">Treść wiadomości e-mail</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <div id="accessEmailAlert" class="alert alert-danger d-none" role="alert"></div>
                <div id="accessEmailSuccess" class="alert alert-success d-none" role="alert">
                    Nowe hasło zostało zapisane w bazie pnedu dla tego konta. Skopiuj treść wiadomości i wyślij ją z własnej skrzynki.
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="accessEmailGeneratePassword">
                    <label class="form-check-label" for="accessEmailGeneratePassword">
                        Wygeneruj nowe hasło dla tego konta, zapisz je w systemie i wstaw do treści wiadomości
                        <span class="text-muted">(dotychczasowe hasło przestanie działać)</span>
                    </label>
                </div>
                <div class="mb-2">
                    <label for="accessEmailSubject" class="form-label small fw-semibold mb-1">Temat</label>
                    <input type="text" class="form-control form-control-sm" id="accessEmailSubject" readonly>
                </div>
                <div class="mb-3">
                    <label for="accessEmailBody" class="form-label small fw-semibold mb-1">Treść</label>
                    <textarea class="form-control font-monospace small" id="accessEmailBody" rows="18" readonly></textarea>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="accessEmailCopySubject">Kopiuj temat</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="accessEmailCopyBody">Kopiuj treść</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="accessEmailCopyAll">Kopiuj temat i treść</button>
                    <a href="#" class="btn btn-primary btn-sm" id="accessEmailMailto">Otwórz klienta poczty</a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const urls = @json($accessEmailUrls);
    const participantEmail = @json($user->email);
    const generatePasswordUrl = @json(route('admin.pnedu-users.access-credentials-password', $user));

    const subject = 'Dostęp do nagrania, materiałów i zaświadczenia';

    function buildBodyPlaceholderPassword() {
        return [
            'Dzień dobry,',
            '',
            'dziękujemy za udział w szkoleniu zorganizowanym przez Platformę Nowoczesnej Edukacji.',
            '',
            'Poniżej przesyłamy informacje dotyczące dostępu do nagrania, materiałów szkoleniowych oraz zaświadczenia.',
            '',
            'Strona logowania:',
            urls.login,
            '',
            'Nazwa użytkownika:',
            participantEmail,
            '',
            'Hasło:',
            'wygenerowane hasło zostało przesłane dzień przed szkoleniem w osobnej wiadomości e-mail.',
            '',
            'Jeżeli nie możesz odnaleźć tej wiadomości, sprawdź również foldery takie jak: SPAM, Oferty, Powiadomienia lub Inne, ponieważ wiadomość mogła zostać tam automatycznie przeniesiona.',
            '',
            'Jeśli nie pamiętasz hasła lub nie możesz go odnaleźć, skorzystaj z opcji „Nie pamiętasz hasła?” dostępnej pod adresem:',
            urls.forgot,
            '',
            'Po wpisaniu adresu e-mail uczestnika otrzymasz wiadomość umożliwiającą ustawienie nowego hasła.',
            '',
            'W razie pytań lub problemów z dostępem do szkolenia prosimy o odpowiedź na tę wiadomość. Chętnie pomożemy.',
            '',
            'Pozdrawiam serdecznie,',
            'Waldemar Grabowski',
            'Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli',
            'Platforma Nowoczesnej Edukacji',
            '',
            '-----',
        ].join('\n');
    }

    function buildBodyWithPassword(plainPassword) {
        return [
            'Dzień dobry,',
            '',
            'dziękujemy za udział w szkoleniu zorganizowanym przez Platformę Nowoczesnej Edukacji.',
            '',
            'Poniżej przesyłamy informacje dotyczące dostępu do nagrania, materiałów szkoleniowych oraz zaświadczenia.',
            '',
            'Strona logowania:',
            urls.login,
            '',
            'Nazwa użytkownika:',
            participantEmail,
            '',
            'Hasło:',
            plainPassword,
            '',
            'Po zalogowaniu hasło możesz zmienić na stronie: ' + urls.profile + ' (kliknij swoje imię po prawej na górze i wybierz opcję „Edytuj profil”).',
            '',
            'W razie pytań lub problemów z dostępem do szkolenia prosimy o odpowiedź na tę wiadomość. Chętnie pomożemy.',
            '',
            'Pozdrawiam serdecznie,',
            'Waldemar Grabowski',
            'Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli',
            'Platforma Nowoczesnej Edukacji',
            '',
            '-----',
        ].join('\n');
    }

    const modalEl = document.getElementById('accessCredentialsEmailModal');
    const subjectEl = document.getElementById('accessEmailSubject');
    const bodyEl = document.getElementById('accessEmailBody');
    const checkboxEl = document.getElementById('accessEmailGeneratePassword');
    const alertEl = document.getElementById('accessEmailAlert');
    const successEl = document.getElementById('accessEmailSuccess');
    const mailtoEl = document.getElementById('accessEmailMailto');

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function hideAlerts() {
        alertEl.classList.add('d-none');
        alertEl.textContent = '';
        successEl.classList.add('d-none');
    }

    function showError(msg) {
        successEl.classList.add('d-none');
        alertEl.textContent = msg;
        alertEl.classList.remove('d-none');
    }

    function applyMailto() {
        const body = bodyEl.value;
        const q = 'mailto:' + encodeURIComponent(participantEmail)
            + '?subject=' + encodeURIComponent(subjectEl.value)
            + '&body=' + encodeURIComponent(body);
        mailtoEl.setAttribute('href', q);
    }

    function resetModal() {
        checkboxEl.checked = false;
        checkboxEl.disabled = false;
        subjectEl.value = subject;
        bodyEl.value = buildBodyPlaceholderPassword();
        hideAlerts();
        applyMailto();
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } finally {
            document.body.removeChild(ta);
        }
        return Promise.resolve();
    }

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
            resetModal();
        });
    }

    checkboxEl.addEventListener('change', async function () {
        hideAlerts();
        if (!checkboxEl.checked) {
            return;
        }

        checkboxEl.disabled = true;
        try {
            const res = await fetch(generatePasswordUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                const msg = data.message || ('Błąd ' + res.status + '. Spróbuj ponownie za chwilę.');
                showError(msg);
                checkboxEl.checked = false;
                checkboxEl.disabled = false;
                return;
            }
            if (!data.password) {
                showError('Nie udało się odczytać wygenerowanego hasła.');
                checkboxEl.checked = false;
                checkboxEl.disabled = false;
                return;
            }
            bodyEl.value = buildBodyWithPassword(data.password);
            applyMailto();
            successEl.classList.remove('d-none');
            checkboxEl.disabled = true;
        } catch (e) {
            showError('Nie udało się połączyć z serwerem. Sprawdź połączenie z internetem.');
            checkboxEl.checked = false;
            checkboxEl.disabled = false;
        }
    });

    document.getElementById('accessEmailCopySubject').addEventListener('click', function () {
        copyText(subjectEl.value).then(function () {});
    });
    document.getElementById('accessEmailCopyBody').addEventListener('click', function () {
        copyText(bodyEl.value).then(function () {});
    });
    document.getElementById('accessEmailCopyAll').addEventListener('click', function () {
        copyText('Temat: ' + subjectEl.value + '\n\n' + bodyEl.value).then(function () {});
    });
})();
</script>
@endpush

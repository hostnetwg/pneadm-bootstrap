@php
    $guestLive = $guestLive ?? ($state['guest_live'] ?? ['eligible' => false, 'url' => null, 'hint' => '']);
    $inputId = $inputId ?? 'guest-live-url';
@endphp
@if(! empty($guestLive['url']))
    <div class="{{ $wrapperClass ?? 'mb-3' }}">
        <label class="form-label fw-semibold mb-1" for="{{ $inputId }}">
            <i class="fas fa-link me-1"></i> Live bez logowania (gość)
        </label>
        <div class="input-group">
            <input type="text"
                   class="form-control form-control-sm font-monospace"
                   id="{{ $inputId }}"
                   value="{{ $guestLive['url'] }}"
                   readonly>
            <button type="button"
                    class="btn btn-outline-secondary btn-sm"
                    data-copy-target="{{ $inputId }}">
                Kopiuj link
            </button>
        </div>
        <p class="small text-muted mb-0 mt-1">{{ $guestLive['hint'] }}</p>
        <p class="small text-muted mb-0">Adres nie jest w katalogu pnedu.pl ani w sitemapie. Działa przy ClickMeeting „Dla wszystkich”, w oknie live.</p>
    </div>
    <script>
        (function () {
            var btn = document.querySelector('[data-copy-target="{{ $inputId }}"]');
            if (!btn || btn.dataset.guestLiveCopyBound) {
                return;
            }
            btn.dataset.guestLiveCopyBound = '1';
            btn.addEventListener('click', function () {
                var input = document.getElementById('{{ $inputId }}');
                if (!input) {
                    return;
                }
                navigator.clipboard.writeText(input.value).then(function () {
                    btn.textContent = 'Skopiowano!';
                    setTimeout(function () { btn.textContent = 'Kopiuj link'; }, 2000);
                });
            });
        })();
    </script>
@endif

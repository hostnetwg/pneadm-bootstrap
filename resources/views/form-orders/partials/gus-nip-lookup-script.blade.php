{{-- Wspólne: przyciski [data-gus-target] na create/edit zamówienia FORM --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var lookupUrl = @json(route('form-orders.gus-lookup'));
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';

    var fieldMap = {
        name: 'name',
        postcode: 'postal_code',
        city: 'city',
        address: 'address',
        nip: 'nip'
    };

    function setStatus(target, message, isError) {
        var el = document.getElementById(target + '-gus-status');
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.classList.toggle('text-danger', !!isError);
        el.classList.toggle('text-success', !isError && !!message);
    }

    function gusLookup(target, button) {
        var nipInput = document.getElementById(target + '_nip');
        if (!nipInput) {
            return;
        }

        var raw = (nipInput.value || '').trim();
        if (!raw) {
            nipInput.setCustomValidity('Wpisz NIP przed pobraniem danych.');
            nipInput.reportValidity();
            nipInput.setCustomValidity('');
            return;
        }

        var originalLabel = button.textContent;
        button.disabled = true;
        button.textContent = 'Pobieranie…';
        setStatus(target, 'Pobieranie danych z GUS…', false);

        fetch(lookupUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ nip: raw, target: target })
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, status: response.status, body: body };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.body || !result.body.success) {
                    var msg = (result.body && (result.body.message || (result.body.errors && result.body.errors.nip && result.body.errors.nip[0])))
                        || 'Nie udało się pobrać danych z GUS.';
                    setStatus(target, msg, true);
                    return;
                }

                var data = result.body.data || {};
                Object.keys(fieldMap).forEach(function (apiKey) {
                    var fieldId = target + '_' + fieldMap[apiKey];
                    var input = document.getElementById(fieldId);
                    if (input && data[apiKey]) {
                        input.value = data[apiKey];
                        input.classList.remove('is-invalid');
                    }
                });

                setStatus(target, 'Uzupełniono dane z GUS.', false);
            })
            .catch(function () {
                setStatus(target, 'Nie udało się pobrać danych z GUS.', true);
            })
            .finally(function () {
                button.disabled = false;
                button.textContent = originalLabel;
            });
    }

    document.querySelectorAll('[data-gus-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            gusLookup(button.getAttribute('data-gus-target'), button);
        });
    });
});
</script>

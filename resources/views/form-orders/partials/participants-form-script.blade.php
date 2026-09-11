<script>
(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var root = document.getElementById('order-form-participants-root');
        if (!root) {
            return;
        }

        var rowsWrap = document.getElementById('order-form-participant-rows');
        var addBtn = document.getElementById('order-form-add-participant');
        var template = document.getElementById('order-form-participant-row-template');
        var breakdownEl = document.getElementById('order-form-price-breakdown');
        var priceInput = document.getElementById('product_price');
        var max = parseInt(root.dataset.maxParticipants || '50', 10) || 50;
        var unitPrice = parseFloat(root.dataset.unitPrice || '');

        function rows() {
            return Array.prototype.slice.call(rowsWrap.querySelectorAll('.order-form-participant-row'));
        }

        function formatPln(n) {
            return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function reindex() {
            rows().forEach(function (row, index) {
                row.dataset.participantIndex = String(index);
                var num = row.querySelector('.js-participant-number');
                if (num) {
                    num.textContent = String(index + 1);
                }
                row.querySelectorAll('input').forEach(function (input) {
                    var name = input.getAttribute('name') || '';
                    name = name.replace(/participants\[\d+]/, 'participants[' + index + ']');
                    name = name.replace(/participants\[__INDEX__\]/, 'participants[' + index + ']');
                    input.setAttribute('name', name);
                    var id = input.getAttribute('id') || '';
                    id = id.replace(/_\d+$/, '_' + index).replace(/___INDEX__$/, '_' + index);
                    if (/participant_(first_name|last_name|email)/.test(id) || id.indexOf('__INDEX__') >= 0) {
                        input.id = id.replace('__INDEX__', String(index));
                    }
                });
                row.querySelectorAll('label[for]').forEach(function (label) {
                    var f = label.getAttribute('for') || '';
                    f = f.replace(/_\d+$/, '_' + index).replace(/___INDEX__$/, '_' + index);
                    label.setAttribute('for', f.replace('__INDEX__', String(index)));
                });
                var removeBtn = row.querySelector('.js-remove-participant');
                if (removeBtn) {
                    removeBtn.hidden = index === 0;
                }
                if (index === 0) {
                    row.querySelectorAll('[data-primary-field]').forEach(function (el) {
                        el.id = 'participant_' + el.getAttribute('data-primary-field');
                    });
                }
            });
            updateAddButton();
            recalculatePrice();
        }

        function updateAddButton() {
            if (!addBtn) {
                return;
            }
            addBtn.hidden = rows().length >= max;
        }

        function recalculatePrice() {
            var count = Math.max(1, rows().length);
            if (isNaN(unitPrice)) {
                if (breakdownEl) {
                    breakdownEl.textContent = count > 1 ? (count + ' os.') : '';
                }
                return;
            }
            var total = Math.round(unitPrice * count * 100) / 100;
            if (priceInput) {
                priceInput.value = total.toFixed(2);
            }
            if (breakdownEl) {
                breakdownEl.textContent = count > 1
                    ? (formatPln(unitPrice) + ' PLN × ' + count + ' os. = ' + formatPln(total) + ' PLN')
                    : '';
            }
        }

        function addRow() {
            if (rows().length >= max || !template) {
                return;
            }
            var html = template.innerHTML.replace(/__INDEX__/g, String(rows().length));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var node = wrap.firstElementChild;
            rowsWrap.appendChild(node);
            bindRowEvents(node);
            reindex();
            var emailInput = node.querySelector('.js-participant-email');
            if (emailInput) {
                emailInput.focus();
            }
        }

        function bindRowEvents(row) {
            var removeBtn = row.querySelector('.js-remove-participant');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (rows().length <= 1) {
                        return;
                    }
                    row.remove();
                    reindex();
                });
            }
            var emailInput = row.querySelector('.js-participant-email');
            if (emailInput) {
                emailInput.addEventListener('input', checkDuplicateEmails);
                emailInput.addEventListener('blur', checkDuplicateEmails);
            }
        }

        function checkDuplicateEmails() {
            var values = {};
            rows().forEach(function (row) {
                var input = row.querySelector('.js-participant-email');
                if (!input) {
                    return;
                }
                var email = (input.value || '').trim().toLowerCase();
                var feedback = input.parentElement.querySelector('.js-email-feedback');
                input.classList.remove('is-invalid');
                if (feedback) {
                    feedback.textContent = '';
                }
                if (email === '' || email.indexOf('@') < 0) {
                    return;
                }
                if (values[email]) {
                    input.classList.add('is-invalid');
                    if (feedback) {
                        feedback.style.display = 'block';
                        feedback.textContent = 'Ten sam adres e-mail nie może powtórzyć się na zamówieniu.';
                    }
                } else {
                    values[email] = true;
                }
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', addRow);
        }

        rows().forEach(bindRowEvents);
        reindex();

        window.formOrderParticipantsSetUnitPrice = function (price) {
            unitPrice = parseFloat(price);
            if (isNaN(unitPrice)) {
                root.dataset.unitPrice = '';
                return;
            }
            root.dataset.unitPrice = String(unitPrice);
            recalculatePrice();
        };
    });
})();
</script>

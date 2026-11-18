{{-- Wspólny JS: przełącznik training/product + TomSelect + wariant ceny --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const initialKind = @json($initialOrderKind ?? 'training');
        const courseSearchUrl = @json($courseSearchUrl);
        const catalogProductSearchUrl = @json($catalogProductSearchUrl);
        const pricesUrlTemplate = @json($catalogProductPricesUrlTemplate);
        const coursePreselected = @json($courseSelectPreselected);
        const catalogPreselected = @json($catalogProductSelectPreselected);
        const initialPriceId = @json($selectedProductPriceId ? (string) $selectedProductPriceId : '');
        const showCourseInfo = @json((bool) ($showCourseInfo ?? false));

        const priceInput = document.getElementById('product_price');
        const nameInput = document.getElementById('product_name');
        const selectError = document.getElementById('course-select-error');
        const trainingFields = document.getElementById('training-product-fields');
        const onlineFields = document.getElementById('online-product-fields');
        const courseSelect = document.getElementById('course_id');
        const catalogSelect = document.getElementById('catalog_product_id');
        const priceSelect = document.getElementById('product_price_id');
        const kindRadios = document.querySelectorAll('input[name="order_kind"]');
        const courseInfo = document.getElementById('course-info');
        const courseDetails = document.getElementById('course-details');
        const archivedToggle = document.getElementById('course_include_archived');

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderCourseInfo(item) {
            if (!showCourseInfo || !courseInfo || !courseDetails) {
                return;
            }
            if (!item) {
                courseInfo.style.display = 'none';
                return;
            }
            const instructor = item.instructor ? escapeHtml(item.instructor) : '<span class="text-muted">—</span>';
            courseDetails.innerHTML =
                '<div class="row g-2">' +
                    '<div class="col-md-6"><strong>Tytuł:</strong> ' + escapeHtml(item.title_text || '') + '</div>' +
                    '<div class="col-md-6"><strong>Data rozpoczęcia:</strong> ' + (item.start_date ? '[' + escapeHtml(item.start_date) + ']' : '—') + '</div>' +
                    '<div class="col-md-6"><strong>Prowadzący:</strong> ' + instructor + '</div>' +
                '</div>';
            courseInfo.style.display = 'block';
        }

        function applyUnitPrice(amount, flat) {
            if (amount === null || amount === undefined || !priceInput) {
                return;
            }
            priceInput.value = amount;
            if (typeof window.formOrderParticipantsSetUnitPrice === 'function') {
                window.formOrderParticipantsSetUnitPrice(amount, !!flat);
            }
        }

        function fillPriceOptions(prices, preferredId) {
            if (!priceSelect) {
                return;
            }
            const current = preferredId || priceSelect.value || '';
            priceSelect.innerHTML = '<option value="">— wybierz wariant —</option>';
            (prices || []).forEach(function (price) {
                const option = document.createElement('option');
                option.value = String(price.id);
                option.textContent = price.label || (price.name + ' — ' + price.amount + ' PLN');
                option.dataset.amount = price.amount;
                if (String(price.id) === String(current)) {
                    option.selected = true;
                }
                priceSelect.appendChild(option);
            });
            if (!priceSelect.value && prices && prices.length) {
                priceSelect.value = String(prices[0].id);
            }
            const selected = priceSelect.options[priceSelect.selectedIndex];
            if (selected && selected.dataset.amount) {
                applyUnitPrice(selected.dataset.amount, false);
            }
        }

        function loadPricesForProduct(productId, preferredId, fallbackPrices) {
            if (!productId) {
                fillPriceOptions([], preferredId);
                return;
            }
            if (fallbackPrices && fallbackPrices.length) {
                fillPriceOptions(fallbackPrices, preferredId);
            }
            const url = pricesUrlTemplate.replace('__ID__', encodeURIComponent(productId));
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (payload) {
                    fillPriceOptions(payload.prices || [], preferredId || initialPriceId);
                })
                .catch(function () {
                    if (!fallbackPrices || !fallbackPrices.length) {
                        fillPriceOptions([], preferredId);
                    }
                });
        }

        let courseTs = null;
        let catalogTs = null;

        function setNativeDisabled(el, disabled) {
            if (!el) {
                return;
            }
            el.disabled = !!disabled;
            if (disabled) {
                el.setAttribute('disabled', 'disabled');
            } else {
                el.removeAttribute('disabled');
            }
        }

        function setTomSelectEnabled(ts, enabled) {
            if (!ts) {
                return;
            }
            if (enabled) {
                if (typeof ts.enable === 'function') {
                    ts.enable();
                }
            } else if (typeof ts.disable === 'function') {
                ts.disable();
            }
        }

        function setKind(kind) {
            const isProduct = kind === 'product';
            if (trainingFields) {
                trainingFields.classList.toggle('d-none', isProduct);
            }
            if (onlineFields) {
                onlineFields.classList.toggle('d-none', !isProduct);
            }

            setNativeDisabled(courseSelect, isProduct);
            setNativeDisabled(catalogSelect, !isProduct);
            setNativeDisabled(priceSelect, !isProduct);
            setTomSelectEnabled(courseTs, !isProduct);
            setTomSelectEnabled(catalogTs, isProduct);

            if (courseSelect) {
                if (isProduct) {
                    courseSelect.removeAttribute('required');
                } else {
                    courseSelect.setAttribute('required', 'required');
                }
            }
            if (catalogSelect) {
                if (isProduct) {
                    catalogSelect.setAttribute('required', 'required');
                } else {
                    catalogSelect.removeAttribute('required');
                }
            }
            if (priceSelect) {
                if (isProduct) {
                    priceSelect.setAttribute('required', 'required');
                } else {
                    priceSelect.removeAttribute('required');
                }
            }
            if (!isProduct) {
                renderCourseInfo(coursePreselected);
            } else if (courseInfo) {
                courseInfo.style.display = 'none';
            }
        }

        kindRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) {
                    setKind(radio.value);
                }
            });
        });

        if (priceSelect) {
            priceSelect.addEventListener('change', function () {
                const selected = priceSelect.options[priceSelect.selectedIndex];
                if (selected && selected.dataset.amount) {
                    applyUnitPrice(selected.dataset.amount, false);
                }
            });
        }

        // Widoczność pól od razu; enable/disable TomSelect dopiero po init.
        setKind(initialKind);

        function boot(initFn) {
            if (!initFn) {
                if (selectError) {
                    selectError.classList.remove('d-none');
                }
                return;
            }

            const STORAGE_KEY = 'formOrders.courseSelect.includeArchived';
            let includeArchived = false;
            try {
                includeArchived = window.localStorage.getItem(STORAGE_KEY) === '1';
            } catch (e) {}
            if (archivedToggle) {
                archivedToggle.checked = includeArchived;
            }

            // Init przy chwilowo włączonych selectach — TomSelect inaczej startuje
            // „na zawsze” disabled, gdy HTML ma disabled przy starcie.
            setNativeDisabled(courseSelect, false);
            setNativeDisabled(catalogSelect, false);

            courseTs = initFn('course_id', {
                searchUrl: courseSearchUrl,
                preselected: coursePreselected,
                includeArchived: includeArchived,
                onCourseChanged: function (item) {
                    renderCourseInfo(item);
                    if (item && item.default_price !== null && item.default_price !== undefined) {
                        applyUnitPrice(item.default_price, item.default_price_flat);
                    }
                },
            });

            if (courseTs && coursePreselected) {
                renderCourseInfo(coursePreselected);
            }
            if (courseTs && archivedToggle) {
                archivedToggle.addEventListener('change', function () {
                    const checked = !!archivedToggle.checked;
                    try { window.localStorage.setItem(STORAGE_KEY, checked ? '1' : '0'); } catch (e) {}
                    if (typeof courseTs.setIncludeArchived === 'function') {
                        courseTs.setIncludeArchived(checked);
                    }
                });
            }

            catalogTs = initFn('catalog_product_id', {
                searchUrl: catalogProductSearchUrl,
                preselected: catalogPreselected,
                placeholder: 'Wybierz lub wpisz tytuł / ID kursu online...',
                onCourseChanged: function (item) {
                    if (!item) {
                        return;
                    }
                    if (item.title_text && nameInput) {
                        nameInput.value = item.title_text;
                    }
                    loadPricesForProduct(
                        item.id,
                        item.default_price_id || initialPriceId,
                        item.prices || []
                    );
                },
            });

            if (catalogPreselected && catalogPreselected.id) {
                loadPricesForProduct(
                    catalogPreselected.id,
                    catalogPreselected.default_price_id || initialPriceId,
                    catalogPreselected.prices || []
                );
            }

            setKind(initialKind);
        }

        window.ensureCourseSelectInit().then(boot).catch(function () {
            boot(null);
        });
    });
</script>

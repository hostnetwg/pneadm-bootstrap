(function () {
    'use strict';

    var TOM_SELECT_VERSION = '2.4.3';
    var TOM_SELECT_CDN = 'https://cdn.jsdelivr.net/npm/tom-select@' + TOM_SELECT_VERSION + '/dist';
    var loadingPromise = null;

    function statusToTitleClass(status) {
        switch (status) {
            case 'upcoming':
                return 'text-primary';
            case 'ongoing':
                return 'text-danger';
            case 'unknown':
                return 'text-muted';
            default:
                return '';
        }
    }

    function statusBadgeHtml(status) {
        switch (status) {
            case 'upcoming':
                return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Nadchodzące</span>';
            case 'ongoing':
                return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Trwa</span>';
            case 'archived':
                return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Archiwalne</span>';
            default:
                return '';
        }
    }

    function normalizeCourseSearchItems(data) {
        if (Array.isArray(data)) {
            return data;
        }

        if (data && Array.isArray(data.items)) {
            return data.items;
        }

        return [];
    }

    function loadStylesheet(href) {
        if (document.querySelector('link[href="' + href + '"]')) {
            return Promise.resolve();
        }

        return new Promise(function (resolve, reject) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = function () { resolve(); };
            link.onerror = function () { reject(new Error('Stylesheet load failed')); };
            document.head.appendChild(link);
        });
    }

    function loadScript(src) {
        if (document.querySelector('script[src="' + src + '"]')) {
            return Promise.resolve();
        }

        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('Script load failed')); };
            document.head.appendChild(script);
        });
    }

    function ensureTomSelectLoaded() {
        if (window.TomSelect) {
            return Promise.resolve();
        }

        if (loadingPromise) {
            return loadingPromise;
        }

        loadingPromise = loadStylesheet(TOM_SELECT_CDN + '/css/tom-select.bootstrap5.min.css')
            .then(function () {
                return loadScript(TOM_SELECT_CDN + '/js/tom-select.complete.min.js');
            })
            .then(function () {
                if (!window.TomSelect) {
                    throw new Error('TomSelect unavailable after CDN load');
                }
            });

        return loadingPromise;
    }

    function createInitCourseSelect(TomSelect) {
        return function initCourseSelect(selectId, options) {
            var el = document.getElementById(selectId);
            if (!el || el.dataset.tomselectInit === '1') {
                return null;
            }
            el.dataset.tomselectInit = '1';

            options = options || {};
            var initialValue = options.preselected && options.preselected.id ? String(options.preselected.id) : '';
            var lastReportedValue = initialValue;
            var includeArchived = !!options.includeArchived;

            var settings = {
                valueField: 'value',
                labelField: 'title_text',
                searchField: ['title_text', 'id_old', 'value', 'id_hash', 'instructor'],
                maxOptions: 50,
                placeholder: options.placeholder || 'Wybierz lub wpisz tytuł / ID szkolenia...',
                plugins: ['clear_button'],
                preload: 'focus',
                load: function (query, callback) {
                    this.clearOptions();
                    var url = options.searchUrl
                        + '?q=' + encodeURIComponent(query || '')
                        + '&include_archived=' + (includeArchived ? '1' : '0');

                    fetch(url, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Course search HTTP ' + response.status);
                            }
                            return response.json();
                        })
                        .then(function (data) {
                            callback(normalizeCourseSearchItems(data));
                        })
                        .catch(function () {
                            callback();
                        });
                },
                render: {
                    option: function (item, escape) {
                        var title = item.title_text || '';
                        var idOld = item.id_old ? ' · Publigo ID: ' + escape(item.id_old) : '';
                        var startDate = item.start_date ? ' [' + escape(item.start_date) + ']' : '';
                        var instructor = item.instructor
                            ? '<div class="small text-muted"><i class="bi bi-person-badge"></i> ' + escape(item.instructor) + '</div>'
                            : '';
                        var titleClass = statusToTitleClass(item.status);
                        var statusBadge = statusBadgeHtml(item.status);
                        var certBadge = item.certificate_registration_open
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Rej. wł.</span>'
                            : '';

                        return ''
                            + '<div class="py-1">'
                            + '<div class="d-flex align-items-start gap-2 flex-wrap">'
                            + '<div class="fw-semibold ' + titleClass + '">' + escape(title) + '</div>'
                            + statusBadge
                            + certBadge
                            + '</div>'
                            + '<div class="small text-muted">#' + escape(item.id) + idOld + startDate + '</div>'
                            + instructor
                            + '</div>';
                    },
                    item: function (item, escape) {
                        var startDate = item.start_date ? ' [' + escape(item.start_date) + ']' : '';
                        var titleClass = statusToTitleClass(item.status);
                        return '<div class="' + titleClass + '">#' + escape(item.id) + ' · ' + escape(item.title_text || '') + startDate + '</div>';
                    },
                    no_results: function () {
                        return '<div class="no-results small text-muted px-2 py-1">Brak wyników.</div>';
                    },
                },
                onChange: function (value) {
                    var normalized = value == null ? '' : String(value);
                    if (normalized === lastReportedValue) {
                        return;
                    }
                    lastReportedValue = normalized;
                    if (typeof options.onCourseChanged !== 'function') {
                        return;
                    }
                    var item = normalized && this.options ? (this.options[normalized] || null) : null;
                    options.onCourseChanged(item);
                },
            };

            if (options.preselected && options.preselected.id) {
                var pre = options.preselected;
                settings.options = [{
                    value: String(pre.id),
                    id: pre.id,
                    id_hash: '#' + pre.id,
                    id_old: pre.id_old || '',
                    title_text: pre.title_text || ('Kurs #' + pre.id),
                    start_date: pre.start_date || '',
                    end_date: pre.end_date || '',
                    status: pre.status || '',
                    instructor: pre.instructor || '',
                    default_price: pre.default_price != null ? pre.default_price : null,
                }];
                settings.items = [String(pre.id)];
            }

            var ts = new TomSelect('#' + selectId, settings);
            var originalLoad = ts.load.bind(ts);
            ts.load = function (value) {
                delete this.loadedSearches[value];
                return originalLoad(value);
            };

            ts.setIncludeArchived = function (value) {
                var next = !!value;
                if (next === includeArchived) {
                    return;
                }
                includeArchived = next;
                ts.clearOptions();
                ts.load('');
            };

            return ts;
        };
    }

    window.ensureCourseSelectFallback = function () {
        if (typeof window.initCourseSelect === 'function') {
            return Promise.resolve(window.initCourseSelect);
        }

        return ensureTomSelectLoaded().then(function () {
            if (typeof window.initCourseSelect !== 'function') {
                window.initCourseSelect = createInitCourseSelect(window.TomSelect);
            }

            return window.initCourseSelect;
        });
    };
})();

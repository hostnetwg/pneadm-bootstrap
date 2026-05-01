import TomSelect from 'tom-select';
import 'tom-select/dist/js/plugins/clear_button.js';

/**
 * Mapuje status cyklu życia kursu na klasę CSS koloru tytułu w dropdownie.
 *  - upcoming → niebieski (text-primary)
 *  - ongoing  → czerwony  (text-danger)
 *  - archived → domyślny czarny
 *  - unknown  → wyciszony
 */
function statusToTitleClass(status) {
    switch (status) {
        case 'upcoming':
            return 'text-primary';
        case 'ongoing':
            return 'text-danger';
        case 'unknown':
            return 'text-muted';
        case 'archived':
        default:
            return '';
    }
}

/**
 * Mały badge informujący o statusie kursu obok tytułu (dla czytelności listy).
 */
function statusBadgeHtml(status) {
    switch (status) {
        case 'upcoming':
            return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Nadchodzące</span>';
        case 'ongoing':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Trwa</span>';
        case 'archived':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Archiwalne</span>';
        case 'unknown':
        default:
            return '';
    }
}

/**
 * Inicjalizuje TomSelect na <select> wyboru szkolenia z wyszukiwaniem AJAX.
 *
 * @param {string} selectId - id elementu <select> (np. "course_id")
 * @param {object} options
 * @param {string} options.searchUrl - URL endpointu zwracającego JSON {items: [...]}
 * @param {string} [options.placeholder] - placeholder dla pola wyszukiwania
 * @param {object} [options.preselected] - opcjonalnie obiekt {id, title_text, id_old, start_date, instructor} do prefill
 * @param {function} [options.onCourseChanged] - callback wywoływany przy realnej zmianie wyboru: (item|null)
 */
export function initCourseSelect(selectId, options) {
    const el = document.getElementById(selectId);
    if (!el || el.dataset.tomselectInit === '1') {
        return null;
    }
    el.dataset.tomselectInit = '1';

    const initialValue = options.preselected && options.preselected.id ? String(options.preselected.id) : '';
    let lastReportedValue = initialValue;
    let includeArchived = !!options.includeArchived;

    const settings = {
        valueField: 'value',
        labelField: 'title_text',
        searchField: ['title_text', 'id_old', 'value', 'instructor'],
        maxOptions: 50,
        placeholder: options.placeholder || 'Wybierz lub wpisz tytuł / ID szkolenia...',
        plugins: {
            clear_button: {
                title: 'Wyczyść wybór',
                className: 'clear-button',
            },
        },
        // Lista nadchodzących i trwających kursów ładuje się od razu po fokusie
        // — dla większości operatorów to wystarcza bez wpisywania czegokolwiek.
        preload: 'focus',
        load: function (query, callback) {
            const url = options.searchUrl
                + '?q=' + encodeURIComponent(query || '')
                + '&include_archived=' + (includeArchived ? '1' : '0');
            fetch(url, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((data) => callback(data.items || []))
                .catch(() => callback());
        },
        render: {
            option: function (item, escape) {
                const title = item.title_text || '';
                const idOld = item.id_old ? ' · Publigo ID: ' + escape(item.id_old) : '';
                const startDate = item.start_date ? ' [' + escape(item.start_date) + ']' : '';
                const instructor = item.instructor
                    ? '<div class="small text-muted"><i class="bi bi-person-badge"></i> ' + escape(item.instructor) + '</div>'
                    : '';
                const titleClass = statusToTitleClass(item.status);
                const statusBadge = statusBadgeHtml(item.status);
                return (
                    '<div class="py-1">' +
                    '<div class="d-flex align-items-start gap-2">' +
                        '<div class="fw-semibold ' + titleClass + '">' + escape(title) + '</div>' +
                        statusBadge +
                    '</div>' +
                    '<div class="small text-muted">#' + escape(item.id) + idOld + startDate + '</div>' +
                    instructor +
                    '</div>'
                );
            },
            item: function (item, escape) {
                const startDate = item.start_date ? ' [' + escape(item.start_date) + ']' : '';
                const titleClass = statusToTitleClass(item.status);
                return '<div class="' + titleClass + '">#' + escape(item.id) + ' · ' + escape(item.title_text || '') + startDate + '</div>';
            },
            no_results: function () {
                return '<div class="no-results small text-muted px-2 py-1">Brak wyników.</div>';
            },
        },
        onChange: function (value) {
            const normalized = value == null ? '' : String(value);
            if (normalized === lastReportedValue) {
                return;
            }
            lastReportedValue = normalized;
            if (typeof options.onCourseChanged !== 'function') {
                return;
            }
            const item = normalized && this.options ? (this.options[normalized] || null) : null;
            options.onCourseChanged(item);
        },
    };

    if (options.preselected && options.preselected.id) {
        const pre = options.preselected;
        settings.options = [{
            value: String(pre.id),
            id: pre.id,
            id_old: pre.id_old || '',
            title_text: pre.title_text || ('Kurs #' + pre.id),
            start_date: pre.start_date || '',
            end_date: pre.end_date || '',
            status: pre.status || '',
            instructor: pre.instructor || '',
            default_price: pre.default_price ?? null,
        }];
        settings.items = [String(pre.id)];
    }

    const ts = new TomSelect('#' + selectId, settings);

    // Pozwala przełączać widoczność archiwalnych z poziomu UI (checkbox).
    // Domyślnie clearOptions() w TomSelect zachowuje aktualnie wybrany item,
    // więc przełączenie nie psuje preselekcji.
    ts.setIncludeArchived = function (value) {
        const next = !!value;
        if (next === includeArchived) {
            return;
        }
        includeArchived = next;
        ts.clearOptions();
        ts.load('');
    };

    return ts;
}

window.initCourseSelect = initCourseSelect;

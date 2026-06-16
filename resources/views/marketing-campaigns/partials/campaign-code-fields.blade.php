@php
    $isCreate = $isCreate ?? false;
    $nextCampaignCode = $nextCampaignCode ?? null;
    $campaignCodeValue = old('campaign_code', $campaignCodeDefault ?? '');
@endphp

<div class="mb-0" id="campaign-code-field">
    <label for="campaign_code" class="form-label fw-semibold">
        Kod kampanii
        <span class="text-muted fw-normal small">· <code>utm_campaign</code>, <code>fb</code></span>
        <span class="text-danger">*</span>
    </label>
    <div class="input-group">
        <input type="text"
               class="form-control font-monospace @error('campaign_code') is-invalid @enderror"
               id="campaign_code"
               name="campaign_code"
               value="{{ $campaignCodeValue }}"
               maxlength="50"
               placeholder="np. {{ now()->format('Y-m') }}_515-dyrektor"
               required
               autocomplete="off">
        @if($isCreate)
            <button type="button"
                    class="btn btn-outline-primary"
                    id="btn-suggest-campaign-code"
                    title="Z daty szkolenia i nazwy lub tytułu">
                <i class="bi bi-magic"></i> Zaproponuj
            </button>
        @endif
    </div>
    @error('campaign_code')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if($isCreate)
        <x-campaign-field-hint summary="Zalecany format: RRRR-MM_{id}-{słowo} (max 50 znaków) — krótki link /l/… do sociali.">
            Trafia do linku jako <code>utm_campaign</code> i legacy <code>fb</code>.
            Przykłady: <code>{{ now()->format('Y-m') }}_515-dyrektor</code>,
            <code>{{ now()->format('Y-m') }}_527-ai</code>.
            Przycisk <strong>Zaproponuj</strong> bierze miesiąc szkolenia, ID kursu i jedno słowo z tytułu.
            Druga wysyłka w miesiącu: sufiks <code>-reminder</code> lub <code>-2</code>.
            @if(filled($nextCampaignCode))
                Starsze kampanie: numery (<code>{{ $nextCampaignCode }}</code>) —
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="btn-use-legacy-campaign-code">użyj {{ $nextCampaignCode }}</button>.
            @endif
        </x-campaign-field-hint>
    @else
        <x-campaign-field-hint summary="Identyfikator techniczny w linkach i zamówieniach (fb_source).">
            <span class="text-warning-emphasis">
                <i class="bi bi-exclamation-triangle"></i>
                Po publikacji linków <strong>nie zmieniaj</strong> kodu — stare URL-e i atrybucja zostaną przy poprzedniej wartości.
            </span>
        </x-campaign-field-hint>
    @endif
</div>

@if($isCreate)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const codeInput = document.getElementById('campaign_code');
        const nameInput = document.getElementById('name');
        const suggestBtn = document.getElementById('btn-suggest-campaign-code');
        const legacyBtn = document.getElementById('btn-use-legacy-campaign-code');
        let lastCourseItem = null;

        if (!codeInput) {
            return;
        }

        const polishMap = {
            ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z',
        };

        const slugStopwords = new Set([
            'jako', 'oto', 'dla', 'oraz', 'jest', 'twoj', 'twoja', 'jak', 'czy', 'nie',
            'abc', 'twoje', 'twoim', 'przez', 'przy', 'tego', 'tej', 'tym', 'aby', 'czyli',
        ]);

        const weakLeadWords = new Set([
            'zaczynasz', 'zapraszamy', 'zapraszam', 'nowe', 'nowy', 'nowa', 'szkolenie',
            'webinar', 'kurs', 'warsztat', 'warsztaty', 'zapisy', 'zapisz',
        ]);

        function slugify(text) {
            return String(text || '')
                .toLowerCase()
                .replace(/[ąćęłńóśźż]/g, function (ch) {
                    return polishMap[ch] || ch;
                })
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .replace(/-{2,}/g, '-');
        }

        function monthFromCourse(item) {
            if (!item || !item.start_date) {
                return null;
            }
            const match = String(item.start_date).match(/(\d{4})-(\d{2})/);
            if (!match) {
                return null;
            }
            return match[1] + '-' + match[2];
        }

        function currentYearMonth() {
            const now = new Date();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            return now.getFullYear() + '-' + month;
        }

        /** Krótkie słowo z tytułu (nie cały slug) — link /l/{kod} ma być zwięzły. */
        function shortKeywordFromText(text, maxLen) {
            const parts = slugify(text)
                .split('-')
                .filter(function (part) {
                    return part.length >= 3 && !slugStopwords.has(part);
                });

            let keyword = parts.length > 0 ? parts[0] : slugify(text).split('-')[0] || 'kampania';

            if (parts.length > 1 && weakLeadWords.has(keyword)) {
                keyword = parts[1];
            } else if (parts.length > 1 && keyword.length < 5) {
                keyword = parts.slice(0, 2).join('-');
            }

            if (keyword.length > maxLen) {
                keyword = keyword.substring(0, maxLen).replace(/-+$/g, '');
            }

            return keyword || 'kampania';
        }

        function buildSuggestedCode() {
            const month = monthFromCourse(lastCourseItem) || currentYearMonth();
            const nameText = (nameInput && nameInput.value ? nameInput.value.trim() : '');
            const courseTitle = lastCourseItem && lastCourseItem.title_text
                ? String(lastCourseItem.title_text).trim()
                : '';
            const sourceText = nameText || courseTitle || 'kampania';
            const courseId = lastCourseItem && lastCourseItem.id
                ? String(lastCourseItem.id)
                : '';

            const prefix = month + '_';

            if (courseId) {
                const keywordMax = Math.max(4, 50 - prefix.length - courseId.length - 1);
                const keyword = shortKeywordFromText(sourceText, Math.min(14, keywordMax));

                return prefix + courseId + '-' + keyword;
            }

            let slug = shortKeywordFromText(sourceText, Math.max(4, 50 - prefix.length));

            return prefix + slug;
        }

        function applySuggestedCode() {
            codeInput.value = buildSuggestedCode();
            codeInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        document.addEventListener('pne:campaign-course-changed', function (event) {
            lastCourseItem = event.detail && event.detail.item ? event.detail.item : null;
        });

        if (suggestBtn) {
            suggestBtn.addEventListener('click', applySuggestedCode);
        }

        if (legacyBtn) {
            legacyBtn.addEventListener('click', function () {
                const legacyCode = @json($nextCampaignCode);
                if (!legacyCode) {
                    return;
                }
                codeInput.value = legacyCode;
                codeInput.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }
    });
    </script>
    @endpush
@endif

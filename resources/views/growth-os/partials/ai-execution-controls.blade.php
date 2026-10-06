{{-- Compact local AI model / reasoning / web-search override (DEC-052, DEC-053). --}}
@php
    use App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog;
    use App\Support\GrowthOS\GrowthAiRequestOptions;

    $aiChannel = $aiChannel ?? GrowthAiModelCatalog::CHANNEL_GENERAL;
    $aiProposal = $aiProposal ?? null;
    $aiControlId = $aiControlId ?? ('ai-exec-'.uniqid());
    $aiDefaults = GrowthAiRequestOptions::uiDefaults($aiChannel, is_array($aiProposal) ? $aiProposal : null);
    if (is_bool($aiWebSearchDefault ?? null)) {
        $aiDefaults['web_search'] = $aiWebSearchDefault;
    }
    $aiModels = GrowthAiModelCatalog::models();
    $aiEfforts = GrowthAiModelCatalog::effortLabels();
    $aiSelectedModel = old('ai_model', $aiDefaults['model']);
    $aiSelectedEffort = old('ai_reasoning_effort', $aiDefaults['reasoning_effort']);
    $aiWebSearchChecked = old(GrowthAiRequestOptions::WEB_SEARCH_FIELD, $aiDefaults['web_search'] ? '1' : '0') === '1';
    $aiSimulation = config('growth_ai.enabled') !== true;
@endphp
<div
    class="growth-ai-exec border rounded px-2 py-2 mb-3 bg-light"
    data-growth-ai-exec
    data-default-model="{{ $aiDefaults['model'] }}"
    data-default-effort="{{ $aiDefaults['reasoning_effort'] }}"
    data-default-web-search="{{ $aiDefaults['web_search'] ? '1' : '0' }}"
>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <p class="small mb-0 text-secondary">
            AI:
            <span class="fw-semibold text-body" data-growth-ai-exec-summary>
                {{ GrowthAiModelCatalog::compactLabel((string) $aiSelectedModel, (string) $aiSelectedEffort) }}
            </span>
            <span class="badge bg-white text-secondary border ms-1 @if(! $aiWebSearchChecked) d-none @endif" data-growth-ai-exec-search-badge>Sieć</span>
            @if($aiSimulation)
                <span class="badge bg-white text-secondary border ms-1">Symulacja lokalna — model nie został wywołany</span>
            @endif
        </p>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $aiControlId }}" aria-expanded="false" aria-controls="{{ $aiControlId }}">
            ⚙
            <span class="visually-hidden">Ustawienia modelu AI</span>
        </button>
    </div>
    <div class="collapse mt-2" id="{{ $aiControlId }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1" for="{{ $aiControlId }}-model">Model</label>
                <select name="ai_model" id="{{ $aiControlId }}-model" class="form-select form-select-sm" data-growth-ai-exec-model @disabled($aiDisabled ?? false)>
                    @foreach($aiModels as $model)
                        <option
                            value="{{ $model['id'] }}"
                            @selected($aiSelectedModel === $model['id'])
                            data-web-search="{{ ($model['capabilities']['web_search'] ?? false) ? '1' : '0' }}"
                        >
                            {{ $model['label'] }} · {{ $model['cost_tier'] }} — {{ $model['description'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1" for="{{ $aiControlId }}-effort">Wysiłek</label>
                <select name="ai_reasoning_effort" id="{{ $aiControlId }}-effort" class="form-select form-select-sm" data-growth-ai-exec-effort @disabled($aiDisabled ?? false)>
                    @foreach($aiEfforts as $effortValue => $effortLabel)
                        <option value="{{ $effortValue }}" @selected($aiSelectedEffort === $effortValue)>{{ $effortLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-growth-ai-exec-reset @disabled($aiDisabled ?? false)>
                    Domyślne
                </button>
            </div>
        </div>
        <div class="form-check mt-2 mb-0">
            <input type="hidden" name="{{ GrowthAiRequestOptions::WEB_SEARCH_FIELD }}" value="0">
            <input
                class="form-check-input"
                type="checkbox"
                name="{{ GrowthAiRequestOptions::WEB_SEARCH_FIELD }}"
                id="{{ $aiControlId }}-web-search"
                value="1"
                data-growth-ai-exec-web-search
                @checked($aiWebSearchChecked)
                @disabled($aiDisabled ?? false)
            >
            <label class="form-check-label small" for="{{ $aiControlId }}-web-search">Wyszukiwanie w sieci</label>
        </div>
        <p class="form-text mb-0 mt-2">Lokalny wybór (model, wysiłek, sieć) dotyczy tylko tego requestu i nie zmienia globalnych ustawień. Jeśli sieć nie zadziała, dostaniesz informację przy propozycji.</p>
    </div>
</div>
<script>
(() => {
    const root = document.currentScript?.previousElementSibling;
    if (!root || root.dataset.growthAiExecBound === '1') return;
    root.dataset.growthAiExecBound = '1';
    const summary = root.querySelector('[data-growth-ai-exec-summary]');
    const model = root.querySelector('[data-growth-ai-exec-model]');
    const effort = root.querySelector('[data-growth-ai-exec-effort]');
    const search = root.querySelector('[data-growth-ai-exec-web-search]');
    const searchBadge = root.querySelector('[data-growth-ai-exec-search-badge]');
    const reset = root.querySelector('[data-growth-ai-exec-reset]');
    const labels = @json($aiEfforts);
    const modelLabels = @json(collect($aiModels)->mapWithKeys(fn ($m) => [$m['id'] => $m['label']]));
    const sync = () => {
        if (!summary || !model || !effort) return;
        const modelLabel = modelLabels[model.value] || model.value;
        const effortLabel = labels[effort.value] || effort.value;
        summary.textContent = modelLabel + ' · ' + effortLabel;
        if (searchBadge) {
            searchBadge.classList.toggle('d-none', !(search?.checked));
        }
    };
    model?.addEventListener('change', sync);
    effort?.addEventListener('change', sync);
    search?.addEventListener('change', sync);
    reset?.addEventListener('click', () => {
        model.value = root.dataset.defaultModel;
        effort.value = root.dataset.defaultEffort;
        if (search) {
            search.checked = root.dataset.defaultWebSearch === '1';
        }
        sync();
    });
    sync();
})();
</script>

<section id="system-prompts" class="card border mb-4" aria-labelledby="system-prompts-heading">
    <div class="card-header bg-white">
        <h2 class="h5 mb-1" id="system-prompts-heading">Prompty systemowe do API</h2>
        <p class="text-secondary small mb-2">
            To jest instrukcja wysyłana do API. Dane projektu (temat, pola, szkice) idą osobno, w JSON.
            Zapis podmienia prompt dla wszystkich projektów. Przywrócenie wraca do wersji z kodu.
            Gdy wyszukiwanie w sieci jest włączone, pod instrukcją dokleja się akapit poniżej. Przy grafice miejsca w nawiasach klamrowych dostają wartości z karty.
        </p>
        <p class="small mb-1">Akapit dopisywany przy włączonym wyszukiwaniu:</p>
        <textarea class="form-control font-monospace small" rows="3" readonly>{{ trim(\App\Services\GrowthOS\AI\GrowthAiService::WEB_SEARCH_APPENDIX) }}</textarea>
    </div>
    <div class="card-body">
        <div class="accordion" id="growth-system-prompts">
            @foreach($systemPrompts as $stage)
                @php
                    $stageOpen = collect($stage['prompts'])->contains(
                        fn (array $prompt): bool => $prompt['key'] === session('prompt_key') || $prompt['key'] === old('prompt_key')
                    );
                @endphp
                <div class="accordion-item">
                    <h3 class="accordion-header" id="growth-prompt-heading-{{ $stage['id'] }}">
                        <button
                            class="accordion-button {{ $stageOpen ? '' : 'collapsed' }}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#growth-prompt-{{ $stage['id'] }}"
                            aria-expanded="{{ $stageOpen ? 'true' : 'false' }}"
                            aria-controls="growth-prompt-{{ $stage['id'] }}"
                        >
                            {{ $stage['label'] }}
                        </button>
                    </h3>
                    <div
                        id="growth-prompt-{{ $stage['id'] }}"
                        class="accordion-collapse collapse {{ $stageOpen ? 'show' : '' }}"
                        aria-labelledby="growth-prompt-heading-{{ $stage['id'] }}"
                        data-bs-parent="#growth-system-prompts"
                    >
                        <div class="accordion-body">
                            @foreach($stage['prompts'] as $prompt)
                                <form method="post" action="{{ route('growth.prompts.update', $prompt['key']) }}" id="prompt-{{ $prompt['key'] }}" class="mb-4">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="prompt_key" value="{{ $prompt['key'] }}">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <h4 class="h6 mb-0">{{ $prompt['label'] }}</h4>
                                        <span class="badge text-bg-light border">{{ $prompt['version'] }}</span>
                                        @if($prompt['customized'])
                                            <span class="badge text-bg-warning">Własna treść</span>
                                        @endif
                                    </div>
                                    @if($prompt['placeholders'] !== '')
                                        <p class="small text-secondary mb-2">{{ $prompt['placeholders'] }}</p>
                                    @endif
                                    <textarea
                                        class="form-control font-monospace small @error('body') {{ old('prompt_key') === $prompt['key'] ? 'is-invalid' : '' }} @enderror"
                                        name="body"
                                        rows="14"
                                        required
                                        maxlength="20000"
                                    >{{ old('prompt_key') === $prompt['key'] ? old('body') : $prompt['body'] }}</textarea>
                                    @error('body')
                                        @if(old('prompt_key') === $prompt['key'])
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @endif
                                    @enderror
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <button type="submit" class="btn btn-primary btn-sm">Zapisz ten prompt</button>
                                        @if($prompt['customized'])
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#growth-prompt-reset"
                                                data-prompt-reset="{{ route('growth.prompts.reset', $prompt['key']) }}"
                                                data-prompt-label="{{ $prompt['label'] }}"
                                            >
                                                Przywróć wersję z kodu
                                            </button>
                                        @endif
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<div class="modal fade" id="growth-prompt-reset" tabindex="-1" aria-labelledby="growth-prompt-reset-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="growth-prompt-reset-form">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="growth-prompt-reset-title">Przywrócić prompt z kodu?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Własna treść „<span id="growth-prompt-reset-label"></span>” zostanie usunięta. API znów dostanie wersję z kodu, we wszystkich projektach.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                    <button type="submit" class="btn btn-danger">Przywróć wersję z kodu</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('[data-prompt-reset]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = document.getElementById('growth-prompt-reset-form');
        form.action = button.dataset.promptReset;
        document.getElementById('growth-prompt-reset-label').textContent = button.dataset.promptLabel || '';
    });
});
</script>

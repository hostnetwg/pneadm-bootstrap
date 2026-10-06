<x-app-layout>
    <x-slot name="header">
        Ustawienia AI — PNE Rozwój
    </x-slot>

    <div class="container-fluid py-4" style="max-width: 720px">
        <nav class="small mb-3" aria-label="Okruszki">
            <a href="{{ route('growth.dashboard') }}">PNE Rozwój</a>
            <span class="text-secondary">/</span>
            <span>Ustawienia AI</span>
        </nav>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border mb-4">
            <div class="card-body">
                <h1 class="h4 mb-3">Ustawienia AI</h1>
                <p class="text-secondary small">
                    Domyślne ustawienia dla Growth OS. Przy każdym requestcie możesz lokalnie wybrać inny model lub wysiłek —
                    bez zmiany tych wartości. Klucz API pozostaje w konfiguracji serwera.
                </p>

                <form method="POST" action="{{ route('growth.ai-settings.update') }}">
                    @csrf
                    @method('PUT')

                    <section class="mb-4" aria-labelledby="general-ai-heading">
                        <h2 class="h6" id="general-ai-heading">Domyślne AI</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="general_model">Model</label>
                                <select name="general_model" id="general_model" class="form-select">
                                    @foreach($models as $model)
                                        <option value="{{ $model['id'] }}" @selected(old('general_model', $defaults['general_model']) === $model['id'])>
                                            {{ $model['label'] }} · {{ $model['cost_tier'] }} — {{ $model['description'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="general_reasoning_effort">Wysiłek</label>
                                <select name="general_reasoning_effort" id="general_reasoning_effort" class="form-select">
                                    @foreach($effortLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('general_reasoning_effort', $defaults['general_reasoning_effort']) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="mb-4" aria-labelledby="research-ai-heading">
                        <h2 class="h6" id="research-ai-heading">Research</h2>
                        <p class="small text-secondary">Asystent planowania kierunku (wyszukiwanie w Internecie). Tylko modele z web_search.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="research_model">Model</label>
                                <select name="research_model" id="research_model" class="form-select">
                                    @foreach($models as $model)
                                        @continue(! ($model['capabilities']['web_search'] ?? false))
                                        <option value="{{ $model['id'] }}" @selected(old('research_model', $defaults['research_model']) === $model['id'])>
                                            {{ $model['label'] }} · {{ $model['cost_tier'] }} — {{ $model['description'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="research_reasoning_effort">Wysiłek</label>
                                <select name="research_reasoning_effort" id="research_reasoning_effort" class="form-select">
                                    @foreach($effortLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('research_reasoning_effort', $defaults['research_reasoning_effort']) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Zapisz</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('growth.ai-settings.restore') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Przywróć zalecane</button>
                    <span class="small text-secondary ms-2">
                        {{ $recommended['general_model'] }} / {{ $effortLabels[$recommended['general_reasoning_effort']] ?? $recommended['general_reasoning_effort'] }}
                        · research:
                        {{ $recommended['research_model'] }} / {{ $effortLabels[$recommended['research_reasoning_effort']] ?? $recommended['research_reasoning_effort'] }}
                    </span>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

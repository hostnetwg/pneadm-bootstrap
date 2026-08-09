<x-app-layout>
    <x-slot name="header">
        Szablon: {{ $template->name }}
    </x-slot>

    <div class="py-3">
        <div class="mb-3">
            <a href="{{ route('surveys.templates.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Lista szablonów</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
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

        <form method="POST" action="{{ route('surveys.templates.update', $template) }}" class="card mb-4">
            @csrf
            @method('PUT')
            <div class="card-header">Metadane szablonu</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nazwa</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" value="{{ $template->slug }}" disabled>
                </div>
                <div class="col-12">
                    <label class="form-label">Opis</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $template->description) }}</textarea>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1" @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label" for="is_active">Aktywny</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_default" id="is_default" value="1" @checked(old('is_default', $template->is_default))>
                        <label class="form-check-label" for="is_default">Domyślny</label>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary">Zapisz szablon</button>
            </div>
        </form>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span>Pytania ({{ $template->questions->count() }})</span>
            </div>
            <div class="list-group list-group-flush">
                @foreach($template->questions as $question)
                    <div class="list-group-item">
                        <form method="POST" action="{{ route('surveys.templates.questions.update', [$template, $question]) }}" class="row g-2 align-items-end">
                            @csrf
                            @method('PUT')
                            <div class="col-md-5">
                                <label class="form-label small mb-0">Treść</label>
                                <input type="text" name="question_text" class="form-control form-control-sm" value="{{ $question->question_text }}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Typ</label>
                                <select name="question_type" class="form-select form-select-sm">
                                    @foreach($types as $type)
                                        <option value="{{ $type }}" @selected($question->question_type === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small mb-0">Nr</label>
                                <input type="number" name="question_order" class="form-control form-control-sm" value="{{ $question->question_order }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Opcje (linie)</label>
                                <textarea name="options_text" class="form-control form-control-sm" rows="2">@if(is_array($question->options) && array_is_list($question->options)){{ implode("\n", $question->options) }}@elseif(is_array($question->options)){{ json_encode($question->options, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}@endif</textarea>
                            </div>
                            <div class="col-md-1">
                                <div class="form-check mt-3">
                                    <input type="checkbox" class="form-check-input" name="is_required" value="1" @checked($question->is_required)>
                                    <label class="form-check-label small">Wym.</label>
                                </div>
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary" title="Zapisz"><i class="bi bi-check-lg"></i></button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('surveys.templates.questions.destroy', [$template, $question]) }}" class="mt-1"
                              onsubmit="return confirm('Usunąć to pytanie z szablonu?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Usuń pytanie</button>
                            <span class="text-muted small ms-2">key: {{ $question->question_key }}</span>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('surveys.templates.questions.store', $template) }}" class="card">
            @csrf
            <div class="card-header">Dodaj pytanie</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Treść pytania</label>
                    <input type="text" name="question_text" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Typ</label>
                    <select name="question_type" class="form-select" required>
                        @foreach($types as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kolejność</label>
                    <input type="number" name="question_order" class="form-control" value="{{ ($template->questions->max('question_order') ?? 0) + 10 }}">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_required" value="1" checked>
                        <label class="form-check-label">Wym.</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Opcje (jedna na linię; dla availability — JSON)</label>
                    <textarea name="options_text" class="form-control" rows="3" placeholder="opcja A&#10;opcja B"></textarea>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-success">Dodaj pytanie</button>
            </div>
        </form>
    </div>
</x-app-layout>

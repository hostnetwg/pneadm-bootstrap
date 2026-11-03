<form method="POST" action="{{ route('closed-training-cases.topic.update', $case) }}" class="{{ $formClass ?? '' }}">
    @csrf
    @method('PUT')
    <div class="input-group">
        <span class="input-group-text font-monospace" title="Identyfikator sprawy. Tego kodu nie zmienisz.">[{{ $case->referenceCode() }}]</span>
        <label for="{{ $inputId }}" class="visually-hidden">Tytuł szkolenia</label>
        <input type="text"
               name="topic"
               id="{{ $inputId }}"
               value="{{ old('topic', $case->editableTopic()) }}"
               required
               maxlength="255"
               class="form-control @error('topic') is-invalid @enderror"
               placeholder="Tytuł szkolenia">
        <button type="submit" class="btn btn-primary text-nowrap">Zapisz tytuł</button>
    </div>
    @error('topic')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @unless($compact ?? false)
        <div class="form-text">Kod [{{ $case->referenceCode() }}] zostaje przy sprawie. Zmieniasz tylko tytuł.</div>
    @endunless
</form>

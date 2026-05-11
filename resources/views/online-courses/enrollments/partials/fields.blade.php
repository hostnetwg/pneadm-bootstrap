@php($e = $enrollment ?? null)
<div class="mb-3">
    <label class="form-label" for="email">E-mail</label>
    <input id="email" name="email" type="email" class="form-control" required value="{{ old('email', $e?->email ?? '') }}" @if($e) autocomplete="off" @endif>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="first_name">Imię</label>
        <input id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $e?->first_name ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Nazwisko</label>
        <input id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $e?->last_name ?? '') }}">
    </div>
</div>
<div class="mb-3">
    <label class="form-label" for="access_expires_at">Data wygaśnięcia dostępu (opcjonalnie, UTC — puste = bezterminowo)</label>
    <input id="access_expires_at" name="access_expires_at" type="datetime-local" class="form-control"
           value="{{ old('access_expires_at', $e && $e->access_expires_at ? $e->access_expires_at->timezone('UTC')->format('Y-m-d\TH:i') : '') }}">
</div>
<div class="mb-3">
    <label class="form-label" for="access_source">Źródło</label>
    <input id="access_source" name="access_source" class="form-control" value="{{ old('access_source', $e?->access_source ?? 'manual') }}" placeholder="manual, publigo_migration, …">
</div>
<div class="mb-3">
    <label class="form-label" for="notes">Notatki</label>
    <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $e?->notes ?? '') }}</textarea>
</div>

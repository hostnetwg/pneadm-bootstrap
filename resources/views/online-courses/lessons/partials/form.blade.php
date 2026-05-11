@php
    $lesson = $lesson ?? null;
    $embeds = old('embeds');
    if (! is_array($embeds)) {
        if ($lesson) {
            $embeds = $lesson->embeds->map(fn ($e) => [
                'video_url' => $e->video_url,
                'platform' => $e->platform,
                'title' => $e->title,
            ])->values()->all();
        } else {
            $embeds = [
                ['video_url' => '', 'platform' => 'vimeo', 'title' => ''],
                ['video_url' => '', 'platform' => 'vimeo', 'title' => ''],
            ];
        }
    }
    $resource_links = old('resource_links');
    if (! is_array($resource_links)) {
        if ($lesson) {
            $resource_links = $lesson->resourceLinks->map(fn ($r) => [
                'url' => $r->url,
                'title' => $r->title,
            ])->values()->all();
        } else {
            $resource_links = [['url' => '', 'title' => '']];
        }
    }
@endphp

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ $lesson ? route('online-courses.lessons.update', [$course, $module, $lesson]) : route('online-courses.lessons.store', [$course, $module]) }}">
    @csrf
    @if($lesson)
        @method('PUT')
    @endif

    <div class="mb-3">
        <label class="form-label" for="title">Tytuł lekcji</label>
        <input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title', $lesson?->title) }}">
    </div>

    <div class="form-check mb-3">
        <input type="hidden" name="is_published" value="0">
        <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published" @checked(old('is_published', $lesson?->is_published ?? true))>
        <label class="form-check-label" for="is_published">Opublikowana (widoczna na pnedu dla użytkowników z dostępem)</label>
    </div>

    <div class="mb-3">
        <label class="form-label" for="body_html">Treść (HTML)</label>
        <textarea id="body_html" name="body_html" class="form-control font-monospace" rows="14">{{ old('body_html', $lesson?->body_html) }}</textarea>
    </div>

    <h5>Wideo osadzone (YouTube / Vimeo / „inny” jako link)</h5>
    <p class="small text-muted">Puste wiersze są pomijane.</p>
    <div id="embed-rows">
        @foreach($embeds as $i => $row)
            <div class="row g-2 mb-2 embed-row">
                <div class="col-md-5">
                    <input type="url" name="embeds[{{ $i }}][video_url]" class="form-control" placeholder="URL wideo" value="{{ $row['video_url'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <select name="embeds[{{ $i }}][platform]" class="form-select">
                        @foreach(['vimeo' => 'Vimeo','youtube' => 'YouTube','other' => 'Inny'] as $pv => $pl)
                            <option value="{{ $pv }}" @selected(($row['platform'] ?? '') === $pv || (($row['platform'] ?? '') === '' && $pv === 'vimeo'))>{{ $pl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="embeds[{{ $i }}][title]" class="form-control" placeholder="Opcjonalny tytuł" value="{{ $row['title'] ?? '' }}">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-secondary btn-remove-embed">&times;</button>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="btn-add-embed">+ kolejne wideo</button>

    <h5>Linki do materiałów</h5>
    <div id="link-rows">
        @foreach($resource_links as $i => $row)
            <div class="row g-2 mb-2 link-row">
                <div class="col-md-6">
                    <input type="url" name="resource_links[{{ $i }}][url]" class="form-control" placeholder="https://..." value="{{ $row['url'] ?? '' }}">
                </div>
                <div class="col-md-5">
                    <input type="text" name="resource_links[{{ $i }}][title]" class="form-control" placeholder="Opis linku" value="{{ $row['title'] ?? '' }}">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-secondary btn-remove-link">&times;</button>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="btn-add-link">+ kolejny link</button>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $lesson ? 'Zapisz lekcję' : 'Dodaj lekcję' }}</button>
        <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-outline-secondary">Wróć do struktury</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let embedIdx = {{ count($embeds) }};
    let linkIdx = {{ count($resource_links) }};

    document.getElementById('btn-add-embed')?.addEventListener('click', function () {
        const wrap = document.getElementById('embed-rows');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 embed-row';
        div.innerHTML = `
            <div class="col-md-5"><input type="url" name="embeds[${embedIdx}][video_url]" class="form-control" placeholder="URL wideo"></div>
            <div class="col-md-2"><select name="embeds[${embedIdx}][platform]" class="form-select"><option value="vimeo" selected>Vimeo</option><option value="youtube">YouTube</option><option value="other">Inny</option></select></div>
            <div class="col-md-4"><input type="text" name="embeds[${embedIdx}][title]" class="form-control" placeholder="Opcjonalny tytuł"></div>
            <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-secondary btn-remove-embed">&times;</button></div>`;
        wrap.appendChild(div);
        bindRemove(div.querySelector('.btn-remove-embed'), div);
        embedIdx++;
    });

    document.getElementById('btn-add-link')?.addEventListener('click', function () {
        const wrap = document.getElementById('link-rows');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 link-row';
        div.innerHTML = `
            <div class="col-md-6"><input type="url" name="resource_links[${linkIdx}][url]" class="form-control" placeholder="https://..."></div>
            <div class="col-md-5"><input type="text" name="resource_links[${linkIdx}][title]" class="form-control" placeholder="Opis linku"></div>
            <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-secondary btn-remove-link">&times;</button></div>`;
        wrap.appendChild(div);
        bindRemove(div.querySelector('.btn-remove-link'), div);
        linkIdx++;
    });

    function bindRemove(btn, row) {
        btn?.addEventListener('click', function () { row.remove(); });
    }
    document.querySelectorAll('.btn-remove-embed').forEach(btn => bindRemove(btn, btn.closest('.embed-row')));
    document.querySelectorAll('.btn-remove-link').forEach(btn => bindRemove(btn, btn.closest('.link-row')));
});
</script>

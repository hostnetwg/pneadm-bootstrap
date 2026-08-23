@php
    $isEdit = $article->exists;
@endphp

@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Treść artykułu</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $article->editorText($article->title)) }}" required>
                    <div class="form-text">Encja <code>&amp;nbsp;</code> zapisuje twardą spację (kontrola łamania wiersza). Po zapisie w polu nadal możesz edytować ją jako <code>&amp;nbsp;</code>.</div>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug publiczny</label>
                    <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $article->slug) }}" placeholder="Zostaw puste, aby wygenerować automatycznie">
                    <div class="form-text">Adres publiczny: <code>/blog/slug-artykulu</code>.</div>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="excerpt" class="form-label">Krótki opis</label>
                    <textarea name="excerpt" id="excerpt" class="form-control @error('excerpt') is-invalid @enderror" rows="3" maxlength="1000">{{ old('excerpt', $article->editorText($article->excerpt)) }}</textarea>
                    <div class="form-text">Widoczny na liście bloga i używany jako opis SEO, jeśli nie podasz osobnego meta description. Encja <code>&amp;nbsp;</code> = twarda spacja.</div>
                    @error('excerpt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="content_html" class="form-label">Treść HTML</label>
                    <textarea name="content_html" id="content_html" class="form-control @error('content_html') is-invalid @enderror" rows="16" placeholder="<p>Wpisz treść artykułu...</p>">{{ old('content_html', $article->content_html) }}</textarea>
                    <div class="form-text">Dozwolone są podstawowe znaczniki HTML, np. akapity, nagłówki, listy, linki i tabele. Encja <code>&amp;nbsp;</code> jest traktowana jako twarda spacja.</div>
                    @error('content_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">SEO</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="meta_title" class="form-label">Meta title</label>
                    <input type="text" name="meta_title" id="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $article->editorText($article->meta_title)) }}" maxlength="255">
                    <div class="form-text">Jeśli zostawisz puste, użyty zostanie tytuł artykułu.</div>
                    @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="meta_description" class="form-label">Meta description</label>
                    <textarea name="meta_description" id="meta_description" class="form-control @error('meta_description') is-invalid @enderror" rows="3" maxlength="500">{{ old('meta_description', $article->editorText($article->meta_description)) }}</textarea>
                    @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Notatki administracyjne</h5>
            </div>
            <div class="card-body">
                <textarea name="internal_notes" id="internal_notes" class="form-control @error('internal_notes') is-invalid @enderror" rows="4">{{ old('internal_notes', $article->internal_notes) }}</textarea>
                @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Publikacja</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="draft" {{ old('status', $article->status) === 'draft' ? 'selected' : '' }}>Szkic</option>
                        <option value="published" {{ old('status', $article->status) === 'published' ? 'selected' : '' }}>Opublikowany</option>
                    </select>
                    <div class="form-text">Tylko opublikowane artykuły będą widoczne na <code>/blog</code>.</div>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="published_at" class="form-label">Data publikacji</label>
                    <input type="datetime-local" name="published_at" id="published_at" class="form-control @error('published_at') is-invalid @enderror" value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}">
                    <div class="form-text">Przy publikacji bez daty system ustawi bieżący czas.</div>
                    @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <input type="hidden" name="comments_enabled" value="0">
                <div class="form-check mb-3">
                    <input type="checkbox" name="comments_enabled" value="1" id="comments_enabled" class="form-check-input" {{ old('comments_enabled', $article->comments_enabled) ? 'checked' : '' }}>
                    <label for="comments_enabled" class="form-check-label">Komentarze włączone w przyszłości</label>
                    <div class="form-text">To tylko przygotowanie pola. Publiczny formularz komentarzy nie jest jeszcze wdrożony.</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Grafika</h5>
            </div>
            <div class="card-body">
                @if($isEdit && $article->publicImageUrl())
                    <div class="mb-3">
                        <img src="{{ $article->publicImageUrl() }}" alt="{{ $article->title }}" class="img-fluid rounded border">
                    </div>
                    <input type="hidden" name="remove_cover_image" value="0">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remove_cover_image" value="1" id="remove_cover_image" class="form-check-input">
                        <label for="remove_cover_image" class="form-check-label">Usuń aktualną grafikę</label>
                    </div>
                @endif

                <div class="mb-3">
                    <label for="cover_image" class="form-label">Grafika główna</label>
                    <input type="file" name="cover_image" id="cover_image" class="form-control @error('cover_image') is-invalid @enderror" accept="image/*">
                    <div class="form-text">JPG, PNG, GIF lub WebP, maks. 2 MB.</div>
                    @error('cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-light">
                            <tr>
                                <th>Parametr</th>
                                <th>Wartość</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Proporcje</td>
                                <td><strong>16:9</strong> (jak na zajawce bloga)</td>
                            </tr>
                            <tr>
                                <td>Zalecany rozmiar</td>
                                <td><strong>1600 × 900 px</strong></td>
                            </tr>
                            <tr>
                                <td>Minimum</td>
                                <td>1200 × 675 px</td>
                            </tr>
                            <tr>
                                <td>Opcjonalnie (retina / OG)</td>
                                <td>1920 × 1080 px</td>
                            </tr>
                            <tr>
                                <td>Kadrowanie</td>
                                <td>Ważna treść na środku — obraz jest przycinany do 16:9</td>
                            </tr>
                            <tr>
                                <td>Nazwa pliku na serwerze</td>
                                <td><code>slug-artykulu-xxxxxx.png</code> — np. <code>nowa-podstawa-programowa-2026-a3f9c2.png</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">
                    Ta sama grafika jest używana na liście <code>/blog</code> i na stronie artykułu.
                    Przy każdej wymianie pliku zapisywany jest nowy adres URL (unikalny sufiks), więc przeglądarka nie pokaże starego obrazu z cache.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2">
    <button type="submit" name="save_action" value="stay_editing" class="btn btn-primary">
        {{ $isEdit ? 'Zapisz i edytuj dalej' : 'Dodaj i edytuj dalej' }}
    </button>
    <button type="submit" name="save_action" value="close" class="btn btn-outline-secondary">
        {{ $isEdit ? 'Zapisz i wróć do listy' : 'Dodaj i wróć do listy' }}
    </button>
    <a href="{{ route('articles.index') }}" class="btn btn-secondary">Anuluj</a>
</div>

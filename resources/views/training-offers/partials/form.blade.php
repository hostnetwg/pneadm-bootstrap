@php
    $isEdit = $offer->exists;
@endphp

@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Treść oferty</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label">Tytuł oferty <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $offer->title) }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug publiczny</label>
                    <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $offer->slug) }}" placeholder="Zostaw puste, aby wygenerować automatycznie">
                    <div class="form-text">Używany w adresie strony szczegółowej na pnedu.pl.</div>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="summary" class="form-label">Krótkie podsumowanie</label>
                    <textarea name="summary" id="summary" class="form-control @error('summary') is-invalid @enderror" rows="3" maxlength="500">{{ old('summary', $offer->summary) }}</textarea>
                    <div class="form-text">Widoczne na liście ofert.</div>
                    @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="description_html" class="form-label">Pełny opis oferty HTML</label>
                    <textarea name="description_html" id="description_html" class="form-control @error('description_html') is-invalid @enderror" rows="10">{{ old('description_html', $offer->description_html) }}</textarea>
                    <div class="form-text">Opis publiczny oferty. Dozwolone są podstawowe znaczniki HTML.</div>
                    @error('description_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="scope" class="form-label">Zakres szkolenia / zagadnienia</label>
                    <textarea name="scope" id="scope" class="form-control @error('scope') is-invalid @enderror" rows="6">{{ old('scope', $offer->scope) }}</textarea>
                    <div class="form-text">W przyszłości pole będzie kopiowane do zakresu szkolenia w `courses.description`.</div>
                    @error('scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="audience" class="form-label">Odbiorcy</label>
                    <input type="text" name="audience" id="audience" class="form-control @error('audience') is-invalid @enderror" value="{{ old('audience', $offer->audience) }}" placeholder="np. rady pedagogiczne, dyrektorzy szkół">
                    @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <input type="text" name="meta_title" id="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $offer->meta_title) }}" maxlength="255">
                    @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="meta_description" class="form-label">Meta description</label>
                    <textarea name="meta_description" id="meta_description" class="form-control @error('meta_description') is-invalid @enderror" rows="3" maxlength="500">{{ old('meta_description', $offer->meta_description) }}</textarea>
                    @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Notatki administracyjne</h5>
            </div>
            <div class="card-body">
                <textarea name="internal_notes" id="internal_notes" class="form-control @error('internal_notes') is-invalid @enderror" rows="4">{{ old('internal_notes', $offer->internal_notes) }}</textarea>
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
                <input type="hidden" name="is_active" value="0">
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" {{ old('is_active', $offer->is_active) ? 'checked' : '' }}>
                    <label for="is_active" class="form-check-label">Aktywna w panelu</label>
                </div>

                <input type="hidden" name="show_on_pnedu" value="0">
                <div class="form-check mb-3">
                    <input type="checkbox" name="show_on_pnedu" value="1" id="show_on_pnedu" class="form-check-input" {{ old('show_on_pnedu', $offer->show_on_pnedu) ? 'checked' : '' }}>
                    <label for="show_on_pnedu" class="form-check-label">Pokaż na pnedu.pl</label>
                    <div class="form-text">Oferta pojawi się w katalogu „Szkolenia rad pedagogicznych”.</div>
                </div>

                <input type="hidden" name="featured_on_homepage" value="0">
                <div class="form-check mb-3">
                    <input type="checkbox" name="featured_on_homepage" value="1" id="featured_on_homepage" class="form-check-input" {{ old('featured_on_homepage', $offer->featured_on_homepage) ? 'checked' : '' }}>
                    <label for="featured_on_homepage" class="form-check-label">Wyróżnij na stronie głównej</label>
                    <div class="form-text">Oferta pojawi się w sekcji szkoleń rad pedagogicznych na stronie głównej pnedu.pl.</div>
                </div>

                <div class="mb-3">
                    <label for="sort_order" class="form-label">Kolejność</label>
                    <input type="number" min="0" name="sort_order" id="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $offer->sort_order ?? 0) }}">
                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Cena i przyszłe kopiowanie</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="price_mode" class="form-label">Tryb ceny <span class="text-danger">*</span></label>
                    <select name="price_mode" id="price_mode" class="form-select @error('price_mode') is-invalid @enderror" required>
                        <option value="individual" {{ old('price_mode', $offer->price_mode) === 'individual' ? 'selected' : '' }}>Cena ustalana indywidualnie</option>
                        <option value="fixed" {{ old('price_mode', $offer->price_mode) === 'fixed' ? 'selected' : '' }}>Konkretna cena</option>
                    </select>
                    @error('price_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="price_amount" class="form-label">Cena brutto</label>
                    <input type="number" step="0.01" min="0" name="price_amount" id="price_amount" class="form-control @error('price_amount') is-invalid @enderror" value="{{ old('price_amount', $offer->price_amount) }}">
                    <div class="form-text">Wymagana tylko dla konkretnej ceny.</div>
                    @error('price_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="default_course_category" class="form-label">Domyślna kategoria kursu</label>
                    <select name="default_course_category" id="default_course_category" class="form-select @error('default_course_category') is-invalid @enderror">
                        <option value="closed" {{ old('default_course_category', $offer->default_course_category) === 'closed' ? 'selected' : '' }}>Zamknięte</option>
                        <option value="open" {{ old('default_course_category', $offer->default_course_category) === 'open' ? 'selected' : '' }}>Otwarte</option>
                    </select>
                    @error('default_course_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Trener i grafika</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="instructor_id" class="form-label">Trener</label>
                    <select name="instructor_id" id="instructor_id" class="form-select @error('instructor_id') is-invalid @enderror">
                        <option value="">Brak</option>
                        @foreach($instructors as $instructor)
                            <option value="{{ $instructor->id }}" {{ (string) old('instructor_id', $offer->instructor_id) === (string) $instructor->id ? 'selected' : '' }}>
                                {{ $instructor->full_title_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('instructor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @if($isEdit && $offer->publicImageUrl())
                    <div class="mb-3">
                        <img src="{{ $offer->publicImageUrl() }}" alt="{{ $offer->title }}" class="img-fluid rounded border">
                    </div>
                    <input type="hidden" name="remove_image" value="0">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remove_image" value="1" id="remove_image" class="form-check-input">
                        <label for="remove_image" class="form-check-label">Usuń aktualną grafikę</label>
                    </div>
                @endif

                <div class="mb-3">
                    <label for="image" class="form-label">Grafika oferty</label>
                    <input type="file" name="image" id="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
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
    <a href="{{ route('training-offers.index') }}" class="btn btn-secondary">Anuluj</a>
</div>

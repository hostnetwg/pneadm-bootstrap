@php
    $c = $course;
@endphp

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ $c ? route('online-courses.update', $c) : route('online-courses.store') }}" enctype="multipart/form-data">
    @csrf
    @if($c)
        @method('PUT')
    @endif

    <div class="mb-3">
        <label class="form-label" for="title">Tytuł</label>
        <input id="title" name="title" class="form-control" required value="{{ old('title', $c?->title) }}">
    </div>

    <div class="mb-3">
        <label class="form-label" for="slug">Slug (URL, opcjonalny — wygenerujemy z tytułu)</label>
        <input id="slug" name="slug" class="form-control" value="{{ old('slug', $c?->slug) }}" placeholder="np. kurs-excel-dla-nauczycieli">
    </div>

    <div class="mb-3">
        <label class="form-label" for="online_course_image">Grafika kursu (okładka)</label>
        <input id="online_course_image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
        <div class="form-text">Opcjonalnie. JPG, PNG, GIF lub WebP, maks. 2&nbsp;MB. Pliki w <code>storage/app/public/online-courses/images</code> (link <code>public/storage</code>).</div>
        @if($c && !empty($c->image))
            <div class="mt-2">
                <img src="{{ asset('storage/'.$c->image) }}" alt="Obrazek kursu" class="img-thumbnail" style="max-width: 200px;">

                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image">
                    <label class="form-check-label" for="remove_image">Usuń grafikę z kursu</label>
                </div>
            </div>
        @endif
    </div>

    <div class="mb-3">
        <label class="form-label" for="description">Opis</label>
        <textarea id="description" name="description" class="form-control" rows="4">{{ old('description', $c?->description) }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label" for="offer_description_html">Opis oferty (HTML, opcjonalnie — na później na froncie)</label>
        <textarea id="offer_description_html" name="offer_description_html" class="form-control" rows="4">{{ old('offer_description_html', $c?->offer_description_html) }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label" for="instructor_id">Instruktor</label>
        <select id="instructor_id" name="instructor_id" class="form-select">
            <option value="">—</option>
            @foreach($instructors as $i)
                <option value="{{ $i->id }}" @selected(old('instructor_id', $c?->instructor_id) == $i->id)>{{ $i->last_name }} {{ $i->first_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label" for="legacy_publigo_product_id">ID produktu Publigo / mapowanie migracji</label>
        <input id="legacy_publigo_product_id" name="legacy_publigo_product_id" class="form-control" value="{{ old('legacy_publigo_product_id', $c?->legacy_publigo_product_id) }}" placeholder="np. stary SKU lub ID">
    </div>

    <div class="form-check mb-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $c?->is_active ?? true))>
        <label class="form-check-label" for="is_active">Kurs aktywny</label>
    </div>

    <div class="form-check mb-3">
        <input type="hidden" name="visible_in_dashboard" value="0">
        <input type="checkbox" name="visible_in_dashboard" value="1" class="form-check-input" id="visible_in_dashboard" @checked(old('visible_in_dashboard', $c?->visible_in_dashboard ?? true))>
        <label class="form-check-label" for="visible_in_dashboard">Widoczny w panelu pnedu.pl dla osób z dostępem</label>
    </div>

    <div class="mb-3">
        <label class="form-label" for="internal_notes">Notatki wewnętrzne</label>
        <textarea id="internal_notes" name="internal_notes" class="form-control" rows="2">{{ old('internal_notes', $c?->internal_notes) }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary">{{ $c ? 'Zapisz zmiany' : 'Utwórz kurs' }}</button>
    @if($c)
        <a href="{{ route('online-courses.index') }}" class="btn btn-outline-secondary ms-2">Lista</a>
    @endif
</form>

<div class="card border-secondary mb-4 mt-1 shadow-sm">
    <div class="card-header bg-light py-2 border-secondary-subtle">
        <h6 class="mb-0 fw-semibold">Powiązane szkolenie / webinar (zaświadczenie dla abonentów)</h6>
    </div>
    <div class="card-body">
        <label class="form-label visually-hidden" for="linked_course_id">Powiązane szkolenie / webinar</label>
        <select id="linked_course_id" name="linked_course_id" class="form-control @error('linked_course_id') is-invalid @enderror">
            @if(!empty($linkedCourse))
                <option value="{{ $linkedCourse->id }}" selected>
                    #{{ $linkedCourse->id }} · {{ strip_tags($linkedCourse->title) }}
                    @if($linkedCourse->start_date) [{{ $linkedCourse->start_date->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}] @endif
                </option>
            @endif
        </select>
        @error('linked_course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="form-text text-muted d-block mt-1">
            Wpisz tytuł, ID lub Publigo ID. Lista obejmuje także szkolenia archiwalne. Pole opcjonalne — wyczyść wybór, jeśli lekcja nie ma powiązanego zaświadczenia.
        </small>
        <div id="linked-course-info" class="alert alert-light mt-2 mb-0 py-2 small border" style="display: none;">
            <div id="linked-course-details"></div>
        </div>
        <p class="small text-muted mb-0 mt-3">
            Abonenci kursu online z aktywnym dostępem zobaczą na pnedu.pl formularz rejestracji uczestnika tego szkolenia
            (dane imię/nazwisko/e-mail z zapisu na kurs online). Termin publicznej rejestracji nie obowiązuje — wymagane jest tylko
            włączenie „Rejestracji zaświadczenia” w edycji szkolenia.
        </p>
        <p class="small text-muted mb-0 mt-2">
            <strong>Uwaga:</strong> powiązanie dotyczy całej lekcji (nie pojedynczego wideo). Przy kilku nagraniach w jednej lekcji
            abonent rejestruje się raz na wybrane szkolenie — typowy przypadek to jedno wideo = jeden webinar.
        </p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Domyślne zaświadczenia dla nowych szkoleń</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Te ustawienia kopiują się na szkolenie tylko w momencie dodania go do serii,
            i tylko gdy szkolenie ma jeszcze domyślny format <code>{nr}/{course_id}/{year}/PNE</code>.
            Szkolenia już w serii nie są zmieniane. Po dodaniu pola na szkoleniu pozostają edytowalne.
        </p>

        <div class="mb-3">
            <label for="certificate_format" class="form-label">Format numeracji zaświadczeń</label>
            <input type="text"
                   name="certificate_format"
                   id="certificate_format"
                   class="form-control"
                   value="{{ old('certificate_format', $series?->certificate_format ?? '') }}"
                   placeholder="{nr}/{course_id}/{year}/TIK">
            <small class="form-text text-muted">
                Zmienne: <code>{nr}</code>, <code>{course_id}</code>, <code>{year}</code>.
                Puste pole = nie ustawiaj formatu automatycznie.
            </small>
        </div>

        <div class="mb-0">
            <label for="certificate_template_id" class="form-label">Szablon zaświadczenia</label>
            <select name="certificate_template_id" id="certificate_template_id" class="form-select">
                <option value="">Bez automatycznego szablonu</option>
                @foreach ($certificateTemplates as $template)
                    <option value="{{ $template->id }}"
                        @selected((string) old('certificate_template_id', $series?->certificate_template_id ?? '') === (string) $template->id)>
                        {{ $template->name }}
                    </option>
                @endforeach
            </select>
            <small class="form-text text-muted">
                <a href="{{ route('admin.certificate-templates.index') }}" target="_blank" rel="noopener">Zarządzaj szablonami</a>
            </small>
        </div>
    </div>
</div>

@php
    $certificateTemplates = $certificateTemplates ?? collect();
    $status = old('certificate_download_status', $c?->certificate_download_status ?? 'no_certificate');
@endphp

<div class="card border-secondary mb-4">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 fw-semibold">Zaświadczenia (kurs online)</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label for="certificate_download_status" class="form-label">Status zaświadczeń</label>
            <select name="certificate_download_status" id="certificate_download_status" class="form-select">
                <option value="no_certificate" @selected($status === 'no_certificate')>Brak zaświadczenia</option>
                <option value="in_preparation" @selected($status === 'in_preparation')>Zaświadczenie w przygotowaniu</option>
                <option value="download_enabled" @selected($status === 'download_enabled')>Udostępnij pobieranie zaświadczeń</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="certificate_format" class="form-label">Format numeracji zaświadczeń</label>
            <input type="text" name="certificate_format" id="certificate_format" class="form-control"
                   value="{{ old('certificate_format', $c?->certificate_format ?? '{nr}/{online_course_id}/{year}/PNE-KO') }}"
                   placeholder="{nr}/{online_course_id}/{year}/PNE-KO">
            <small class="form-text text-muted">
                Zmienne: <code>{nr}</code>, <code>{online_course_id}</code>, <code>{year}</code>.
            </small>
        </div>

        <div class="mb-3">
            <label for="certificate_issue_date" class="form-label">Data wydania zaświadczeń (opcjonalnie)</label>
            <input type="date" name="certificate_issue_date" id="certificate_issue_date" class="form-control"
                   value="{{ old('certificate_issue_date', $c?->certificate_issue_date?->format('Y-m-d') ?? '') }}">
            <small class="form-text text-muted">Jeśli puste — data pierwszego pobrania przez uczestnika.</small>
        </div>

        <div class="mb-3">
            <label for="certificate_duration_minutes" class="form-label">Czas trwania szkolenia (minuty)</label>
            <input type="number" name="certificate_duration_minutes" id="certificate_duration_minutes" class="form-control"
                   min="0" max="9999" step="1"
                   value="{{ old('certificate_duration_minutes', $c?->certificate_duration_minutes) }}"
                   placeholder="np. 90">
            <small class="form-text text-muted">
                Wymiar na zaświadczeniu PDF — gdy w szablonie włączone „Pokaż czas trwania” lub zmienna <code>{czas_trwania}</code> / <code>{wymiar_minut}</code>.
            </small>
        </div>

        <div class="mb-3">
            <label for="certificate_template_id" class="form-label">Szablon certyfikatu</label>
            <select name="certificate_template_id" id="certificate_template_id" class="form-select">
                <option value="">Domyślny szablon</option>
                @foreach($certificateTemplates as $template)
                    <option value="{{ $template->id }}" @selected((string) old('certificate_template_id', $c?->certificate_template_id) === (string) $template->id)>
                        {{ $template->name }}
                    </option>
                @endforeach
            </select>
            <small class="form-text text-muted">
                <a href="{{ route('admin.certificate-templates.index') }}" target="_blank" rel="noopener">Zarządzaj szablonami</a>
            </small>
        </div>

        <div class="form-check mb-2">
            <input type="hidden" name="certificate_collect_birth_data" value="0">
            <input type="checkbox" name="certificate_collect_birth_data" value="1" class="form-check-input" id="certificate_collect_birth_data"
                @checked(old('certificate_collect_birth_data', $c?->certificate_collect_birth_data ?? false))>
            <label class="form-check-label" for="certificate_collect_birth_data">Zbieraj datę i miejsce urodzenia na zaświadczeniu</label>
        </div>
        <div class="form-check mb-0">
            <input type="hidden" name="certificate_birth_data_required" value="0">
            <input type="checkbox" name="certificate_birth_data_required" value="1" class="form-check-input" id="certificate_birth_data_required"
                @checked(old('certificate_birth_data_required', $c?->certificate_birth_data_required ?? false))>
            <label class="form-check-label" for="certificate_birth_data_required">Dane urodzenia wymagane przed pobraniem</label>
        </div>
        <p class="small text-muted mt-3 mb-0">
            Imię i nazwisko na zaświadczeniu pochodzą z profilu użytkownika na pnedu.pl (to samo konto co e-mail zapisu na kurs).
        </p>
    </div>
</div>

@php
    $mailTemplateKey = \App\Support\GrowthOS\MailTemplates::key(old('template_key', $material['template_key'] ?? null));
    $mailEditorContent = \App\Support\GrowthOS\MailHtmlFormatter::editorContent((string) old('mail_body', $mailFields['body'] ?? ''));
    $mailFinalHtml = \App\Support\GrowthOS\MailHtmlFormatter::copyHtml((string) old('mail_preheader', $mailFields['preheader'] ?? ''), $mailEditorContent, $mailTemplateKey);
@endphp
<fieldset class="mb-3">
    <legend class="form-label fs-6">Szablon</legend>
    <div class="d-flex flex-wrap gap-3">
        @foreach(\App\Support\GrowthOS\MailTemplates::labels() as $templateKey => $templateLabel)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="template_key" value="{{ $templateKey }}" id="mail_template_{{ $templateKey }}" @checked($mailTemplateKey === $templateKey) @disabled($materialSkipped) data-mail-template-choice>
                <label class="form-check-label" for="mail_template_{{ $templateKey }}">{{ $templateLabel }}</label>
            </div>
        @endforeach
    </div>
    <div class="form-text">Szablon ustala układ i kolory. Treść, temat i preheader zostają. AI go nie wybiera.</div>
</fieldset>
<label id="mail_body_label" class="form-label">Treść</label>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2" data-mail-editor-toolbar>
    <div class="btn-group btn-group-sm" role="group" aria-label="Tryb treści">
        <button type="button" class="btn btn-primary" data-mail-mode="visual" aria-pressed="true">Edycja</button>
        <button type="button" class="btn btn-outline-primary" data-mail-mode="html" aria-pressed="false">Kod HTML</button>
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Formatowanie" data-mail-tools>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="toggleBold" title="Pogrubienie" aria-label="Pogrubienie"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="toggleItalic" title="Kursywa" aria-label="Kursywa"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="toggleUnderline" title="Podkreślenie" aria-label="Podkreślenie"><i class="bi bi-type-underline" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="toggleBulletList" title="Lista" aria-label="Lista"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="toggleOrderedList" title="Lista numerowana" aria-label="Lista numerowana"><i class="bi bi-list-ol" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-link data-bs-toggle="modal" data-bs-target="#mail-editor-link" title="Link" aria-label="Link"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="clear" title="Wyczyść formatowanie" aria-label="Wyczyść formatowanie"><i class="bi bi-eraser" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="undo" title="Cofnij" aria-label="Cofnij"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-mail-command="redo" title="Ponów" aria-label="Ponów"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" data-mail-copy-html>Kopiuj HTML maila</button>
    <span class="small text-success d-none" role="status" data-mail-copy-html-status>Skopiowano. Wklej w Sendy w trybie HTML.</span>
</div>
<div class="mb-2" data-mail-frame data-mail-template="{{ $mailTemplateKey }}">
    {!! \App\Support\GrowthOS\MailHtmlFormatter::editorFrame($mailTemplateKey, (bool) $materialSkipped) !!}
</div>
<textarea id="mail_body" name="mail_body" class="d-none" aria-labelledby="mail_body_label" @readonly($materialSkipped)>{{ $mailEditorContent }}</textarea>
<label for="mail_body_html" class="visually-hidden">Kod HTML maila</label>
<textarea id="mail_body_html" class="form-control font-monospace d-none mb-2" rows="16" readonly data-mail-final-html>{{ $mailFinalHtml }}</textarea>
<p class="form-text">Edycja formatuje samą treść. Kod HTML to gotowy mail do skopiowania: układ bierze się z wybranego szablonu i nie edytuje się go w tym polu.</p>
@foreach(\App\Support\GrowthOS\MailTemplates::keys() as $templateKey)
    <template id="mail-shell-{{ $templateKey }}">{!! \App\Support\GrowthOS\MailHtmlFormatter::render('', $templateKey) !!}</template>
@endforeach

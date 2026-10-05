@php
    $mailContext = \App\Support\GrowthOS\MailRenderContext::fromProject($project, $material, false);
    $mailEditorContent = \App\Support\GrowthOS\MailHtmlFormatter::editorContent((string) old('mail_body', $mailFields['body'] ?? ''));
    $mailFinalHtml = \App\Support\GrowthOS\MailHtmlFormatter::copyHtml(
        (string) old('mail_preheader', $mailFields['preheader'] ?? ''),
        $mailEditorContent,
        \App\Support\GrowthOS\MailTemplates::CANONICAL,
        $mailContext,
    );
    $canCopyMailHtml = \App\Support\GrowthOS\MailHtmlFormatter::canCopyHtml($mailContext);
    $includePaidOffer = (bool) old('include_paid_offer', $material['include_paid_offer'] ?? false);
    $showCertificate = (bool) old('show_certificate', $material['show_certificate'] ?? false);
    $paidSnapshot = is_array($material['paid_offer_snapshot'] ?? null) ? $material['paid_offer_snapshot'] : null;
    $paidCoursesCount = is_array($paidSnapshot['courses'] ?? null) ? count($paidSnapshot['courses']) : 0;
@endphp
<div class="alert alert-light border small mb-3" role="status">
    Układ: <strong>Sendy PNE</strong> (kanoniczny). Greeting, CTA, karta webinaru, oferta i stopka składa aplikacja.
    Edytujesz tylko treść redakcyjną.
</div>
@if(! $canCopyMailHtml)
    <div class="alert alert-warning small" role="status">
        Brakuje linku do zapisów. Uzupełnij go na <a href="{{ route('growth.projects.show', $project['id']) }}#project-links">karcie projektu</a> przed skopiowaniem finalnego HTML.
    </div>
@endif
@if(blank($project['youtube_live_url'] ?? null))
    <div class="alert alert-light border small" role="status">
        Link do transmisji YouTube nie został jeszcze podany. Przycisk YouTube nie pojawi się w finalnym HTML.
    </div>
@endif

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="show_certificate" value="1" id="mail_show_certificate" @checked($showCertificate) @disabled($materialSkipped)>
        <label class="form-check-label" for="mail_show_certificate">Pokaż informację o bezpłatnym zaświadczeniu</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="include_paid_offer" value="1" id="mail_include_paid_offer" @checked($includePaidOffer) @disabled($materialSkipped)>
        <label class="form-check-label" for="mail_include_paid_offer">Dołącz ofertę płatnych szkoleń pnedu.pl</label>
    </div>
    @if($includePaidOffer)
        <div class="border rounded p-2 mt-2 small bg-light">
            @if($paidCoursesCount > 0)
                <p class="mb-1">
                    Oferta: <strong>{{ $paidCoursesCount }}</strong>
                    {{ $paidCoursesCount === 1 ? 'szkolenie' : 'szkoleń' }}
                    @if(! empty($paidSnapshot['captured_at']))
                        · pobrano {{ \Illuminate\Support\Carbon::parse($paidSnapshot['captured_at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                    @endif
                </p>
                <button type="submit" class="btn btn-outline-secondary btn-sm" name="refresh_paid_offer" value="1" @disabled($materialSkipped)>Odśwież ofertę</button>
            @else
                <p class="mb-0 text-warning-emphasis">
                    Nie znaleziono obecnie nadchodzących płatnych szkoleń spełniających kryteria.
                    Blok oferty nie zostanie dodany do maila.
                </p>
                <button type="submit" class="btn btn-outline-secondary btn-sm mt-2" name="refresh_paid_offer" value="1" @disabled($materialSkipped)>Odśwież ofertę</button>
            @endif
        </div>
    @endif
</div>

<label id="mail_body_label" class="form-label">Treść redakcyjna</label>
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
    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" data-mail-copy-html @disabled(! $canCopyMailHtml) title="{{ $canCopyMailHtml ? 'Kopiuj finalny HTML' : 'Uzupełnij link do zapisów na karcie projektu' }}">Kopiuj HTML maila</button>
    <span class="small text-success d-none" role="status" data-mail-copy-html-status>Skopiowano. Wklej w Sendy w trybie HTML.</span>
</div>
<div class="mb-2" data-mail-frame data-mail-template="sendy-pne" data-mail-can-copy="{{ $canCopyMailHtml ? '1' : '0' }}">
    {!! \App\Support\GrowthOS\MailHtmlFormatter::editorFrame(\App\Support\GrowthOS\MailTemplates::CANONICAL, (bool) $materialSkipped, $mailContext) !!}
</div>
<textarea id="mail_body" name="mail_body" class="d-none" aria-labelledby="mail_body_label" @readonly($materialSkipped)>{{ $mailEditorContent }}</textarea>
<label for="mail_body_html" class="visually-hidden">Kod HTML maila</label>
<textarea id="mail_body_html" class="form-control font-monospace d-none mb-2" rows="16" readonly data-mail-final-html>{{ $mailFinalHtml }}</textarea>
<p class="form-text">Edycja formatuje treść redakcyjną. Kod HTML to gotowy mail Sendy PNE do skopiowania — layoutu nie edytujesz w tym polu.</p>
<template id="mail-shell-sendy-pne">{!! \App\Support\GrowthOS\MailHtmlFormatter::render('', \App\Support\GrowthOS\MailTemplates::CANONICAL, $mailContext) !!}</template>

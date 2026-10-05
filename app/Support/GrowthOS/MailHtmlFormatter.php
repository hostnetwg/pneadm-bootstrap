<?php

namespace App\Support\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\PaidCourseOfferBuilder;
use DOMDocument;
use DOMElement;
use DOMNode;

// Deploy safety: load companion classes even when production classmap was not
// regenerated after git pull (optimize-autoloader / stale OPcache edge cases).
require_once __DIR__.'/MailRenderContext.php';
require_once __DIR__.'/MailTemplates.php';
require_once dirname(__DIR__, 2).'/Services/GrowthOS/PaidCourseOfferBuilder.php';

/**
 * Composes the Sendy PNE email: greeting, editorial body, webinar card, CTAs, offer, footer (DEC-050).
 * The editor never receives the full document — only the editorial region.
 */
final class MailHtmlFormatter
{
    public const MARKER = 'data-pne-mail="1"';

    public const CTA_LABEL = 'Zapisz się na webinar';

    public const YOUTUBE_CTA_LABEL = 'Dołącz na YouTube';

    public const ROOM_CTA_LABEL = 'Dołącz do pokoju';

    public const GREETING = 'Dzień dobry [Name,fallback=]!';

    public const CERTIFICATE_NOTE = 'Tuż po zakończeniu webinaru udostępnimy formularz rejestracji <strong>bezpłatnego zaświadczenia</strong>. '
        .'Zaświadczenie jest dla osób obecnych na żywo — <strong>bądź z nami</strong>.';

    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a'];

    private const SAFE_HREF = '/^(https?:\/\/|mailto:|\[LINK DO )/i';

    public static function format(string $plain, ?string $templateKey = null, ?MailRenderContext $context = null): string
    {
        $context = self::withRoomCtaFromContent($plain, $context);

        return self::render(self::plainToContent($plain), $templateKey, $context);
    }

    public static function formatComposedDraft(string $draft, ?string $templateKey = null, ?MailRenderContext $context = null): string
    {
        $fields = MaterialDraftTask::parseMainMail($draft);
        if (str_contains($fields['body'], self::MARKER)) {
            return $draft;
        }

        $subjects = array_values(array_filter(
            [$fields['subject'], ...$fields['alternatives']],
            static fn (string $subject): bool => $subject !== '',
        ));

        return MaterialDraftTask::composeMainMail(
            $subjects,
            $fields['preheader'],
            self::format($fields['body'], $templateKey, $context),
        );
    }

    /**
     * Body region only. A stored plain draft stays plain. A full mail or editor HTML loses the wrapper, the button and unsafe tags.
     */
    public static function editorContent(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        if (str_contains($source, 'data-pne-mail-body')) {
            return self::sanitizeContent(self::extractBody($source));
        }

        if (! self::isHtml($source)) {
            return $source;
        }

        return self::sanitizeContent($source);
    }

    public static function render(string $content, ?string $templateKey = null, ?MailRenderContext $context = null): string
    {
        $context ??= new MailRenderContext;
        if ($context->isReminder && ! $context->includeRoomCta && (
            str_contains($content, MaterialDraftTask::ROOM_LINK_PLACEHOLDER)
        )) {
            $context = new MailRenderContext(
                registrationUrl: $context->registrationUrl,
                youtubeLiveUrl: $context->youtubeLiveUrl,
                hostName: $context->hostName,
                liveLabel: $context->liveLabel,
                webinarTitle: $context->webinarTitle,
                webinarSubtitle: $context->webinarSubtitle,
                showCertificate: $context->showCertificate,
                includeRoomCta: true,
                paidCourses: $context->paidCourses,
                growthCampaignId: $context->growthCampaignId,
                isReminder: true,
                reminderTiming: $context->reminderTiming,
            );
        }
        $editorial = self::prepareEditorial($content, $context);

        return self::document($editorial, $context, MailTemplates::key($templateKey));
    }

    /**
     * Same shell as the copied mail, with the editor mounted in the content cell.
     */
    public static function editorFrame(?string $templateKey = null, bool $locked = false, ?MailRenderContext $context = null): string
    {
        $lockedAttr = $locked ? ' data-mail-locked="1"' : '';
        $context ??= new MailRenderContext;

        return self::document(
            '<div id="mail_body_editor" data-mail-editor'.$lockedAttr.'></div>',
            $context,
            MailTemplates::key($templateKey),
            emailizeEditorial: false,
        );
    }

    public static function finalHtml(string $content, ?string $templateKey = null, ?MailRenderContext $context = null): string
    {
        $context = self::withRoomCtaFromContent($content, $context);
        $content = self::editorContent($content);

        return self::isHtml($content)
            ? self::render($content, $templateKey, $context)
            : self::format($content, $templateKey, $context);
    }

    private static function withRoomCtaFromContent(string $content, ?MailRenderContext $context): MailRenderContext
    {
        $context ??= new MailRenderContext;
        if (! $context->isReminder || $context->includeRoomCta || ! str_contains($content, MaterialDraftTask::ROOM_LINK_PLACEHOLDER)) {
            return $context;
        }

        return new MailRenderContext(
            registrationUrl: $context->registrationUrl,
            youtubeLiveUrl: $context->youtubeLiveUrl,
            hostName: $context->hostName,
            liveLabel: $context->liveLabel,
            webinarTitle: $context->webinarTitle,
            webinarSubtitle: $context->webinarSubtitle,
            showCertificate: $context->showCertificate,
            includeRoomCta: true,
            paidCourses: $context->paidCourses,
            growthCampaignId: $context->growthCampaignId,
            isReminder: true,
            reminderTiming: $context->reminderTiming,
        );
    }

    public static function copyHtml(string $preheader, string $content, ?string $templateKey = null, ?MailRenderContext $context = null): string
    {
        return self::preheaderHtml($preheader).self::finalHtml($content, $templateKey, $context);
    }

    public static function canCopyHtml(?MailRenderContext $context): bool
    {
        $url = trim((string) ($context?->registrationUrl ?? ''));

        return $url !== '' && preg_match('/^https:\/\//i', $url) === 1;
    }

    public static function preheaderHtml(string $preheader): string
    {
        return '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">'
            .self::escape(trim($preheader))
            .str_repeat('&nbsp;&zwnj;', 40)
            .'</div>';
    }

    /**
     * Subject, preheader and visible body text. No template table, styles or button.
     */
    public static function plainForAi(string $draft): string
    {
        $fields = MaterialDraftTask::parseMainMail($draft);
        $subjects = array_values(array_filter(
            [$fields['subject'], ...$fields['alternatives']],
            static fn (string $subject): bool => $subject !== '',
        ));

        return MaterialDraftTask::composeMainMail($subjects, $fields['preheader'], self::toPlain($fields['body']));
    }

    public static function sanitizeContent(string $html): string
    {
        $html = trim($html);
        if ($html === '' || ! self::isHtml($html)) {
            return $html;
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="pne-mail-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $root = $document->getElementById('pne-mail-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private static function prepareEditorial(string $content, MailRenderContext $context): string
    {
        $html = self::isHtml($content) ? self::sanitizeContent($content) : self::plainToContent($content);
        $html = self::stripTechnicalMarkers($html);
        $html = self::stripLeadingGreeting($html);

        return self::emailize($html);
    }

    private static function document(
        string $editorialHtml,
        MailRenderContext $context,
        string $templateKey,
        bool $emailizeEditorial = true,
    ): string {
        $inner = $emailizeEditorial ? $editorialHtml : $editorialHtml;
        $paidBlock = self::paidCoursesHtml($context);
        $youtube = trim((string) ($context->youtubeLiveUrl ?? ''));
        $registration = trim((string) ($context->registrationUrl ?? ''));
        $registrationHref = $registration !== '' ? self::escapeAttr($registration) : self::escapeAttr(MaterialDraftTask::LINK_PLACEHOLDER);

        $parts = [];
        $parts[] = self::outerOpen($templateKey);
        $parts[] = self::headerHtml();
        if ($context->isReminder) {
            $parts[] = self::reminderBannerHtml($context);
        }
        $parts[] = self::greetingHtml();
        $parts[] = '<tr><td data-pne-mail-body="1" style="padding:8px 30px 12px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;color:#243040;">'
            .$inner
            .'</td></tr>';

        if ($context->liveLabel !== '' || $context->webinarTitle !== '') {
            $parts[] = self::webinarLabelHtml($context);
            $parts[] = self::webinarCardHtml($context, $registrationHref, $youtube);
        } else {
            $parts[] = self::ctaOnlyHtml($registrationHref, $youtube, $context);
        }

        if ($paidBlock !== '') {
            $parts[] = $paidBlock;
        }

        $parts[] = self::footerHtml();
        $parts[] = self::outerClose();

        return implode('', $parts);
    }

    private static function outerOpen(string $templateKey): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '.self::MARKER
            .' data-pne-mail-template="'.self::escapeAttr($templateKey).'" bgcolor="#f3f6f9"'
            .' style="width:100%;background-color:#f3f6f9;"><tr><td align="center" style="padding:28px 12px;">'
            .'<table role="presentation" width="680" cellpadding="0" cellspacing="0" bgcolor="#ffffff"'
            .' style="width:100%;max-width:680px;background-color:#ffffff;border:1px solid #dfe5ec;">';
    }

    private static function outerClose(): string
    {
        return '</table></td></tr></table>';
    }

    private static function headerHtml(): string
    {
        return '<tr><td data-pne-mail-header="1" bgcolor="#073b5c" style="padding:24px 30px;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">'
            .'<div style="font-size:20px;line-height:1.3;font-weight:bold;">Platforma Nowoczesnej Edukacji</div>'
            .'<div style="padding-top:5px;font-size:13px;line-height:1.4;color:#dbe7f5;">Praktyczna wiedza dla nauczycieli i dyrektorów</div>'
            .'</td></tr>';
    }

    private static function greetingHtml(): string
    {
        return '<tr><td data-pne-mail-greeting="1" style="padding:28px 30px 8px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;color:#243040;">'
            .'<p style="margin:0;">'.self::escape(self::GREETING).'</p>'
            .'</td></tr>';
    }

    private static function reminderBannerHtml(MailRenderContext $context): string
    {
        $label = $context->reminderTiming === 'day_before'
            ? 'PRZYPOMNIENIE – WEBINAR JUŻ JUTRO!'
            : 'OSTATNIE PRZYPOMNIENIE – WEBINAR JUŻ DZISIAJ!';

        return '<tr><td data-pne-mail-reminder-banner="1" bgcolor="#e63333" style="padding:14px 30px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.4;font-weight:bold;letter-spacing:0.02em;color:#ffffff;text-align:center;">'
            .'<span aria-hidden="true" style="display:inline-block;width:10px;height:10px;border-radius:50%;background-color:#ffffff;margin-right:8px;vertical-align:middle;"></span>'
            .self::escape($label)
            .'</td></tr>';
    }

    private static function webinarLabelHtml(MailRenderContext $context): string
    {
        $label = $context->liveLabel !== ''
            ? 'BEZPŁATNY WEBINAR • '.$context->liveLabel
            : 'BEZPŁATNY WEBINAR';

        $labelUpper = function_exists('mb_strtoupper')
            ? mb_strtoupper($label, 'UTF-8')
            : strtoupper($label);

        return '<tr><td data-pne-mail-webinar-label="1" align="center" style="padding:8px 30px 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.4;letter-spacing:0.04em;color:#073b5c;font-weight:bold;">'
            .self::escape($labelUpper)
            .'</td></tr>';
    }

    private static function webinarCardHtml(MailRenderContext $context, string $registrationHref, string $youtube): string
    {
        $title = trim($context->webinarTitle) !== '' ? trim($context->webinarTitle) : 'Webinar PNE';
        $subtitle = trim($context->webinarSubtitle);
        $liveLabel = trim($context->liveLabel);
        $host = trim($context->hostName);
        if ($host === '—') {
            $host = '';
        }

        $html = '<tr><td data-pne-mail-webinar-card="1" style="padding:12px 30px 24px;font-family:Arial,Helvetica,sans-serif;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f8fafc" style="width:100%;background-color:#f8fafc;border:1px solid #dfe5ec;">'
            .'<tr><td style="padding:22px 22px 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.4;color:#008c95;font-weight:bold;letter-spacing:0.03em;">WEBINAR PNE</td></tr>'
            .'<tr><td style="padding:0 22px 10px;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:1.35;color:#073b5c;font-weight:bold;">'
            .self::escape($title)
            .'</td></tr>';

        if ($subtitle !== '') {
            $html .= '<tr><td style="padding:0 22px 14px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#243040;">'
                .self::escape($subtitle)
                .'</td></tr>';
        }

        $html .= '<tr><td style="padding:0 22px 16px;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;">';

        if ($liveLabel !== '') {
            $html .= self::webinarMetaRowHtml('&#128197;', 'Termin', $liveLabel);
        }

        if ($host !== '') {
            $html .= self::webinarMetaRowHtml('&#128100;', 'Prowadzący', $host);
        }

        if ($context->showCertificate) {
            $html .= '<tr><td colspan="2" style="padding:4px 0 0;">'
                .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#eef7f8" style="width:100%;background-color:#eef7f8;border:1px solid #c9e3e6;">'
                .'<tr><td align="center" style="padding:12px 14px 6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.3;font-weight:bold;letter-spacing:0.03em;color:#008c95;">'
                .'<span aria-hidden="true" style="font-size:18px;line-height:1;vertical-align:middle;">&#128220;</span>'
                .'<span style="display:inline-block;padding-left:8px;vertical-align:middle;">ZAŚWIADCZENIE</span>'
                .'</td></tr>'
                .'<tr><td align="center" style="padding:0 14px 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#073b5c;">'
                .self::CERTIFICATE_NOTE
                .'</td></tr>'
                .'</table></td></tr>';
        }

        $html .= '</table></td></tr>'
            .'<tr><td align="center" style="padding:4px 22px 14px;">'
            .self::buttonHtml($registrationHref, self::CTA_LABEL, '#f7b500', '#073b5c')
            .'</td></tr>';

        if ($youtube !== '') {
            $html .= '<tr><td align="center" style="padding:0 22px 14px;">'
                .self::buttonHtml(self::escapeAttr($youtube), self::YOUTUBE_CTA_LABEL, '#ff0000', '#ffffff')
                .'</td></tr>';
        }

        if ($context->includeRoomCta) {
            $html .= '<tr><td align="center" style="padding:0 22px 18px;">'
                .self::buttonHtml(self::escapeAttr(MaterialDraftTask::ROOM_LINK_PLACEHOLDER), self::ROOM_CTA_LABEL, '#008c95', '#ffffff')
                .'</td></tr>';
        } else {
            $html .= '<tr><td style="padding:0 0 8px;">&nbsp;</td></tr>';
        }

        $html .= '</table></td></tr>';

        return $html;
    }

    private static function webinarMetaRowHtml(string $iconEntity, string $label, string $value): string
    {
        return '<tr>'
            .'<td width="28" valign="top" style="padding:0 8px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.4;color:#008c95;" aria-hidden="true">'.$iconEntity.'</td>'
            .'<td style="padding:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#243040;">'
            .'<div style="font-size:12px;line-height:1.3;font-weight:bold;letter-spacing:0.03em;color:#008c95;padding-bottom:2px;">'.self::escape(mb_strtoupper($label, 'UTF-8')).'</div>'
            .self::escape($value)
            .'</td></tr>';
    }

    private static function ctaOnlyHtml(string $registrationHref, string $youtube, MailRenderContext $context): string
    {
        $html = '<tr><td data-pne-mail-cta="1" align="center" style="padding:16px 30px 24px;font-family:Arial,Helvetica,sans-serif;">'
            .self::buttonHtml($registrationHref, self::CTA_LABEL, '#f7b500', '#073b5c');

        if ($youtube !== '') {
            $html .= '<div style="height:12px;line-height:12px;font-size:12px;">&nbsp;</div>'
                .self::buttonHtml(self::escapeAttr($youtube), self::YOUTUBE_CTA_LABEL, '#ff0000', '#ffffff');
        }

        if ($context->includeRoomCta) {
            $html .= '<div style="height:12px;line-height:12px;font-size:12px;">&nbsp;</div>'
                .self::buttonHtml(self::escapeAttr(MaterialDraftTask::ROOM_LINK_PLACEHOLDER), self::ROOM_CTA_LABEL, '#008c95', '#ffffff');
        }

        return $html.'</td></tr>';
    }

    private static function buttonHtml(string $href, string $label, string $bg, string $color): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="'.$bg.'" style="border-radius:4px;">'
            .'<a href="'.$href.'" style="display:inline-block;padding:13px 26px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.2;color:'.$color.';text-decoration:none;font-weight:bold;">'
            .self::escape($label)
            .'</a></td></tr></table>';
    }

    private static function paidCoursesHtml(MailRenderContext $context): string
    {
        if ($context->paidCourses === []) {
            return '';
        }

        $allUrl = app(PaidCourseOfferBuilder::class)->allTrainingsUrl($context->growthCampaignId);
        $html = '<tr><td data-pne-mail-paid-courses="1" style="padding:8px 30px 24px;font-family:Arial,Helvetica,sans-serif;">'
            .'<div style="font-size:18px;line-height:1.35;color:#073b5c;font-weight:bold;padding-bottom:12px;">Najbliższe płatne szkolenia NODN Platforma Nowoczesnej Edukacji</div>';

        foreach ($context->paidCourses as $course) {
            if (! is_array($course)) {
                continue;
            }
            $html .= self::paidCourseCardHtml($course);
        }

        $html .= '<div style="padding-top:8px;font-size:14px;line-height:1.5;">'
            .'<a href="'.self::escapeAttr($allUrl).'" style="color:#008c95;font-weight:bold;text-decoration:underline;">Zobacz wszystkie najbliższe szkolenia →</a>'
            .'</div></td></tr>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private static function paidCourseCardHtml(array $course): string
    {
        $meta = array_values(array_filter([
            trim((string) ($course['date_label'] ?? '')),
            trim((string) ($course['time_label'] ?? '')),
            trim((string) ($course['duration_label'] ?? '')),
        ]));
        $instructor = trim((string) ($course['instructor_name'] ?? ''));
        $price = trim((string) ($course['price_label'] ?? ''));
        $promo = trim((string) ($course['promotion_label'] ?? ''));
        $omnibus = trim((string) ($course['omnibus_label'] ?? ''));
        $url = trim((string) ($course['url'] ?? ''));
        $title = trim((string) ($course['title'] ?? ''));

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;background-color:#ffffff;border:1px solid #dfe5ec;margin:0 0 12px;">'
            .'<tr><td style="padding:16px 18px;font-family:Arial,Helvetica,sans-serif;">';

        if ($meta !== []) {
            $html .= '<div style="font-size:12px;line-height:1.4;color:#008c95;font-weight:bold;padding-bottom:6px;">'
                .self::escape(implode(' • ', $meta))
                .'</div>';
        }

        $html .= '<div style="font-size:16px;line-height:1.4;color:#073b5c;font-weight:bold;padding-bottom:6px;">'
            .self::escape($title)
            .'</div>';

        if ($instructor !== '') {
            $html .= '<div style="font-size:13px;line-height:1.45;color:#243040;padding-bottom:8px;">'
                .self::escape('Prowadzący: '.$instructor)
                .'</div>';
        }

        if ($price !== '') {
            $html .= '<div style="font-size:15px;line-height:1.45;color:#243040;font-weight:bold;padding-bottom:4px;">'
                .self::escape($price)
                .'</div>';
        }

        if ($promo !== '') {
            $html .= '<div style="font-size:12px;line-height:1.45;color:#073b5c;padding-bottom:4px;">'
                .self::escape($promo)
                .'</div>';
        }

        if ($omnibus !== '') {
            $html .= '<div style="font-size:12px;line-height:1.45;color:#6b7785;padding-bottom:10px;">'
                .self::escape($omnibus)
                .'</div>';
        }

        if ($url !== '') {
            $html .= '<div style="padding-top:6px;">'
                .self::buttonHtml(self::escapeAttr($url), 'ZOBACZ SZCZEGÓŁY', '#073b5c', '#ffffff')
                .'</div>';
        }

        return $html.'</td></tr></table>';
    }

    private static function footerHtml(): string
    {
        return '<tr><td data-pne-mail-footer="1" align="center" style="padding:18px 30px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b7785;border-top:1px solid #e6eaee;">'
            .'Akredytowany Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>'
            .'Platforma Nowoczesnej Edukacji<br>'
            .'<a href="[unsubscribe]" style="color:#6b7785;text-decoration:underline;">Wypisz się z listy</a>'
            .'</td></tr>';
    }

    private static function stripTechnicalMarkers(string $html): string
    {
        $markers = [
            MaterialDraftTask::LINK_PLACEHOLDER,
            MaterialDraftTask::ROOM_LINK_PLACEHOLDER,
        ];

        if (! self::isHtml($html)) {
            $lines = preg_split('/\R/u', $html) ?: [];
            $kept = [];
            foreach ($lines as $line) {
                $trim = trim($line);
                if (in_array($trim, $markers, true) || preg_match('/^zapisz się:?$/iu', $trim) === 1) {
                    continue;
                }
                $kept[] = $line;
            }

            return trim(implode("\n", $kept));
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="pne-mail-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);
        $root = $document->getElementById('pne-mail-root');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        foreach ($root->getElementsByTagName('a') as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $href = $link->getAttribute('href');
            if (in_array($href, $markers, true)) {
                $link->parentNode?->removeChild($link);
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        $plain = html_entity_decode(strip_tags($result, '<p><br><strong><b><em><i><u><ul><ol><li><a>'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach ($markers as $marker) {
            $result = str_replace($marker, '', $result);
        }

        return trim($result !== '' ? $result : $plain);
    }

    private static function stripLeadingGreeting(string $html): string
    {
        $pattern = '/^(?:\s|<p[^>]*>)*(?:Dzień dobry(?:\s*\[Name,fallback=[^\]]*\])?[!,.]?\s*)+/iu';
        if (! self::isHtml($html)) {
            return trim((string) preg_replace($pattern, '', $html, 1));
        }

        return trim((string) preg_replace(
            '/^(?:<p[^>]*>\s*)?Dzień dobry(?:\s*\[Name,fallback=[^\]]*\])?[!,.]?\s*(?:<br\s*\/?>)?\s*/iu',
            '<p>',
            $html,
            1,
        ));
    }

    private static function plainToContent(string $plain): string
    {
        $blocks = preg_split('/\R{2,}/u', trim($plain)) ?: [];
        $html = '';

        foreach ($blocks as $block) {
            if (trim($block) !== '') {
                $html .= self::renderBlock($block);
            }
        }

        return $html;
    }

    private static function renderBlock(string $block): string
    {
        $lines = preg_split('/\R/u', trim($block)) ?: [];
        $html = '';
        $paragraph = [];
        $list = [];

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph === []) {
                return;
            }

            $html .= '<p>'.implode('<br>', array_map(self::escape(...), $paragraph)).'</p>';
            $paragraph = [];
        };
        $flushList = function () use (&$list, &$html): void {
            if ($list === []) {
                return;
            }

            $items = '';
            foreach ($list as $item) {
                $items .= '<li>'.self::escape($item).'</li>';
            }
            $html .= '<ul>'.$items.'</ul>';
            $list = [];
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if (
                $trim === ''
                || $trim === MaterialDraftTask::LINK_PLACEHOLDER
                || $trim === MaterialDraftTask::ROOM_LINK_PLACEHOLDER
                || preg_match('/^zapisz się:?$/iu', $trim) === 1
            ) {
                continue;
            }

            if (preg_match('/^(?:[-*•]|\d+[.)])\s+(.+)$/u', $trim, $match) === 1) {
                $flushParagraph();
                $list[] = $match[1];

                continue;
            }

            $flushList();
            $paragraph[] = $trim;
        }

        $flushList();
        $flushParagraph();

        return $html;
    }

    private static function emailize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="pne-mail-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $root = $document->getElementById('pne-mail-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        foreach ($root->getElementsByTagName('*') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $style = match ($node->tagName) {
                'p' => 'margin:0 0 16px;',
                'ul', 'ol' => 'margin:0 0 16px;padding:0 0 0 22px;',
                'li' => 'margin:0 0 8px;',
                'a' => 'color:#008c95;',
                default => null,
            };
            if ($style !== null) {
                $node->setAttribute('style', $style);
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private static function extractBody(string $html): string
    {
        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $node = null;
        foreach ($document->getElementsByTagName('td') as $cell) {
            if ($cell instanceof DOMElement && $cell->getAttribute('data-pne-mail-body') === '1') {
                $node = $cell;
                break;
            }
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $document->saveHTML($child);
        }

        return $inner;
    }

    private static function toPlain(string $body): string
    {
        if (! self::isHtml($body) && ! str_contains($body, 'data-pne-mail')) {
            return trim($body);
        }

        $html = self::editorContent($body);
        $html = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*li\b[^>]*>/iu', "\n- ", $html) ?? $html;
        $html = preg_replace('/<\/\s*(p|div|h[1-6]|ul|ol|li)\s*>/iu', "\n\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R/u', $text) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (
                $trim === MaterialDraftTask::LINK_PLACEHOLDER
                || $trim === MaterialDraftTask::ROOM_LINK_PLACEHOLDER
                || preg_match('/^zapisz się:?$/iu', $trim) === 1
            ) {
                continue;
            }
            $kept[] = $trim;
        }

        return trim((string) preg_replace("/\n{3,}/u", "\n\n", implode("\n", $kept)));
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'], true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if ($tag === 'a' && preg_match(self::SAFE_HREF, $child->getAttribute('href')) !== 1) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            self::sanitizeChildren($child);

            if (! in_array($tag, self::ALLOWED, true)) {
                self::unwrap($child);

                continue;
            }

            $href = $tag === 'a' ? $child->getAttribute('href') : '';
            $attributes = [];
            foreach ($child->attributes ?? [] as $attribute) {
                $attributes[] = $attribute->name;
            }
            foreach ($attributes as $name) {
                $child->removeAttribute($name);
            }

            if ($tag === 'a' && preg_match(self::SAFE_HREF, $href) === 1) {
                $child->setAttribute('href', $href);
            }
        }
    }

    private static function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private static function isHtml(string $value): bool
    {
        return preg_match('/<[a-z!\/]/i', $value) === 1;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

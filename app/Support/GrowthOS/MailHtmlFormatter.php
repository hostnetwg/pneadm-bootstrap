<?php

namespace App\Support\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Turns plain or limited body content into one email-safe HTML document.
 * The editor never receives this document: header, card, button and footer are applied here (DEC-049).
 */
final class MailHtmlFormatter
{
    public const MARKER = 'data-pne-mail="1"';

    public const CTA_LABEL = 'Zapisz się na webinar';

    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a'];

    private const SAFE_HREF = '/^(https?:\/\/|mailto:)/i';

    public static function format(string $plain, ?string $templateKey = null): string
    {
        return self::render(self::plainToContent($plain), $templateKey);
    }

    public static function formatComposedDraft(string $draft, ?string $templateKey = null): string
    {
        $fields = MaterialDraftTask::parseMainMail($draft);
        if (str_contains($fields['body'], self::MARKER)) {
            return $draft;
        }

        $subjects = array_values(array_filter(
            [$fields['subject'], ...$fields['alternatives']],
            static fn (string $subject): bool => $subject !== '',
        ));

        return MaterialDraftTask::composeMainMail($subjects, $fields['preheader'], self::format($fields['body'], $templateKey));
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

    public static function render(string $content, ?string $templateKey = null): string
    {
        $key = MailTemplates::key($templateKey);
        $theme = MailTemplates::theme($key);
        $inner = self::emailize(self::sanitizeContent($content));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '.self::MARKER.' data-pne-mail-template="'.self::escape($key).'" style="background-color:'.$theme['page'].';">'
            .'<tr><td align="center" style="padding:24px 12px;">'
            .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:'.$theme['card'].';border:'.$theme['border'].';">'
            .self::headerRow($theme)
            .'<tr><td data-pne-mail-body="1" style="padding:'.$theme['content_pad'].';font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#243040;">'
            .$inner
            .'</td></tr>'
            .'<tr><td data-pne-mail-cta="1" style="padding:8px 28px 24px;font-family:Arial,Helvetica,sans-serif;">'
            .self::button($theme['button'])
            .'</td></tr>'
            .'<tr><td data-pne-mail-footer="1" style="padding:16px 28px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.4;color:#6b7785;border-top:1px solid #e6eaee;">'
            .self::escape($theme['footer'])
            .'</td></tr>'
            .'</table></td></tr></table>';
    }

    public static function finalHtml(string $content, ?string $templateKey = null): string
    {
        $content = self::editorContent($content);

        return self::isHtml($content)
            ? self::render($content, $templateKey)
            : self::format($content, $templateKey);
    }

    public static function copyHtml(string $preheader, string $content, ?string $templateKey = null): string
    {
        return self::preheaderHtml($preheader).self::finalHtml($content, $templateKey);
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

    private static function headerRow(array $theme): string
    {
        return '<tr><td data-pne-mail-header="1" style="padding:18px 28px;font-family:Arial,Helvetica,sans-serif;font-size:13px;letter-spacing:0.04em;font-weight:bold;background-color:'.$theme['header_bg'].';color:'.$theme['header_color'].';">'
            .self::escape($theme['header'])
            .'</td></tr>';
    }

    private static function button(string $color): string
    {
        $href = MaterialDraftTask::LINK_PLACEHOLDER;

        return '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
            .'<td bgcolor="'.$color.'" style="border-radius:6px;">'
            .'<a href="'.$href.'" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.2;color:#ffffff;text-decoration:none;font-weight:bold;">'.self::CTA_LABEL.'</a>'
            .'</td></tr></table>';
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
            if ($trim === '' || $trim === MaterialDraftTask::LINK_PLACEHOLDER || preg_match('/^zapisz się:?$/iu', $trim) === 1) {
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
                'a' => 'color:#1e4d8c;',
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

        $node = $document->getElementsByTagName('td')->item(0);
        foreach ($document->getElementsByTagName('td') as $cell) {
            if ($cell instanceof DOMElement && $cell->getAttribute('data-pne-mail-body') === '1') {
                $node = $cell;
                break;
            }
        }

        if (! $node instanceof DOMElement || $node->getAttribute('data-pne-mail-body') !== '1') {
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
            if ($trim === MaterialDraftTask::LINK_PLACEHOLDER || preg_match('/^zapisz się:?$/iu', $trim) === 1) {
                continue;
            }
            $kept[] = $trim;
        }

        $plain = trim((string) preg_replace("/\n{3,}/u", "\n\n", implode("\n", $kept)));

        return $plain;
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
}

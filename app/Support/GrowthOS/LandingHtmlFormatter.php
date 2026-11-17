<?php

namespace App\Support\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Bootstrap 5 fragment for a webinar registration page on pnedu.pl.
 * The public layout already supplies the document, menu and container.
 */
final class LandingHtmlFormatter
{
    private const ALLOWED = ['section', 'div', 'h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'span', 'br'];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        $html = preg_replace('/^```(?:html)?\s*/i', '', $html) ?? $html;
        $html = preg_replace('/\s*```$/', '', $html) ?? $html;
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            $paragraphs = preg_split("/\n{2,}/", $html) ?: [];
            $html = implode('', array_map(
                static fn (string $paragraph): string => '<p>'.htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>',
                array_filter($paragraphs, static fn (string $paragraph): bool => trim($paragraph) !== ''),
            ));
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="pne-landing-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        $root = $document->getElementById('pne-landing-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);
        self::pruneEmpty($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        $result = str_ireplace(
            [
                MaterialDraftTask::LINK_PLACEHOLDER,
                '%5BLINK%20DO%20ZAPISU%5D',
                '&#91;LINK DO ZAPISU&#93;',
                '&#x5B;LINK DO ZAPISU&#x5D;',
            ],
            '',
            $result,
        );

        return trim($result);
    }

    /**
     * Safe HTML for the admin preview. The registration button stays on pnedu.pl.
     */
    public static function preview(string $html): string
    {
        return self::sanitize($html);
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (self::isSignupControl($child) || in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta'], true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED, true)) {
                $moved = [];
                while ($child->firstChild !== null) {
                    $moved[] = $child->firstChild;
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);
                foreach ($moved as $node) {
                    if ($node instanceof DOMElement) {
                        self::sanitizeChildren($node->parentNode ?? $node);
                    }
                }

                continue;
            }

            self::cleanAttributes($child);
            self::sanitizeChildren($child);
        }
    }

    private static function cleanAttributes(DOMElement $element): void
    {
        $allowed = [];
        if ($element->hasAttribute('class')) {
            $classes = array_filter(
                preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [],
                static fn (string $class): bool => preg_match('/^[a-zA-Z][a-zA-Z0-9:_-]{0,48}$/', $class) === 1,
            );
            if ($classes !== []) {
                $allowed['class'] = implode(' ', $classes);
            }
        }

        if (strtolower($element->tagName) === 'a' && $element->hasAttribute('href')) {
            $href = trim($element->getAttribute('href'));
            if ($href === MaterialDraftTask::LINK_PLACEHOLDER || str_starts_with(strtolower($href), 'https://')) {
                $allowed['href'] = $href;
            }
        }

        while ($element->attributes->length > 0) {
            $element->removeAttribute($element->attributes->item(0)->name);
        }

        foreach ($allowed as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }

    private static function isSignupControl(DOMElement $element): bool
    {
        $tag = strtolower($element->tagName);
        $class = $element->getAttribute('class');
        $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($tag === 'a' && preg_match('/(?:^|\s)btn(?:\s|$)/', $class) === 1) {
            return true;
        }

        if ($tag === 'a' && str_contains($href, MaterialDraftTask::LINK_PLACEHOLDER)) {
            return true;
        }

        $label = trim(mb_strtolower(preg_replace('/\s+/u', ' ', $element->textContent) ?? ''));
        $label = trim($label, " \t\n\r\0\x0B.,:;!");

        return in_array($tag, ['a', 'h2', 'h3', 'p'], true) && in_array($label, [
            'zapisz się',
            'zapisz się bezpłatnie',
            'zapiszcie się',
            'zapiszcie się bezpłatnie',
            'zapiszą się państwo',
            'zapiszą się państwo bezpłatnie',
        ], true);
    }

    private static function pruneEmpty(DOMElement $element): void
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            self::pruneEmpty($child);
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['section', 'div', 'p'], true) && trim($child->textContent) === '') {
                $child->parentNode?->removeChild($child);
            }
        }
    }
}

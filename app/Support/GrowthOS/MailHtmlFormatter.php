<?php

namespace App\Support\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;

final class MailHtmlFormatter
{
    public const MARKER = 'data-pne-mail="1"';

    public static function format(string $plain): string
    {
        $blocks = preg_split('/\R{2,}/u', trim($plain)) ?: [];
        $inner = '';

        foreach ($blocks as $block) {
            if (trim($block) !== '') {
                $inner .= self::renderBlock($block);
            }
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '.self::MARKER.' style="background-color:#f4f6f8;">'
            .'<tr><td align="center" style="padding:24px 12px;">'
            .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e6eaee;">'
            .'<tr><td data-pne-mail-body="1" style="padding:32px 28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#243040;">'
            .$inner
            .'</td></tr></table></td></tr></table>';
    }

    public static function formatComposedDraft(string $draft): string
    {
        $fields = MaterialDraftTask::parseMainMail($draft);
        if (str_contains($fields['body'], self::MARKER)) {
            return $draft;
        }

        $subjects = array_values(array_filter(
            [$fields['subject'], ...$fields['alternatives']],
            static fn (string $subject): bool => $subject !== '',
        ));

        return MaterialDraftTask::composeMainMail($subjects, $fields['preheader'], self::format($fields['body']));
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

            $html .= '<p style="margin:0 0 16px;">'.implode('<br>', array_map(self::escape(...), $paragraph)).'</p>';
            $paragraph = [];
        };
        $flushList = function () use (&$list, &$html): void {
            if ($list === []) {
                return;
            }

            $items = '';
            foreach ($list as $item) {
                $items .= '<li style="margin:0 0 8px;">'.self::escape($item).'</li>';
            }
            $html .= '<ul style="margin:0 0 16px;padding:0 0 0 22px;">'.$items.'</ul>';
            $list = [];
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }

            if ($trim === MaterialDraftTask::LINK_PLACEHOLDER) {
                if (count($paragraph) === 1 && preg_match('/^zapisz się:?$/iu', $paragraph[0]) === 1) {
                    $paragraph = [];
                }
                $flushParagraph();
                $flushList();
                $html .= self::button();

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

    private static function button(): string
    {
        $href = MaterialDraftTask::LINK_PLACEHOLDER;

        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;"><tr>'
            .'<td bgcolor="#1e4d8c" style="border-radius:6px;">'
            .'<a href="'.$href.'" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.2;color:#ffffff;text-decoration:none;font-weight:bold;">Zapisz się na webinar</a>'
            .'</td></tr></table>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

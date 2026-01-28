<?php

namespace App\Services\Certificate;

use Barryvdh\DomPDF\Facade\Pdf;

class PDFGenerator
{
    /**
     * Generuje PDF z HTML
     *
     * @param string $html HTML do konwersji
     * @param array $settings Ustawienia (orientation, font_family, etc.)
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generate(string $html, array $settings = [])
    {
        $orientation = $settings['orientation'] ?? 'portrait';
        $fontFamily = $settings['font_family'] ?? 'DejaVu Sans';
        
        return Pdf::loadHTML($html)
            ->setPaper('A4', $orientation)
            ->setOptions([
                'defaultFont' => $fontFamily,
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
    }
}








<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TemplateBuilderService
{
    /**
     * Generuje plik blade z konfiguracji szablonu
     */
    public function generateBladeFile($config, $slug)
    {
        $bladeContent = $this->buildBladeContent($config);
        
        $fileName = Str::slug($slug) . '.blade.php';
        $path = resource_path("views/certificates/{$fileName}");
        
        // Tworzenie folderu jeśli nie istnieje
        $directory = dirname($path);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        
        File::put($path, $bladeContent);
        
        return $fileName;
    }

    /**
     * Buduje treść pliku blade na podstawie konfiguracji
     */
    private function buildBladeContent($config)
    {
        $blocks = $config['blocks'] ?? [];
        $settings = $config['settings'] ?? [];
        
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang=\"pl\">\n";
        $html .= "<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "    <title>Zaświadczenie</title>\n";
        $html .= $this->buildStyles($settings);
        $html .= "</head>\n";
        $html .= "<body>\n";
        
        // Generowanie bloków
        foreach ($blocks as $block) {
            $html .= $this->buildBlock($block);
        }
        
        $html .= "</body>\n";
        $html .= "</html>\n";
        
        return $html;
    }

    /**
     * Buduje sekcję stylów
     */
    private function buildStyles($settings)
    {
        $fontFamily = $settings['font_family'] ?? 'DejaVu Sans';
        $orientation = $settings['orientation'] ?? 'portrait';
        
        $styles = "    <style>\n";
        $styles .= "        body {\n";
        $styles .= "            font-family: \"{$fontFamily}\", sans-serif;\n";
        $styles .= "            text-align: center;\n";
        $styles .= "            position: relative;\n";
        $styles .= "            margin: 0;\n";
        $styles .= "            padding: 10px 20px;\n";
        $styles .= "            height: 100%;\n";
        $styles .= "        }\n";
        
        if ($orientation === 'landscape') {
            $styles .= "        @page {\n";
            $styles .= "            size: A4 landscape;\n";
            $styles .= "        }\n";
        }
        
        $styles .= "        .certificate-title {\n";
        $styles .= "            font-size: " . ($settings['title_size'] ?? '38') . "px;\n";
        $styles .= "            font-weight: bold;\n";
        $styles .= "            color: " . ($settings['title_color'] ?? '#000') . ";\n";
        $styles .= "        }\n";
        $styles .= "        h3 {\n";
        $styles .= "            margin-bottom: 5px;\n";
        $styles .= "        }\n";
        $styles .= "        h2 {\n";
        $styles .= "            margin-top: 5px;\n";
        $styles .= "        }\n";
        $styles .= "        .course-title {\n";
        $styles .= "            word-break: keep-all;\n";
        $styles .= "            font-size: " . ($settings['course_title_size'] ?? '32') . "px;\n";
        $styles .= "            font-weight: bold;\n";
        $styles .= "        }\n";
        $styles .= "        .bold {\n";
        $styles .= "            font-weight: bold;\n";
        $styles .= "        }\n";
        $styles .= "        .date-section {\n";
        $styles .= "            position: absolute;\n";
        $styles .= "            bottom: 180px;\n";
        $styles .= "            left: 15px;\n";
        $styles .= "            width: calc(50% - 15px);\n";
        $styles .= "            text-align: left;\n";
        $styles .= "        }\n";
        $styles .= "        .instructor-section {\n";
        $styles .= "            position: absolute;\n";
        $styles .= "            top: 550px;\n";
        $styles .= "            right: 15px;\n";
        $styles .= "            width: calc(50% - 15px);\n";
        $styles .= "            text-align: right;\n";
        $styles .= "            display: flex;\n";
        $styles .= "            flex-direction: column;\n";
        $styles .= "            align-items: flex-end;\n";
        $styles .= "            gap: 3px;\n";
        $styles .= "        }\n";
        $styles .= "        .instructor-section p {\n";
        $styles .= "            margin: 0;\n";
        $styles .= "            position: relative;\n";
        $styles .= "            z-index: 10;\n";
        $styles .= "        }\n";
        $styles .= "        .instructor-section .signature-img {\n";
        $styles .= "            position: relative;\n";
        $styles .= "            z-index: 1;\n";
        $styles .= "        }\n";
        $styles .= "        .footer {\n";
        $styles .= "            font-size: 10px;\n";
        $styles .= "            text-align: center;\n";
        $styles .= "            position: absolute;\n";
        $styles .= "            bottom: 30px;\n";
        $styles .= "            left: 0;\n";
        $styles .= "            width: 100%;\n";
        $styles .= "        }\n";
        $styles .= "    </style>\n";
        
        return $styles;
    }

    /**
     * Buduje pojedynczy blok szablonu
     */
    private function buildBlock($block)
    {
        $type = $block['type'] ?? '';
        $config = $block['config'] ?? [];
        
        switch ($type) {
            case 'header':
                return $this->buildHeaderBlock($config);
            case 'participant_info':
                return $this->buildParticipantInfoBlock($config);
            case 'course_info':
                return $this->buildCourseInfoBlock($config);
            case 'instructor_signature':
                return $this->buildInstructorSignatureBlock($config);
            case 'footer':
                return $this->buildFooterBlock($config);
            case 'custom_text':
                return $this->buildCustomTextBlock($config);
            default:
                return '';
        }
    }

    /**
     * Blok nagłówka
     */
    private function buildHeaderBlock($config)
    {
        $html = "    <h1 class=\"certificate-title\">" . ($config['title'] ?? 'ZAŚWIADCZENIE') . "</h1>\n";
        
        return $html;
    }

    /**
     * Blok informacji o uczestniku
     */
    private function buildParticipantInfoBlock($config)
    {
        $html = "    <p>Pan/i</p>\n";
        $html .= "    <h2>{{ \$participant->first_name }} {{ \$participant->last_name }}</h2>\n\n";
        
        if (!empty($config['show_birth_info'])) {
            $html .= "    @if (!empty(\$participant->birth_date) && !empty(\$participant->birth_place))\n";
            $html .= "        <p>urodzony/a: {{ \\Carbon\\Carbon::parse(\$participant->birth_date)->format('d.m.Y') }}r. w miejscowości {{ \$participant->birth_place }}</p>\n";
            $html .= "    @else\n";
            $html .= "        <p>&nbsp;</p>\n";
            $html .= "    @endif\n\n";
        }
        
        return $html;
    }

    /**
     * Blok informacji o kursie
     */
    private function buildCourseInfoBlock($config)
    {
        $html = "    <p>ukończył/a szkolenie</p>\n";
        $html .= "    <p>zorganizowane w dniu {{ \\Carbon\\Carbon::parse(\$course->start_date)->format('d.m.Y') }}r. ";
        
        if (!empty($config['show_duration'])) {
            $html .= "w wymiarze {{ \$durationMinutes }} minut, ";
        }
        
        $html .= "przez</p>\n\n";
        $html .= "    <p class=\"bold\">" . ($config['organizer_name'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji') . "</p>\n\n";
        $html .= "    <h3>TEMAT SZKOLENIA</h3>\n";
        $html .= "    <h2 class=\"course-title\">{{ \$course->title }}</h2>\n\n";
        
        if (!empty($config['show_description'])) {
            // Sprawdź czy description nie jest puste przed wyświetleniem "Zakres:"
            $html .= "    @php\n";
            $html .= "        \$description = trim(\$course->description ?? '');\n";
            $html .= "        if (!empty(\$description)) {\n";
            $html .= "            // Dynamiczne dostosowanie rozmiaru czcionki na podstawie długości zakresu\n";
            $html .= "            \$itemCount = 0;\n";
            $html .= "            if (preg_match('/^\\\\d+\\\\.\\\\s*/m', \$description)) {\n";
            $html .= "                \$itemCount = preg_match_all('/^\\\\d+\\\\.\\\\s*/m', \$description);\n";
            $html .= "            }\n";
            $html .= "            // Ustaw rozmiar czcionki na podstawie liczby punktów\n";
            $html .= "            \$fontSize = \$itemCount > 4 ? '13px' : '16px';\n";
            $html .= "            \$marginBottom = \$itemCount > 4 ? '2px' : '5px';\n";
            $html .= "            echo '<p>Zakres:</p>';\n";
            $html .= "            if (preg_match('/^\\\\d+\\\\.\\\\s*/m', \$description)) {\n";
            $html .= "                // To jest lista numerowana - formatuj jako <ol> z dynamiczną czcionką\n";
            $html .= "                \$items = preg_split('/\\\\n(?=\\\\d+\\\\.)/', \$description);\n";
            $html .= "                echo '<ol style=\"text-align: left; margin-left: 0px; font-size: ' . \$fontSize . ';\">';\n";
            $html .= "                foreach (\$items as \$item) {\n";
            $html .= "                    \$cleanItem = preg_replace('/^\\\\d+\\\\.\\\\s*/', '', trim(\$item));\n";
            $html .= "                    if (\$cleanItem) {\n";
            $html .= "                        echo '<li style=\"text-align: left; margin-bottom: ' . \$marginBottom . ';\">' . htmlspecialchars(\$cleanItem) . '</li>';\n";
            $html .= "                    }\n";
            $html .= "                }\n";
            $html .= "                echo '</ol>';\n";
            $html .= "            } else {\n";
            $html .= "                // To jest zwykły tekst - jako akapit wyrównany do lewej z dynamiczną czcionką\n";
            $html .= "                echo '<p style=\"text-align: left; font-size: ' . \$fontSize . ';\">' . htmlspecialchars(\$description) . '</p>';\n";
            $html .= "            }\n";
            $html .= "        }\n";
            $html .= "    @endphp\n\n";
        }
        
        return $html;
    }

    /**
     * Blok podpisu instruktora
     */
    private function buildInstructorSignatureBlock($config)
    {
        $html = "    <div class=\"date-section\">\n";
        $html .= "        <p style=\"margin: 0;\">Data, {{ \\Carbon\\Carbon::parse(\$course->end_date)->format('d.m.Y') }}r.<br>\n";
        $html .= "        Nr rejestru: {{ \$certificateNumber }}</p>\n";
        $html .= "    </div>\n\n";
        
        $html .= "    <div class=\"instructor-section\">\n";
        $html .= "        <p>\n";
        $html .= "            @php\n";
        $html .= "                // Określanie tytułu na podstawie płci\n";
        $html .= "                \$title = match(\$instructor->gender ?? 'prefer_not_to_say') {\n";
        $html .= "                    'male' => 'prowadzący:',\n";
        $html .= "                    'female' => 'prowadząca:',\n";
        $html .= "                    'other' => 'trener:',\n";
        $html .= "                    default => 'prowadzący/a:'\n";
        $html .= "                };\n";
        $html .= "            @endphp\n";
        $html .= "            {{ \$title }}<br>\n";
        $html .= "            <span class=\"bold\">{{ \$instructor->first_name }} {{ \$instructor->last_name }}</span>\n";
        $html .= "        </p>\n";
        $html .= "        \n";
        $html .= "        @if(!empty(\$instructor->signature))\n";
        $html .= "            @php\n";
        $html .= "                // Obsługa ścieżki do grafiki podpisu\n";
        $html .= "                if (\$isPdfMode ?? false) {\n";
        $html .= "                    // Dla PDF używamy base64 encoding - najpewniejsze rozwiązanie\n";
        $html .= "                    \$signatureFile = storage_path('app/public/' . \$instructor->signature);\n";
        $html .= "                    if (file_exists(\$signatureFile)) {\n";
        $html .= "                        \$signatureSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents(\$signatureFile));\n";
        $html .= "                    } else {\n";
        $html .= "                        \$signatureSrc = null;\n";
        $html .= "                    }\n";
        $html .= "                } else {\n";
        $html .= "                    // Dla HTML używamy asset()\n";
        $html .= "                    \$signatureSrc = asset('storage/' . \$instructor->signature);\n";
        $html .= "                }\n";
        $html .= "            @endphp\n";
        $html .= "            @if(\$signatureSrc)\n";
        $html .= "                <img src=\"{{ \$signatureSrc }}\" alt=\"Podpis\" class=\"signature-img\" style=\"max-width: 200px; max-height: 80px; width: auto; height: auto;\">\n";
        $html .= "            @endif\n";
        $html .= "        @endif\n";
        $html .= "    </div>\n\n";
        
        return $html;
    }

    /**
     * Blok stopki
     */
    private function buildFooterBlock($config)
    {
        $footerText = $config['text'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -';
        
        $html = "    <div class=\"footer\">\n";
        
        // Logo w stopce (jeśli włączone)
        if (!empty($config['show_logo']) && !empty($config['logo_path'])) {
            $logoSize = $config['logo_size'] ?? 120;
            $logoPath = $config['logo_path'];
            $logoPosition = $config['logo_position'] ?? 'center';
            
            $html .= "        <div style=\"text-align: {$logoPosition}; margin-bottom: 15px;\">\n";
            $html .= "            @php\n";
            $html .= "                \$logoPath = '{$logoPath}';\n";
            $html .= "                if (\$isPdfMode ?? false) {\n";
            $html .= "                    // Dla PDF używamy base64 encoding\n";
            $html .= "                    \$logoFile = storage_path('app/public/' . \$logoPath);\n";
            $html .= "                    \$logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents(\$logoFile));\n";
            $html .= "                } else {\n";
            $html .= "                    // Dla HTML używamy asset()\n";
            $html .= "                    \$logoSrc = asset('storage/' . \$logoPath);\n";
            $html .= "                }\n";
            $html .= "            @endphp\n";
            $html .= "            <img src=\"{{ \$logoSrc }}\" alt=\"Logo\" style=\"max-width: {$logoSize}px; height: auto;\">\n";
            $html .= "        </div>\n";
        }
        
        $html .= "        {$footerText}\n";
        $html .= "    </div>\n";
        
        return $html;
    }

    /**
     * Blok niestandardowego tekstu
     */
    private function buildCustomTextBlock($config)
    {
        $text = $config['text'] ?? '';
        $align = $config['align'] ?? 'center';
        
        return "    <p style=\"text-align: {$align};\">{$text}</p>\n";
    }

    /**
     * Pobiera dostępne typy bloków
     */
    public function getAvailableBlocks()
    {
        return [
            'header' => [
                'name' => 'Nagłówek',
                'description' => 'Tytuł certyfikatu',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Tytuł', 'default' => 'ZAŚWIADCZENIE']
                ]
            ],
            'participant_info' => [
                'name' => 'Dane uczestnika',
                'description' => 'Imię, nazwisko i dane urodzenia',
                'fields' => [
                    'show_birth_info' => ['type' => 'checkbox', 'label' => 'Pokaż datę i miejsce urodzenia']
                ]
            ],
            'course_info' => [
                'name' => 'Informacje o kursie',
                'description' => 'Temat szkolenia, organizator, czas trwania',
                'fields' => [
                    'show_duration' => ['type' => 'checkbox', 'label' => 'Pokaż czas trwania', 'default' => true],
                    'show_description' => ['type' => 'checkbox', 'label' => 'Pokaż zakres szkolenia', 'default' => true],
                    'organizer_name' => ['type' => 'textarea', 'label' => 'Nazwa organizatora', 'default' => 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji']
                ]
            ],
            'instructor_signature' => [
                'name' => 'Podpis prowadzącego',
                'description' => 'Podpis instruktora, data i numer rejestru',
                'fields' => []
            ],
            'footer' => [
                'name' => 'Stopka',
                'description' => 'Stopka dokumentu z opcjonalnym logo',
                'fields' => [
                    'show_logo' => ['type' => 'checkbox', 'label' => 'Pokaż logo w stopce'],
                    'logo_path' => ['type' => 'text', 'label' => 'Ścieżka do logo'],
                    'logo_size' => ['type' => 'number', 'label' => 'Rozmiar logo (px)', 'default' => 120],
                    'logo_position' => ['type' => 'select', 'label' => 'Pozycja logo', 'options' => ['left' => 'Lewo', 'center' => 'Środek', 'right' => 'Prawo'], 'default' => 'center'],
                    'text' => ['type' => 'textarea', 'label' => 'Treść stopki', 'default' => 'Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -']
                ]
            ],
            'custom_text' => [
                'name' => 'Własny tekst',
                'description' => 'Dowolny tekst',
                'fields' => [
                    'text' => ['type' => 'textarea', 'label' => 'Treść'],
                    'align' => ['type' => 'select', 'label' => 'Wyrównanie', 'options' => ['left' => 'Do lewej', 'center' => 'Do środka', 'right' => 'Do prawej'], 'default' => 'center']
                ]
            ]
        ];
    }
}


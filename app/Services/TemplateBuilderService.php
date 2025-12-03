<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TemplateBuilderService
{
    /**
     * Generuje plik blade z konfiguracji szablonu
     * Zapisuje w pakiecie (dev) lub w aplikacji (produkcja)
     */
    public function generateBladeFile($config, $slug)
    {
        $bladeContent = $this->buildBladeContent($config);
        
        $fileName = Str::slug($slug) . '.blade.php';
        
        // Sprawdź czy pakiet jest zapisywalny
        $packagePath = $this->getPackagePath();
        $isPackageWritable = $this->isPackageWritable();
        
        if ($isPackageWritable && $packagePath) {
            // Dev: zapisz w pakiecie
            $packageFilePath = $packagePath . '/resources/views/certificates/' . $fileName;
            $packageDirectory = dirname($packageFilePath);
            
            if (!File::exists($packageDirectory)) {
                File::makeDirectory($packageDirectory, 0755, true);
            }
            
            try {
                File::put($packageFilePath, $bladeContent);
                \Log::info('Template saved to package', [
                    'slug' => $slug,
                    'package_path' => $packageFilePath
                ]);
                return $fileName;
            } catch (\Exception $e) {
                \Log::error('Failed to save template to package', [
                    'slug' => $slug,
                    'package_path' => $packageFilePath,
                    'error' => $e->getMessage()
                ]);
                // Fallback do aplikacji
                return $this->saveBladeToApp($bladeContent, $fileName, $slug);
            }
        } else {
            // Produkcja: zapisz w aplikacji
            return $this->saveBladeToApp($bladeContent, $fileName, $slug);
        }
    }
    
    /**
     * Pobiera ścieżkę do pakietu pne-certificate-generator
     * Sprawdza różne możliwe lokalizacje (w kolejności priorytetu)
     */
    protected function getPackagePath(): ?string
    {
        // Opcja 1: Przez Docker volume (w kontenerze) - najwyższy priorytet
        $dockerPath = '/var/www/pne-certificate-generator';
        if (File::exists($dockerPath) && File::exists($dockerPath . '/composer.json')) {
            return $dockerPath;
        }
        
        // Opcja 2: Relatywna ścieżka z pneadm-bootstrap (dla lokalnego developmentu)
        $relativePath = base_path('../pne-certificate-generator');
        if (File::exists($relativePath) && File::exists($relativePath . '/composer.json')) {
            return $relativePath;
        }
        
        // Opcja 3: Przez vendor (jeśli pakiet jest zainstalowany przez Composer)
        $vendorPath = base_path('vendor/pne/certificate-generator');
        if (File::exists($vendorPath) && File::exists($vendorPath . '/composer.json')) {
            return $vendorPath;
        }
        
        // Opcja 4: Absolutna ścieżka (fallback dla WSL)
        $absolutePath = '/home/hostnet/WEB-APP/pne-certificate-generator';
        if (File::exists($absolutePath) && File::exists($absolutePath . '/composer.json')) {
            return $absolutePath;
        }
        
        // Opcja 5: Przez realpath (rozwiązuje symlinki i względne ścieżki)
        $realPath = realpath(base_path('../pne-certificate-generator'));
        if ($realPath && File::exists($realPath) && File::exists($realPath . '/composer.json')) {
            return $realPath;
        }
        
        // Loguj błąd dla debugowania
        \Log::error('Cannot find pne-certificate-generator package', [
            'docker_path' => $dockerPath . ' (exists: ' . (File::exists($dockerPath) ? 'yes' : 'no') . ')',
            'relative_path' => $relativePath . ' (exists: ' . (File::exists($relativePath) ? 'yes' : 'no') . ')',
            'vendor_path' => $vendorPath . ' (exists: ' . (File::exists($vendorPath) ? 'yes' : 'no') . ')',
            'absolute_path' => $absolutePath . ' (exists: ' . (File::exists($absolutePath) ? 'yes' : 'no') . ')',
            'base_path' => base_path(),
        ]);
        
        return null;
    }

    /**
     * Sprawdza czy pakiet jest zapisywalny (path repository) czy tylko do odczytu (vendor)
     */
    protected function isPackageWritable(): bool
    {
        $packagePath = $this->getPackagePath();
        
        if (!$packagePath) {
            return false;
        }
        
        // Jeśli pakiet jest w vendor - nie jest zapisywalny
        if (strpos($packagePath, 'vendor/') !== false) {
            return false;
        }
        
        // Sprawdź czy można zapisać w katalogu resources/views pakietu
        $testPath = $packagePath . '/resources/views';
        if (!File::exists($testPath)) {
            return false;
        }
        
        // Sprawdź uprawnienia - próba utworzenia testowego pliku
        $testFile = $testPath . '/.writable_test_' . time();
        try {
            @File::put($testFile, 'test');
            if (File::exists($testFile)) {
                File::delete($testFile);
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        return false;
    }

    /**
     * Zapisuje plik Blade do aplikacji (produkcja)
     */
    protected function saveBladeToApp(string $bladeContent, string $fileName, string $slug): string
    {
        $appPath = resource_path('views/certificates/' . $fileName);
        $appDirectory = dirname($appPath);
        
        if (!File::exists($appDirectory)) {
            File::makeDirectory($appDirectory, 0755, true);
        }
        
        try {
            File::put($appPath, $bladeContent);
            \Log::info('Template saved to app (production)', [
                'slug' => $slug,
                'app_path' => $appPath
            ]);
            return $fileName;
        } catch (\Exception $e) {
            \Log::error('Failed to save template to app', [
                'slug' => $slug,
                'app_path' => $appPath,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Nie udało się zapisać szablonu w aplikacji: ' . $e->getMessage());
        }
    }

    /**
     * Buduje treść pliku blade na podstawie konfiguracji
     */
    private function buildBladeContent($config)
    {
        $blocks = $config['blocks'] ?? [];
        $settings = $config['settings'] ?? [];
        
        // Sortuj bloki według pola 'order' przed renderowaniem
        uasort($blocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang=\"pl\">\n";
        $html .= "<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "    <title>Zaświadczenie</title>\n";
        
        // Dodaj kod PHP dla tła przed style
        if (!empty($settings['background_image'] ?? null)) {
            $html .= "    @php\n";
            $html .= "        // Obsługa tła - konwersja do base64 dla PDF\n";
            $html .= "        \$backgroundImageCss = '';\n";
            $html .= "        \$showBackground = \$templateSettings['show_background'] ?? false;\n";
            $html .= "        if (\$showBackground && !empty(\$templateSettings['background_image'] ?? null)) {\n";
            $html .= "            \$backgroundPath = storage_path('app/public/' . \$templateSettings['background_image']);\n";
            $html .= "            if (file_exists(\$backgroundPath)) {\n";
            $html .= "                \$imageData = file_get_contents(\$backgroundPath);\n";
            $html .= "                \$imageBase64 = base64_encode(\$imageData);\n";
            $html .= "                \$imageMime = mime_content_type(\$backgroundPath);\n";
            $html .= "                \$backgroundImageCss = \"background-image: url('data:{\$imageMime};base64,{\$imageBase64}'); background-size: cover; background-position: center; background-repeat: no-repeat;\";\n";
            $html .= "            }\n";
            $html .= "        }\n";
            $html .= "    @endphp\n";
        }
        
        $html .= $this->buildStyles($settings);
        $html .= "</head>\n";
        $html .= "<body>\n";
        
        // Generowanie bloków - wyodrębnij stałe elementy (instructor_signature i footer)
        $regularBlocks = [];
        $instructorSignatureBlock = null;
        $footerBlock = null;
        
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'instructor_signature') {
                $instructorSignatureBlock = $block;
            } elseif ($type === 'footer') {
                $footerBlock = $block;
            } else {
                $regularBlocks[] = $block;
            }
        }
        
        // Sortuj regularBlocks według order (na wypadek, gdyby były dodane w różnej kolejności)
        usort($regularBlocks, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA <=> $orderB;
        });
        
        // Renderuj najpierw zwykłe bloki
        foreach ($regularBlocks as $block) {
            $html .= $this->buildBlock($block, $settings);
        }
        
        // Renderuj na końcu stałe elementy (zawsze na dole zaświadczenia)
        if ($instructorSignatureBlock) {
            $html .= $this->buildBlock($instructorSignatureBlock, $settings);
        }
        if ($footerBlock) {
            $html .= $this->buildBlock($footerBlock, $settings);
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
        $marginTop = $settings['margin_top'] ?? 10;
        $marginBottom = $settings['margin_bottom'] ?? 10;
        $marginLeft = $settings['margin_left'] ?? 50;
        $marginRight = $settings['margin_right'] ?? 50;
        $totalDateMarginLeft = $marginLeft;
        $totalInstructorMarginRight = $marginRight;
        
        $styles = "    <style>\n";
        
        // Ustawienia @page - muszą być przed body, aby usunąć domyślne marginesy DOMPDF
        $styles .= "        @page {\n";
        $styles .= "            margin: 0;\n";
        if ($orientation === 'landscape') {
            $styles .= "            size: A4 landscape;\n";
        }
        $styles .= "        }\n";
        
        $styles .= "        body {\n";
        $styles .= "            font-family: \"{$fontFamily}\", sans-serif;\n";
        $styles .= "            text-align: center;\n";
        $styles .= "            position: relative;\n";
        $styles .= "            margin: 0;\n";
        $styles .= "            padding: {$marginTop}px {$marginRight}px {$marginBottom}px {$marginLeft}px;\n";
        $styles .= "            height: 100%;\n";
        $styles .= "            line-height: 1;\n";
        if (!empty($settings['background_image'] ?? null)) {
            $styles .= "            {!! \$backgroundImageCss ?? '' !!}\n";
        }
        $styles .= "        }\n";
        $styles .= "        h1, h2, h3, p, ol, ul, li {\n";
        $styles .= "            margin: 0;\n";
        $styles .= "            padding: 0;\n";
        $styles .= "            line-height: 1;\n";
        $styles .= "        }\n";
        $styles .= "        .certificate-title {\n";
        $styles .= "            margin-top: 0;\n";
        $styles .= "            margin-bottom: 20px;\n";
        $styles .= "            padding-top: 0;\n";
        $styles .= "            padding-bottom: 0;\n";
        $styles .= "            line-height: 1;\n";
        $styles .= "            font-size: " . ($settings['title_size'] ?? '38') . "px;\n";
        $styles .= "            font-weight: bold;\n";
        $styles .= "            color: " . ($settings['title_color'] ?? '#000') . ";\n";
        $styles .= "        }\n";
        $styles .= "        body > p {\n";
        $styles .= "            margin-top: 15px;\n";
        $styles .= "            margin-bottom: 15px;\n";
        $styles .= "        }\n";
        $styles .= "        body > h2:not(.course-title) {\n";
        $styles .= "            margin-top: 15px;\n";
        $styles .= "            margin-bottom: 15px;\n";
        $styles .= "        }\n";
        $styles .= "        body > h3 {\n";
        $styles .= "            margin-top: 20px;\n";
        $styles .= "            margin-bottom: 15px;\n";
        $styles .= "        }\n";
        $styles .= "        .course-title {\n";
        $styles .= "            word-break: normal;\n";
        $styles .= "            white-space: normal;\n";
        $styles .= "            hyphens: none;\n";
        $styles .= "            font-size: " . ($settings['course_title_size'] ?? '32') . "px;\n";
        $styles .= "            font-weight: bold;\n";
        $styles .= "            line-height: 1.1;\n";
        $styles .= "            margin-top: 15px;\n";
        $styles .= "            margin-bottom: 20px;\n";
        $styles .= "            padding-left: 0;\n";
        $styles .= "            padding-right: 0;\n";
        $styles .= "        }\n";
        $styles .= "        .bold {\n";
        $styles .= "            font-weight: bold;\n";
        $styles .= "        }\n";
        // Pozycjonowanie sekcji - górna krawędź od dołu strony, na tej samej wysokości, powyżej stopki
        // A4 wymiary w pikselach (96 DPI): Portrait: 794x1123px, Landscape: 1123x794px
        $pageHeight = ($orientation === 'landscape') ? 794 : 1123;
        $dateTop = $pageHeight - $marginBottom - 180; // Górna krawędź 180px powyżej stopki
        // Szacunkowa wysokość stopki (logo ~80px + tekst ~60px + marginesy ~20px = ~160px)
        $footerHeight = 160;
        // Pozycja stopki - jeśli marginBottom = 0, użyj bottom: 0, w przeciwnym razie oblicz top
        if ($marginBottom == 0) {
            $footerCss = "            bottom: 0;\n";
        } else {
            $footerTop = $pageHeight - $marginBottom - $footerHeight;
            $footerCss = "            top: {$footerTop}px;\n";
        }
        $styles .= "        .date-section {\n";
        $styles .= "            position: absolute;\n";
        $styles .= "            top: {$dateTop}px;\n";
        $styles .= "            left: {$totalDateMarginLeft}px;\n";
        $styles .= "            right: 50%;\n";
        $styles .= "            text-align: left;\n";
        $styles .= "        }\n";
        $styles .= "        .instructor-section {\n";
        $styles .= "            position: absolute;\n";
        $styles .= "            top: {$dateTop}px;\n";
        $styles .= "            left: 50%;\n";
        $styles .= "            right: {$totalInstructorMarginRight}px;\n";
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
        $styles .= "            width: fit-content;\n";
        $styles .= "            margin-left: auto;\n";
        $styles .= "            margin-right: auto;\n";
        $styles .= "        }\n";
        $styles .= "        .instructor-section .signature-container {\n";
        $styles .= "            width: fit-content;\n";
        $styles .= "            max-width: 100%;\n";
        $styles .= "            display: flex;\n";
        $styles .= "            justify-content: center;\n";
        $styles .= "            align-items: center;\n";
        $styles .= "            align-self: center;\n";
        $styles .= "            margin: 0 auto;\n";
        $styles .= "        }\n";
        $participantNameFont = $settings['participant_name_font'] ?? 'DejaVu Sans';
        $participantNameItalic = !empty($settings['participant_name_italic']);
        $styles .= "        .participant-name {\n";
        $styles .= "            margin-top: 15px;\n";
        $styles .= "            margin-bottom: 20px;\n";
        $styles .= "            word-break: normal;\n";
        $styles .= "            white-space: normal;\n";
        $styles .= "            hyphens: none;\n";
        $styles .= "            padding-left: 0;\n";
        $styles .= "            padding-right: 0;\n";
        $styles .= "            font-size: " . ($settings['participant_name_size'] ?? '24') . "px;\n";
        $styles .= "            font-family: \"{$participantNameFont}\", sans-serif;\n";
        if ($participantNameItalic) {
            $styles .= "            font-style: italic;\n";
        }
        $styles .= "        }\n";
        $styles .= "        .instructor-section .signature-img {\n";
        $styles .= "            position: relative;\n";
        $styles .= "            z-index: 1;\n";
        $styles .= "            display: block;\n";
        $styles .= "            margin-left: auto;\n";
        $styles .= "            margin-right: auto;\n";
        $styles .= "            background: transparent;\n";
        $styles .= "            background-color: transparent;\n";
        $styles .= "        }\n";
        $footerWidth = "calc(100% - " . ($marginLeft + $marginRight) . "px)";
        $styles .= "        .footer {\n";
        $styles .= "            font-size: 10px;\n";
        $styles .= "            text-align: center;\n";
        $styles .= "            position: fixed;\n";
        $styles .= $footerCss;
        $styles .= "            left: {$marginLeft}px;\n";
        $styles .= "            right: {$marginRight}px;\n";
        $styles .= "            width: {$footerWidth};\n";
        $styles .= "        }\n";
        $styles .= "    </style>\n";
        
        return $styles;
    }

    /**
     * Buduje pojedynczy blok szablonu
     */
    private function buildBlock($block, $settings = [])
    {
        $type = $block['type'] ?? '';
        $config = $block['config'] ?? [];
        
        switch ($type) {
            case 'header':
                return $this->buildHeaderBlock($config);
            case 'participant_info':
                return $this->buildParticipantInfoBlock($config);
            case 'course_info':
                return $this->buildCourseInfoBlock($config, $settings);
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
        $html .= "    <h2 class=\"participant-name\">{{ \$participant->first_name }} {{ \$participant->last_name }}</h2>\n\n";
        
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
    private function buildCourseInfoBlock($config, $settings = [])
    {
        $marginLeft = $settings['margin_left'] ?? 50;
        $marginRight = $settings['margin_right'] ?? 50;
        $completionText = $config['completion_text'] ?? 'ukończył/a szkolenie';
        $eventText = $config['event_text'] ?? 'zorganizowanym w dniu';
        // Nie używamy htmlspecialchars, aby umożliwić HTML
        $html = "    <p>" . $completionText . "</p>\n";
        $html .= "    <p>" . $eventText . " {{ \\Carbon\\Carbon::parse(\$course->start_date)->format('d.m.Y') }}r. ";
        
        if (!empty($config['show_duration'])) {
            $html .= "w wymiarze {{ \$durationMinutes }} minut, ";
        }
        
        $html .= "przez</p>\n\n";
        $html .= "    <p class=\"bold\">" . ($config['organizer_name'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji') . "</p>\n\n";
        
        $subjectLabel = $config['subject_label'] ?? 'TEMAT SZKOLENIA';
        $html .= "    <h3>" . $subjectLabel . "</h3>\n";
        $html .= "    <h2 class=\"course-title\">{{ \$course->title }}</h2>\n\n";
        
        if (!empty($config['show_description'])) {
            // Sprawdź czy description nie jest puste przed wyświetleniem "Zakres:"
            $html .= "    @php\n";
            $html .= "        \$description = trim(\$course->description ?? '');\n";
            $html .= "        if (!empty(\$description)) {\n";
            $html .= "            // Dynamiczne dostosowanie rozmiaru czcionki na podstawie długości zakresu (liczby znaków)\n";
            $html .= "            \$charCount = mb_strlen(\$description);\n";
            $html .= "            // Ustaw rozmiar czcionki na podstawie liczby znaków\n";
            $html .= "            // Dla dłuższych tekstów (>500 znaków) mniejsza czcionka, dla krótszych większa\n";
            $html .= "            \$fontSize = \$charCount > 500 ? '13px' : '16px';\n";
            $html .= "            \$marginBottom = \$charCount > 500 ? '2px' : '5px';\n";
            $html .= "            if (preg_match('/^\\\\d+\\\\.\\\\s*/m', \$description)) {\n";
            $html .= "                // To jest lista numerowana - formatuj jako <ol> z dynamiczną czcionką\n";
            $html .= "                \$items = preg_split('/\\\\n(?=\\\\d+\\\\.)/', \$description);\n";
            $html .= "                echo '<ol style=\"text-align: left; padding-left: 25px; padding-right: 0; font-size: ' . \$fontSize . ';\">';\n";
            $html .= "                foreach (\$items as \$item) {\n";
            $html .= "                    \$cleanItem = preg_replace('/^\\\\d+\\\\.\\\\s*/', '', trim(\$item));\n";
            $html .= "                    if (\$cleanItem) {\n";
            $html .= "                        echo '<li style=\"text-align: left; margin-bottom: ' . \$marginBottom . ';\">' . htmlspecialchars(\$cleanItem) . '</li>';\n";
            $html .= "                    }\n";
            $html .= "                }\n";
            $html .= "                echo '</ol>';\n";
            $html .= "            } else {\n";
            $html .= "                // To jest zwykły tekst - jako akapit wyrównany do lewej z dynamiczną czcionką\n";
            $html .= "                echo '<p style=\"text-align: left; padding-left: 0; padding-right: 0; font-size: ' . \$fontSize . ';\">' . htmlspecialchars(\$description) . '</p>';\n";
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
        $html .= "        <p style=\"margin: 0;\">Data, {{ \\Carbon\\Carbon::parse(\$course->end_date)->format('d.m.Y') }}r.@if((\$templateSettings['show_certificate_number'] ?? true))<br>\n";
        $html .= "        Nr rejestru: {{ \$certificateNumber }}@endif</p>\n";
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
        $html .= "        <div class=\"signature-container\" style=\"margin-right: {{ rand(10, 100) }}px; margin-top: {{ rand(0, 25) }}px;\">\n";
        $html .= "            @if(!empty(\$instructor->signature))\n";
        $html .= "                @php\n";
        $html .= "                    // Obsługa ścieżki do grafiki podpisu\n";
        $html .= "                    if (\$isPdfMode ?? false) {\n";
        $html .= "                        // Dla PDF używamy base64 encoding z usunięciem białego tła\n";
        $html .= "                        \$signatureFile = storage_path('app/public/' . \$instructor->signature);\n";
        $html .= "                        if (file_exists(\$signatureFile)) {\n";
        $html .= "                            // Funkcja do usuwania białego tła\n";
        $html .= "                            \$imageInfo = getimagesize(\$signatureFile);\n";
        $html .= "                            if (\$imageInfo) {\n";
        $html .= "                                \$mimeType = \$imageInfo['mime'];\n";
        $html .= "                                \$width = \$imageInfo[0];\n";
        $html .= "                                \$height = \$imageInfo[1];\n";
        $html .= "                                \n";
        $html .= "                                // Wczytaj obraz w zależności od typu\n";
        $html .= "                                switch (\$mimeType) {\n";
        $html .= "                                    case 'image/png':\n";
        $html .= "                                        \$sourceImage = imagecreatefrompng(\$signatureFile);\n";
        $html .= "                                        break;\n";
        $html .= "                                    case 'image/jpeg':\n";
        $html .= "                                    case 'image/jpg':\n";
        $html .= "                                        \$sourceImage = imagecreatefromjpeg(\$signatureFile);\n";
        $html .= "                                        break;\n";
        $html .= "                                    case 'image/gif':\n";
        $html .= "                                        \$sourceImage = imagecreatefromgif(\$signatureFile);\n";
        $html .= "                                        break;\n";
        $html .= "                                    default:\n";
        $html .= "                                        \$sourceImage = null;\n";
        $html .= "                                }\n";
        $html .= "                                \n";
        $html .= "                                if (\$sourceImage) {\n";
        $html .= "                                    // Utwórz nowy obraz z przezroczystością\n";
        $html .= "                                    \$transparentImage = imagecreatetruecolor(\$width, \$height);\n";
        $html .= "                                    imagealphablending(\$transparentImage, false);\n";
        $html .= "                                    imagesavealpha(\$transparentImage, true);\n";
        $html .= "                                    \$transparent = imagecolorallocatealpha(\$transparentImage, 0, 0, 0, 127);\n";
        $html .= "                                    imagefill(\$transparentImage, 0, 0, \$transparent);\n";
        $html .= "                                \n";
        $html .= "                                    // Kopiuj piksele, zamieniając białe na przezroczyste\n";
        $html .= "                                    for (\$x = 0; \$x < \$width; \$x++) {\n";
        $html .= "                                        for (\$y = 0; \$y < \$height; \$y++) {\n";
        $html .= "                                            \$rgb = imagecolorat(\$sourceImage, \$x, \$y);\n";
        $html .= "                                            \$r = (\$rgb >> 16) & 0xFF;\n";
        $html .= "                                            \$g = (\$rgb >> 8) & 0xFF;\n";
        $html .= "                                            \$b = \$rgb & 0xFF;\n";
        $html .= "                                            \n";
        $html .= "                                            // Jeśli piksel jest biały lub bardzo jasny (threshold 240), ustaw jako przezroczysty\n";
        $html .= "                                            if (\$r >= 240 && \$g >= 240 && \$b >= 240) {\n";
        $html .= "                                                imagesetpixel(\$transparentImage, \$x, \$y, \$transparent);\n";
        $html .= "                                            } else {\n";
        $html .= "                                                \$color = imagecolorallocate(\$transparentImage, \$r, \$g, \$b);\n";
        $html .= "                                                imagesetpixel(\$transparentImage, \$x, \$y, \$color);\n";
        $html .= "                                            }\n";
        $html .= "                                        }\n";
        $html .= "                                    }\n";
        $html .= "                                \n";
        $html .= "                                    // Zapisz do bufora jako PNG\n";
        $html .= "                                    ob_start();\n";
        $html .= "                                    imagepng(\$transparentImage);\n";
        $html .= "                                    \$imageData = ob_get_contents();\n";
        $html .= "                                    ob_end_clean();\n";
        $html .= "                                \n";
        $html .= "                                    // Zwolnij pamięć\n";
        $html .= "                                    imagedestroy(\$sourceImage);\n";
        $html .= "                                    imagedestroy(\$transparentImage);\n";
        $html .= "                                \n";
        $html .= "                                    \$signatureSrc = 'data:image/png;base64,' . base64_encode(\$imageData);\n";
        $html .= "                                } else {\n";
        $html .= "                                    // Fallback - użyj oryginalnego obrazu\n";
        $html .= "                                    \$signatureSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents(\$signatureFile));\n";
        $html .= "                                }\n";
        $html .= "                            } else {\n";
        $html .= "                                \$signatureSrc = null;\n";
        $html .= "                            }\n";
        $html .= "                        } else {\n";
        $html .= "                            \$signatureSrc = null;\n";
        $html .= "                        }\n";
        $html .= "                    } else {\n";
        $html .= "                        // Dla HTML używamy asset()\n";
        $html .= "                        \$signatureSrc = asset('storage/' . \$instructor->signature);\n";
        $html .= "                    }\n";
        $html .= "                @endphp\n";
        $html .= "                @if(\$signatureSrc)\n";
        $html .= "                    <img src=\"{{ \$signatureSrc }}\" alt=\"Podpis\" class=\"signature-img\" style=\"max-width: 200px; max-height: 80px; width: auto; height: auto;\">\n";
        $html .= "                @endif\n";
        $html .= "            @endif\n";
        $html .= "        </div>\n";
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
                    'completion_text' => ['type' => 'textarea', 'label' => 'Tekst ukończenia (obsługuje HTML, np. "ukończył/a szkolenie z cyklu <h3>TIK w pracy NAUCZYCIELA</h3>")', 'default' => 'ukończył/a szkolenie'],
                    'event_text' => ['type' => 'text', 'label' => 'Tekst wydarzenia (np. "zorganizowanym w dniu", "zorganizowane w dniu", "które odbyło się")', 'default' => 'zorganizowanym w dniu'],
                    'subject_label' => ['type' => 'textarea', 'label' => 'Etykieta tematu (obsługuje HTML, np. "TEMAT SZKOLENIA", "TEMAT WEBINARU")', 'default' => 'TEMAT SZKOLENIA'],
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


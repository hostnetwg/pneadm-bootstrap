<?php

namespace App\Services\Certificate;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class TemplateRenderer
{
    /**
     * Renderuje szablon z JSON konfiguracji do HTML
     * (bez użycia plików Blade - bezpośrednie renderowanie)
     *
     * @param array $data Dane do przekazania do widoku
     * @return string HTML
     */
    public function render(array $data): string
    {
        $templateConfig = $data['template_config'] ?? [];
        $settings = $data['settings'] ?? [];
        $blocks = $data['sorted_blocks'] ?? [];
        $instructorSignatureBlock = $data['instructor_signature_block'] ?? null;
        $footerBlock = $data['footer_block'] ?? null;
        
        // Przygotuj dane dla renderowania
        $participant = $data['participant'] ?? null;
        $course = $data['course'] ?? null;
        $instructor = $data['instructor'] ?? null;
        $certificateNumber = $data['certificate_number'] ?? '';
        $durationMinutes = $data['duration_minutes'] ?? 0;
        $isPdfMode = $data['is_pdf_mode'] ?? true;
        
        // Buduj HTML
        $html = $this->buildHtml($blocks, $instructorSignatureBlock, $footerBlock, $settings, [
            'participant' => $participant,
            'course' => $course,
            'instructor' => $instructor,
            'certificateNumber' => $certificateNumber,
            'durationMinutes' => $durationMinutes,
            'isPdfMode' => $isPdfMode,
            'templateSettings' => $settings,
        ]);
        
        return $html;
    }

    /**
     * Buduje pełny HTML dokumentu
     */
    protected function buildHtml(array $blocks, ?array $instructorSignatureBlock, ?array $footerBlock, array $settings, array $data): string
    {
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang=\"pl\">\n";
        $html .= "<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "    <title>Zaświadczenie</title>\n";
        
        // Obsługa tła
        $backgroundImageCss = '';
        if (!empty($settings['background_image']) && !empty($settings['show_background'])) {
            $backgroundPath = storage_path('app/public/' . $settings['background_image']);
            if (file_exists($backgroundPath)) {
                $imageData = file_get_contents($backgroundPath);
                $imageBase64 = base64_encode($imageData);
                $imageMime = mime_content_type($backgroundPath);
                $backgroundImageCss = "background-image: url('data:{$imageMime};base64,{$imageBase64}'); background-size: cover; background-position: center; background-repeat: no-repeat;";
            }
        }
        
        $html .= $this->buildStyles($settings, $backgroundImageCss);
        $html .= "</head>\n";
        $html .= "<body>\n";
        
        // Renderuj regularne bloki
        foreach ($blocks as $block) {
            $html .= $this->buildBlock($block, $settings, $data);
        }
        
        // Renderuj na końcu stałe elementy
        if ($instructorSignatureBlock) {
            $html .= $this->buildBlock($instructorSignatureBlock, $settings, $data);
        }
        if ($footerBlock) {
            $html .= $this->buildBlock($footerBlock, $settings, $data);
        }
        
        $html .= "</body>\n";
        $html .= "</html>\n";
        
        return $html;
    }

    /**
     * Buduje sekcję stylów
     */
    protected function buildStyles(array $settings, string $backgroundImageCss): string
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
        
        // Ustawienia @page
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
        if ($backgroundImageCss) {
            $styles .= "            {$backgroundImageCss}\n";
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
        
        // Pozycjonowanie sekcji
        $pageHeight = ($orientation === 'landscape') ? 794 : 1123;
        $dateTop = $pageHeight - $marginBottom - 180;
        $footerCss = "            bottom: {$marginBottom}px;\n";
        
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
        $styles .= "            justify-content: flex-end;\n";
        $styles .= "            align-items: center;\n";
        $styles .= "            align-self: flex-end;\n";
        $styles .= "            margin: 0;\n";
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
    protected function buildBlock(array $block, array $settings, array $data): string
    {
        $type = $block['type'] ?? '';
        $config = $block['config'] ?? [];
        
        switch ($type) {
            case 'header':
                return $this->buildHeaderBlock($config);
            case 'participant_info':
                return $this->buildParticipantInfoBlock($config, $data);
            case 'course_info':
                return $this->buildCourseInfoBlock($config, $settings, $data);
            case 'instructor_signature':
                return $this->buildInstructorSignatureBlock($config, $data);
            case 'footer':
                return $this->buildFooterBlock($config, $settings, $data);
            case 'custom_text':
                return $this->buildCustomTextBlock($config);
            default:
                return '';
        }
    }

    /**
     * Blok nagłówka
     */
    protected function buildHeaderBlock(array $config): string
    {
        $title = $config['title'] ?? 'ZAŚWIADCZENIE';
        return "    <h1 class=\"certificate-title\">" . htmlspecialchars($title) . "</h1>\n";
    }

    /**
     * Blok informacji o uczestniku
     */
    protected function buildParticipantInfoBlock(array $config, array $data): string
    {
        $participant = $data['participant'];
        $html = "    <p>Pan/i</p>\n";
        
        if ($participant) {
            $firstName = htmlspecialchars($participant->first_name ?? '');
            $lastName = htmlspecialchars($participant->last_name ?? '');
            $html .= "    <h2 class=\"participant-name\">{$firstName} {$lastName}</h2>\n\n";
        }
        
        if (!empty($config['show_birth_info']) && $participant) {
            if (!empty($participant->birth_date) && !empty($participant->birth_place)) {
                $birthDate = Carbon::parse($participant->birth_date)->format('d.m.Y');
                $birthPlace = htmlspecialchars($participant->birth_place);
                $html .= "    <p>urodzony/a: {$birthDate}&nbsp;r. w miejscowości {$birthPlace}</p>\n\n";
            } else {
                $html .= "    <p>&nbsp;</p>\n\n";
            }
        }
        
        return $html;
    }

    /**
     * Blok informacji o kursie
     */
    protected function buildCourseInfoBlock(array $config, array $settings, array $data): string
    {
        $course = $data['course'];
        $durationMinutes = $data['durationMinutes'];
        $completionText = $config['completion_text'] ?? 'ukończył/a szkolenie';
        $eventText = $config['event_text'] ?? 'zorganizowanym w dniu';
        
        // Nie używamy htmlspecialchars dla completionText i eventText, aby umożliwić HTML
        $html = "    <p>{$completionText}</p>\n";
        
        if ($course) {
            $startDate = Carbon::parse($course->start_date)->format('d.m.Y');
            $html .= "    <p>{$eventText} {$startDate}&nbsp;r. ";
            
            if (!empty($config['show_duration'])) {
                $html .= "w wymiarze {$durationMinutes} minut, ";
            }
            
            $html .= "przez</p>\n\n";
        }
        
        $organizerName = $config['organizer_name'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji';
        $html .= "    <p class=\"bold\">{$organizerName}</p>\n\n";
        
        $subjectLabel = $config['subject_label'] ?? 'TEMAT SZKOLENIA';
        $html .= "    <h3>" . htmlspecialchars($subjectLabel) . "</h3>\n";
        
        if ($course) {
            // Nie escapujemy tytułu kursu - może zawierać HTML (np. <h3>TIK w pracy NAUCZYCIELA</h3>)
            $courseTitle = $course->title ?? '';
            $html .= "    <h2 class=\"course-title\">{$courseTitle}</h2>\n\n";
        }
        
        if (!empty($config['show_description']) && $course) {
            $description = trim($course->description ?? '');
            if (!empty($description)) {
                $charCount = mb_strlen($description);
                $fontSize = $charCount > 500 ? '13px' : '16px';
                $marginBottom = $charCount > 500 ? '2px' : '5px';
                
                if (preg_match('/^\d+\.\s*/m', $description)) {
                    // Lista numerowana
                    $items = preg_split('/\n(?=\d+\.)/', $description);
                    $html .= "    <ol style=\"text-align: left; padding-left: 25px; padding-right: 0; font-size: {$fontSize};\">\n";
                    foreach ($items as $item) {
                        $cleanItem = preg_replace('/^\d+\.\s*/', '', trim($item));
                        if ($cleanItem) {
                            $html .= "        <li style=\"text-align: left; margin-bottom: {$marginBottom};\">" . htmlspecialchars($cleanItem) . "</li>\n";
                        }
                    }
                    $html .= "    </ol>\n\n";
                } else {
                    // Zwykły tekst
                    $html .= "    <p style=\"text-align: left; padding-left: 0; padding-right: 0; font-size: {$fontSize};\">" . htmlspecialchars($description) . "</p>\n\n";
                }
            }
        }
        
        return $html;
    }

    /**
     * Blok podpisu instruktora
     */
    protected function buildInstructorSignatureBlock(array $config, array $data): string
    {
        $course = $data['course'];
        $instructor = $data['instructor'];
        $certificateNumber = $data['certificateNumber'];
        $templateSettings = $data['templateSettings'];
        $isPdfMode = $data['isPdfMode'];
        
        $html = "    <div class=\"date-section\">\n";
        
        if ($course) {
            $endDate = Carbon::parse($course->end_date)->format('d.m.Y');
            $html .= "        <p style=\"margin: 0;\">Data, {$endDate}&nbsp;r.";
            
            if (!empty($templateSettings['show_certificate_number'] ?? true)) {
                $html .= "<br>\n        Nr rejestru: " . htmlspecialchars($certificateNumber);
            }
            
            $html .= "</p>\n";
        }
        
        $html .= "    </div>\n\n";
        
        $html .= "    <div class=\"instructor-section\">\n";
        $html .= "        <p>\n";
        
        if ($instructor) {
            $title = match($instructor->gender ?? 'prefer_not_to_say') {
                'male' => 'prowadzący:',
                'female' => 'prowadząca:',
                'other' => 'trener:',
                default => 'prowadzący/a:'
            };
            
            $firstName = htmlspecialchars($instructor->first_name ?? '');
            $lastName = htmlspecialchars($instructor->last_name ?? '');
            $html .= "            {$title}<br>\n";
            $html .= "            <span class=\"bold\">{$firstName} {$lastName}</span>\n";
        }
        
        $html .= "        </p>\n";
        $html .= "        \n";
        $html .= "        <div class=\"signature-container\">\n";
        
        if ($instructor && !empty($instructor->signature)) {
            $signatureSrc = $this->getSignatureImageSrc($instructor->signature, $isPdfMode);
            if ($signatureSrc) {
                $html .= "            <img src=\"{$signatureSrc}\" alt=\"Podpis\" class=\"signature-img\" style=\"max-width: 200px; max-height: 80px; width: auto; height: auto;\">\n";
            }
        }
        
        $html .= "        </div>\n";
        $html .= "    </div>\n\n";
        
        return $html;
    }

    /**
     * Pobiera źródło obrazu podpisu (base64 dla PDF lub URL dla HTML)
     */
    protected function getSignatureImageSrc(string $signaturePath, bool $isPdfMode): ?string
    {
        $signatureFile = storage_path('app/public/' . $signaturePath);
        
        if (!file_exists($signatureFile)) {
            return null;
        }
        
        if ($isPdfMode) {
            // Dla PDF używamy base64 z usunięciem białego tła
            return $this->getSignatureBase64($signatureFile);
        } else {
            // Dla HTML używamy asset()
            return asset('storage/' . $signaturePath);
        }
    }

    /**
     * Konwertuje podpis do base64 z usunięciem białego tła
     */
    protected function getSignatureBase64(string $signatureFile): ?string
    {
        $imageInfo = getimagesize($signatureFile);
        if (!$imageInfo) {
            return null;
        }
        
        $mimeType = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Wczytaj obraz
        $sourceImage = null;
        switch ($mimeType) {
            case 'image/png':
                $sourceImage = imagecreatefrompng($signatureFile);
                break;
            case 'image/jpeg':
            case 'image/jpg':
                $sourceImage = imagecreatefromjpeg($signatureFile);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($signatureFile);
                break;
        }
        
        if (!$sourceImage) {
            // Fallback - użyj oryginalnego obrazu
            return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($signatureFile));
        }
        
        // Utwórz nowy obraz z przezroczystością
        $transparentImage = imagecreatetruecolor($width, $height);
        imagealphablending($transparentImage, false);
        imagesavealpha($transparentImage, true);
        $transparent = imagecolorallocatealpha($transparentImage, 0, 0, 0, 127);
        imagefill($transparentImage, 0, 0, $transparent);
        
        // Włącz obsługę alpha dla źródłowego obrazu (jeśli PNG)
        if ($mimeType === 'image/png') {
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
        }
        
        // Kopiuj piksele, zamieniając białe na przezroczyste
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($sourceImage, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $a = ($rgba >> 24) & 0x7F; // Alpha channel (0 = nieprzezroczysty, 127 = przezroczysty)
                
                // Dla PNG - zawsze sprawdź oryginalny alpha
                if ($mimeType === 'image/png') {
                    // Jeśli piksel jest już przezroczysty w oryginalnym obrazie (alpha > 0), zachowaj to
                    if ($a > 0) {
                        // Zachowaj oryginalny alpha (0 = nieprzezroczysty, 127 = przezroczysty)
                        $alpha = $a;
                        $color = imagecolorallocatealpha($transparentImage, $r, $g, $b, $alpha);
                        imagesetpixel($transparentImage, $x, $y, $color);
                    } else {
                        // Piksel nie jest przezroczysty - sprawdź czy jest biały i usuń tło
                        if ($r >= 240 && $g >= 240 && $b >= 240) {
                            imagesetpixel($transparentImage, $x, $y, $transparent);
                        } else {
                            // Nie jest biały - zachowaj z pełną nieprzezroczystością
                            $color = imagecolorallocatealpha($transparentImage, $r, $g, $b, 0);
                            imagesetpixel($transparentImage, $x, $y, $color);
                        }
                    }
                } else {
                    // Dla JPEG - usuń białe tło
                    // Jeśli piksel jest biały lub bardzo jasny (threshold 240), ustaw jako przezroczysty
                    if ($r >= 240 && $g >= 240 && $b >= 240) {
                        imagesetpixel($transparentImage, $x, $y, $transparent);
                    } else {
                        // Użyj imagecolorallocatealpha z pełną nieprzezroczystością (alpha = 0)
                        $color = imagecolorallocatealpha($transparentImage, $r, $g, $b, 0);
                        imagesetpixel($transparentImage, $x, $y, $color);
                    }
                }
            }
        }
        
        // Zapisz do bufora jako PNG
        ob_start();
        imagepng($transparentImage);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        // Zwolnij pamięć
        imagedestroy($sourceImage);
        imagedestroy($transparentImage);
        
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Blok stopki
     */
    protected function buildFooterBlock(array $config, array $settings, array $data): string
    {
        $footerText = $config['text'] ?? 'Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -';
        $isPdfMode = $data['isPdfMode'];
        
        $html = "    <div class=\"footer\">\n";
        
        // Logo w stopce (jeśli włączone)
        if (!empty($config['show_logo']) && !empty($config['logo_path'])) {
            $logoSize = $config['logo_size'] ?? 120;
            $logoPath = $config['logo_path'];
            $logoPosition = $config['logo_position'] ?? 'center';
            
            $html .= "        <div style=\"text-align: {$logoPosition}; margin-bottom: 15px;\">\n";
            
            $logoSrc = null;
            if ($isPdfMode) {
                $logoFile = storage_path('app/public/' . $logoPath);
                if (file_exists($logoFile)) {
                    $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
                }
            } else {
                $logoSrc = asset('storage/' . $logoPath);
            }
            
            if ($logoSrc) {
                $html .= "            <img src=\"{$logoSrc}\" alt=\"Logo\" style=\"max-width: {$logoSize}px; height: auto;\">\n";
            }
            
            $html .= "        </div>\n";
        }
        
        $html .= "        {$footerText}\n";
        $html .= "    </div>\n";
        
        return $html;
    }

    /**
     * Blok niestandardowego tekstu
     */
    protected function buildCustomTextBlock(array $config): string
    {
        $text = $config['text'] ?? '';
        $align = $config['align'] ?? 'center';
        
        return "    <p style=\"text-align: {$align};\">" . htmlspecialchars($text) . "</p>\n";
    }
}


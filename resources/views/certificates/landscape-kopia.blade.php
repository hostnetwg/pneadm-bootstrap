<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaświadczenie</title>
    @php
        // Upewnij się, że $templateSettings jest tablicą
        $templateSettings = $templateSettings ?? [];
        $marginTop = $templateSettings['margin_top'] ?? 10;
        $marginBottom = $templateSettings['margin_bottom'] ?? 10;
        $marginLeft = $templateSettings['margin_left'] ?? 50;
        $marginRight = $templateSettings['margin_right'] ?? 50;
        $dateMarginLeft = $templateSettings['date_margin_left'] ?? 0;
        $instructorMarginRight = $templateSettings['instructor_margin_right'] ?? 0;
        $totalDateMarginLeft = $marginLeft + $dateMarginLeft;
        $totalInstructorMarginRight = $marginRight + $instructorMarginRight;
        // A4 wymiary w pikselach (96 DPI): Portrait: 794x1123px, Landscape: 1123x794px
        $orientation = $templateSettings['orientation'] ?? 'landscape';
        $pageHeight = ($orientation === 'landscape') ? 794 : 1123; // Wysokość strony A4
        // Oblicz pozycję górnej krawędzi od dołu strony
        $dateTop = $pageHeight - $marginBottom - 180; // Górna krawędź 180px powyżej stopki
        // Szacunkowa wysokość stopki (logo ~80px + tekst ~60px + marginesy ~20px = ~160px)
        $footerHeight = 160;
        // Pozycja stopki - jeśli marginBottom = 0, użyj bottom: 0, w przeciwnym razie oblicz top
        if ($marginBottom == 0) {
            $footerCss = 'bottom: 0;';
        } else {
            $footerTop = $pageHeight - $marginBottom - $footerHeight;
            $footerCss = 'top: ' . $footerTop . 'px;';
        }
        
        // Obsługa tła - konwersja do base64 dla PDF
        $backgroundImageCss = '';
        $showBackground = $templateSettings['show_background'] ?? false;
        if ($showBackground && !empty($templateSettings['background_image'] ?? null)) {
            $backgroundPath = storage_path('app/public/' . $templateSettings['background_image']);
            if (file_exists($backgroundPath)) {
                $imageData = file_get_contents($backgroundPath);
                $imageBase64 = base64_encode($imageData);
                $imageMime = mime_content_type($backgroundPath);
                $backgroundImageCss = "background-image: url('data:{$imageMime};base64,{$imageBase64}'); background-size: cover; background-position: center; background-repeat: no-repeat;";
            }
        }
    @endphp
    <style>
        @page {
            margin: 0;
            size: A4 landscape;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            text-align: center;
            position: relative;
            margin: 0;
            padding: {{ $marginTop }}px {{ $marginRight }}px {{ $marginBottom }}px {{ $marginLeft }}px;
            height: 100%;
            line-height: 1;
            {!! $backgroundImageCss !!}
        }
        h1, h2, h3, p, ol, ul, li {
            margin: 0;
            padding: 0;
            line-height: 1;
        }
        .certificate-title {
            font-size: 36px;
            font-weight: bold;
            color: #000000;
            margin-top: 0;
            margin-bottom: 20px;
            padding-top: 0;
            padding-bottom: 0;
            line-height: 1;
 2px solid purple;
        }
        body > p {
            margin-top: 15px;
            margin-bottom: 15px;
 1px solid cyan;
        }
        body > h2:not(.course-title) {
            margin-top: 15px;
            margin-bottom: 15px;
 2px solid magenta;
        }
        body > h3 {
            margin-top: 20px;
            margin-bottom: 15px;
 2px solid yellow;
        }
        .course-title {
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .course-title {
            word-break: normal;
            white-space: normal;
            hyphens: none;
            font-size: 30px;
            font-weight: bold;
            line-height: 1.1;
            padding-left: 0;
            padding-right: 0;
 2px solid darkorange;
        }
        .bold {
            font-weight: bold;
 1px solid pink;
        }
        ol, ul {
 2px solid brown;
        }
        li {
 1px solid gray;
        }
        .date-section {
            position: absolute;
            top: {{ $dateTop }}px;
            left: {{ $totalDateMarginLeft }}px;
            right: 50%;
            text-align: left;
 2px solid red;
            z-index: 10;
        }
        .instructor-section {
            position: absolute;
            top: {{ $dateTop }}px;
            left: 50%;
            right: {{ $totalInstructorMarginRight }}px;
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
 2px solid blue;
            z-index: 10;
        }
        .instructor-section p {
            margin: 0;
            position: relative;
            z-index: 10;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }
        .instructor-section .signature-container {
            width: fit-content;
            max-width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            align-self: center;
            margin: 0 auto;
        }
        .participant-name {
            margin-top: 15px;
            margin-bottom: 20px;
            word-break: normal;
            white-space: normal;
            hyphens: none;
            padding-left: 0;
            padding-right: 0;
 2px solid blue;
        }
        .instructor-section .signature-img {
            position: relative;
            z-index: 1;
            display: block;
            margin-left: auto;
            margin-right: auto;
            background: transparent;
            background-color: transparent;
        }
        .footer {
            font-size: 10px;
            text-align: center;
            position: fixed;
            {!! $footerCss !!}
            left: {{ $marginLeft }}px;
            right: {{ $marginRight }}px;
            width: calc(100% - {{ $marginLeft + $marginRight }}px);
 2px solid green;
            z-index: 1;
        }
        .footer img {
 2px solid orange;
        }
    </style>
</head>
<body>
    <h1 class="certificate-title" style="margin-top: 0 !important; padding-top: 0 !important; line-height: 1 !important;">ZAŚWIADCZENIE O UKOŃCZENIU SZKOLENIA</h1>
    <p>Pan/i</p>
    <h2 class="participant-name">{{ $participant->first_name }} {{ $participant->last_name }}</h2>

    @if (!empty($participant->birth_date) && !empty($participant->birth_place))
        <p>urodzony/a: {{ \Carbon\Carbon::parse($participant->birth_date)->format('d.m.Y') }}r. w miejscowości {{ $participant->birth_place }}</p>
    @else
        <p>&nbsp;</p>
    @endif

    <p>ukończył/a szkolenie</p>
    <p>zorganizowane w dniu {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}r. w wymiarze {{ $durationMinutes }} minut, przez</p>

    <p class="bold">Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>"Platforma Nowoczesnej Edukacji"</p>

    <h3>TEMAT SZKOLENIA</h3>
    <h2 class="course-title">{!! $course->title !!}</h2>

    @php
        $description = trim($course->description ?? '');
        if (!empty($description)) {
            // Dynamiczne dostosowanie rozmiaru czcionki na podstawie długości zakresu (liczby znaków)
            $charCount = mb_strlen($description);
            // Ustaw rozmiar czcionki na podstawie liczby znaków
            // Dla dłuższych tekstów (>500 znaków) mniejsza czcionka, dla krótszych większa
            $fontSize = $charCount > 500 ? '13px' : '16px';
            $marginBottom = $charCount > 500 ? '2px' : '5px';
            if (preg_match('/^\\d+\\.\\s*/m', $description)) {
                // To jest lista numerowana - formatuj jako <ol> z dynamiczną czcionką
                $items = preg_split('/\\n(?=\\d+\\.)/', $description);
                echo '<ol style="text-align: left; padding-left: 25px; padding-right: 0; font-size: ' . $fontSize . ';">';
                foreach ($items as $item) {
                    $cleanItem = preg_replace('/^\\d+\\.\\s*/', '', trim($item));
                    if ($cleanItem) {
                        echo '<li style="text-align: left; margin-bottom: ' . $marginBottom . ';">' . htmlspecialchars($cleanItem) . '</li>';
                    }
                }
                echo '</ol>';
            } else {
                // To jest zwykły tekst - jako akapit wyrównany do lewej z dynamiczną czcionką
                echo '<p style="text-align: left; padding-left: 0; padding-right: 0; font-size: ' . $fontSize . ';">' . htmlspecialchars($description) . '</p>';
            }
        }
    @endphp

    <div class="date-section">
        <p style="margin: 0;">Data, {{ \Carbon\Carbon::parse($course->end_date)->format('d.m.Y') }}r.@if(($templateSettings['show_certificate_number'] ?? true))<br>
        Nr rejestru: {{ $certificateNumber }}@endif</p>
    </div>

    <div class="instructor-section">
        <p>
            @php
                // Określanie tytułu na podstawie płci
                $title = match($instructor->gender ?? 'prefer_not_to_say') {
                    'male' => 'prowadzący:',
                    'female' => 'prowadząca:',
                    'other' => 'trener:',
                    default => 'prowadzący/a:'
                };
            @endphp
            {{ $title }}<br>
            <span class="bold">{{ $instructor->first_name }} {{ $instructor->last_name }}</span>
        </p>
        
        <div class="signature-container" style="margin-right: {{ rand(10, 100) }}px; margin-top: {{ rand(0, 25) }}px;">
            @if(!empty($instructor->signature))
                @php
                    // Obsługa ścieżki do grafiki podpisu
                    if ($isPdfMode ?? false) {
                        // Dla PDF używamy base64 encoding z usunięciem białego tła
                        $signatureFile = storage_path('app/public/' . $instructor->signature);
                        if (file_exists($signatureFile)) {
                            // Funkcja do usuwania białego tła
                            $imageInfo = getimagesize($signatureFile);
                            if ($imageInfo) {
                                $mimeType = $imageInfo['mime'];
                                $width = $imageInfo[0];
                                $height = $imageInfo[1];
                                
                                // Wczytaj obraz w zależności od typu
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
                                    default:
                                        $sourceImage = null;
                                }
                                
                                if ($sourceImage) {
                                    // Utwórz nowy obraz z przezroczystością
                                    $transparentImage = imagecreatetruecolor($width, $height);
                                    imagealphablending($transparentImage, false);
                                    imagesavealpha($transparentImage, true);
                                    $transparent = imagecolorallocatealpha($transparentImage, 0, 0, 0, 127);
                                    imagefill($transparentImage, 0, 0, $transparent);
                                
                                    // Kopiuj piksele, zamieniając białe na przezroczyste
                                    for ($x = 0; $x < $width; $x++) {
                                        for ($y = 0; $y < $height; $y++) {
                                            $rgb = imagecolorat($sourceImage, $x, $y);
                                            $r = ($rgb >> 16) & 0xFF;
                                            $g = ($rgb >> 8) & 0xFF;
                                            $b = $rgb & 0xFF;
                                            
                                            // Jeśli piksel jest biały lub bardzo jasny (threshold 240), ustaw jako przezroczysty
                                            if ($r >= 240 && $g >= 240 && $b >= 240) {
                                                imagesetpixel($transparentImage, $x, $y, $transparent);
                                            } else {
                                                $color = imagecolorallocate($transparentImage, $r, $g, $b);
                                                imagesetpixel($transparentImage, $x, $y, $color);
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
                                
                                    $signatureSrc = 'data:image/png;base64,' . base64_encode($imageData);
                                } else {
                                    // Fallback - użyj oryginalnego obrazu
                                    $signatureSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($signatureFile));
                                }
                            } else {
                                $signatureSrc = null;
                            }
                        } else {
                            $signatureSrc = null;
                        }
                    } else {
                        // Dla HTML używamy asset()
                        $signatureSrc = asset('storage/' . $instructor->signature);
                    }
                @endphp
                @if($signatureSrc)
                    <img src="{{ $signatureSrc }}" alt="Podpis" class="signature-img" style="max-width: 200px; max-height: 80px; width: auto; height: auto;">
                @endif
            @endif
        </div>
    </div>

    <div class="footer">
        Niepubliczny Ośrodek Doskonalenia Nauczycieli "Platforma Nowoczesnej Edukacji"<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -
    </div>
</body>
</html>

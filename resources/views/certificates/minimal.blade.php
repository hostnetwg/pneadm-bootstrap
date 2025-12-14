<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaświadczenie</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            text-align: center;
            position: relative;
            margin: 0;
            padding: 10px 50px 10px 50px;
            height: 100%;
            line-height: 1;
        }
        h1, h2, h3, p, ol, ul, li {
            margin: 0;
            padding: 0;
            line-height: 1;
        }
        .certificate-title {
            margin-top: 0;
            margin-bottom: 20px;
            padding-top: 0;
            padding-bottom: 0;
            line-height: 1;
            font-size: 42px;
            font-weight: bold;
            color: #2c3e50;
        }
        body > p {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        body > h2:not(.course-title) {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        body > h3 {
            margin-top: 20px;
            margin-bottom: 15px;
        }
        .course-title {
            word-break: normal;
            white-space: normal;
            hyphens: none;
            font-size: 28px;
            font-weight: bold;
            line-height: 1.1;
            margin-top: 15px;
            margin-bottom: 20px;
            padding-left: 0;
            padding-right: 0;
        }
        .bold {
            font-weight: bold;
        }
        .date-section {
            position: absolute;
            top: 933px;
            left: 50px;
            right: 50%;
            text-align: left;
        }
        .instructor-section {
            position: absolute;
            top: 933px;
            left: 50%;
            right: 50px;
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
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
            font-size: 24px;
            font-family: "DejaVu Sans", sans-serif;
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
            bottom: 10px;
            left: 50px;
            right: 50px;
            width: calc(100% - 100px);
        }
    </style>
</head>
<body>
    <h1 class="certificate-title">CERTYFIKAT UKOŃCZENIA</h1>
    <p>Pan/i</p>
    <h2 class="participant-name">{{ $participant->first_name }} {{ $participant->last_name }}</h2>

    <p>ukończył/a szkolenie</p>
    <p>zorganizowanym w dniu {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}r. przez</p>

    <p class="bold">Platforma Nowoczesnej Edukacji</p>

    <h3>TEMAT SZKOLENIA</h3>
    <h2 class="course-title">{{ $course->title }}</h2>

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
        Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -
    </div>
</body>
</html>

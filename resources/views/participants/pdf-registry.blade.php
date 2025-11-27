<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rejestr zaświadczeń</title>
    <style>
        @page {
            margin-top: 30px; /* Zmniejszony margines na nagłówek */
            margin-bottom: 50px; /* Zwiększony margines na stopkę */
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
        }
        .text-center {
            text-align: center;
        }
        .uppercase {
            text-transform: uppercase;
        }
        .header {
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 14pt;
            margin: 5px 0;
            font-weight: normal;
        }
        .header h2 {
            font-size: 16pt;
            margin: 5px 0;
            font-weight: bold;
        }
        .main-title {
            font-size: 18pt;
            font-weight: bold;
            margin: 20px 0;
        }
        .gray-box {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 9pt;
            text-align: left;
        }
        th {
            background-color: #fff;
            font-weight: bold;
            text-align: center;
        }
        .col-lp { width: 30px; text-align: center; }
        .col-id { width: 50px; text-align: center; }
        .col-date { width: 80px; text-align: center; }
        @page {
            margin-top: 60px; /* Miejsce na nagłówek tabeli na kolejnych stronach */
            margin-bottom: 40px;
            margin-left: 30px;
            margin-right: 30px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
        }
        /* ... styles ... */
    </style>
</head>
<body>
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 9;
            $text = '{!! str_replace("'", "\'", str_replace('&nbsp;', ' ', $course->title)) !!} | Data: {{ $course->start_date ? $course->start_date->format('d.m.Y') : '-' }}';
            $color = array(0, 0, 0);
            
            $pages = $pdf->get_page_count();
            $w = $pdf->get_width();
            
            // Marginesy zdefiniowane w @page (w pt/px - dompdf przelicza, ale zakładamy spójność)
            // 30px ~ 22.5pt, ale lepiej użyć bezpiecznych wartości
            $marginLeft = 30;
            $marginRight = 30;
            $marginTop = 60;
            
            // Pozycja "wiersza" nagłówkowego
            $rectX = $marginLeft;
            $rectY = 35; // Trochę powyżej margin-top (gdzie zaczyna się tabela)
            $rectH = 20;
            $rectW = $w - $marginLeft - $marginRight;
            
            // Wyświetl nagłówek "tabelowy" tylko na stronach od 2 wzwyż
            for ($i = 2; $i <= $pages; $i++) {
                $pdf->set_page($i);
                
                // Rysuj prostokąt (obramowanie wiersza)
                $pdf->rectangle($rectX, $rectY, $rectW, $rectH, array(0, 0, 0), 0.5);
                
                // Wyśrodkuj tekst w pionie i poziomie wewnątrz prostokąta?
                // Użytkownik chce "wpisać tytuł i datę". Może być od lewej.
                
                // Tekst z paddingiem
                $textX = $rectX + 5;
                $textY = $rectY + 6; // Przesunięcie w dół dla baseline
                
                $pdf->text($textX, $textY, $text, $font, $size, $color);
            }
            
            // Numeracja stron na dole (dla wszystkich stron czy tylko >1? Zostawmy dla wszystkich jak standard)
            // Użytkownik prosił o usunięcie numerowania wcześniej ("nie działa numerowanie stron, więc usuń to numerowanie")
            // Więc nie przywracam numeracji.
        }
    </script>

    <div class="header text-center">
        <h1>Niepubliczny Ośrodek Doskonalenia Nauczycieli</h1>
        <h1>"Platforma Nowoczesnej Edukacji"</h1>
    </div>

    <div class="text-center main-title uppercase">
        REJESTR ZAŚWIADCZEŃ
    </div>

    <div class="gray-box">
        <div class="info-row">
            <span class="info-label">Tytuł szkolenia:</span> {{ str_replace('&nbsp;', ' ', $course->title) }}
        </div>
        <div class="info-row">
            <span class="info-label">Zakres:</span> {!! strip_tags($course->description) !!}
        </div>
        <div class="info-row">
            @php
                $instructorName = 'Anna Wojkowska'; // Default from prompt
                $instructorLabel = 'Prowadząca';
                
                if ($course->instructor) {
                    $instructorName = $course->instructor->first_name . ' ' . $course->instructor->last_name;
                    $instructorLabel = ($course->instructor->gender === 'male') ? 'Prowadzący' : 'Prowadząca';
                }
            @endphp
            <span class="info-label">{{ $instructorLabel }}:</span> {{ $instructorName }}
        </div>
        <div class="info-row" style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 5px;">
            <table style="width: 100%; border: none; margin: 0;">
                <tr>
                    <td style="border: none; padding: 0;">
                        <span class="info-label">Data szkolenia:</span> 
                        @if($course->start_date)
                            {{ $course->start_date->format('d.m.Y') }}
                        @else
                            -
                        @endif
                    </td>
                    <td style="border: none; padding: 0;">
                        <span class="info-label">Czas trwania:</span> 
                        @if($course->start_date && $course->end_date)
                            {{ $course->start_date->diffInHours($course->end_date) }}h
                        @else
                            -
                        @endif
                    </td>
                    <td style="border: none; padding: 0;">
                        <span class="info-label">Rodzaj:</span> 
                        @if($course->type == 'online')
                            Online
                        @elseif($course->type == 'offline')
                            Stacjonarnie
                        @else
                            {{ $course->type ?? '-' }}
                        @endif
                    </td>
                    <td style="border: none; padding: 0;">
                        <span class="info-label">Kategoria:</span> 
                        @if($course->category == 'open')
                            Otwarte
                        @elseif($course->category == 'closed')
                            Zamknięte
                        @else
                            {{ $course->category ?? '-' }}
                        @endif
                    </td>
                    <td style="border: none; padding: 0;">
                        <span class="info-label">Liczba zaświadczeń:</span> {{ $participants->count() }}
                    </td>
                    <td style="border: none; padding: 0;">
                        <span class="info-label">Data wydania:</span> 
                        @if($course->end_date)
                            {{ $course->end_date->format('d.m.Y') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-lp">L.p.</th>
                <th class="col-id">ID</th>
                <th>Nazwisko</th>
                <th>IMIĘ</th>
                <th class="col-date">DATA URODZENIA</th>
                <th>MIEJSCE URODZENIA</th>
                <th>NR ZAŚWIADCZENIA</th>
                <th>DATA I POTWIERDZENIE ODBIORU</th>
            </tr>
        </thead>
        <tbody>
            @foreach($participants as $index => $participant)
            @php
                // Formatowanie imienia i nazwiska oraz miejsca urodzenia
                $formatName = function($name) {
                    // Jeśli całość wielkimi literami, zamień na title case
                    if (mb_strtoupper($name, 'UTF-8') === $name) {
                        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
                    }
                    
                    // Obsługa nazwisk dwuczłonowych (myślnik)
                    if (strpos($name, '-') !== false) {
                        $parts = explode('-', $name);
                        $formattedParts = array_map(function($part) {
                            return mb_convert_case(trim($part), MB_CASE_TITLE, 'UTF-8');
                        }, $parts);
                        return implode('-', $formattedParts);
                    }

                    // Zawsze formatuj jako Title Case (dla imion i zwykłych nazwisk)
                    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
                };

                $lastName = $formatName($participant->last_name);
                $birthPlace = $formatName($participant->birth_place);
                // Imię - user wanted uppercase in previous request ("IMIĘ"), but now implies consistent formatting. 
                // Wait, request says: "Nazwisko | IMIĘ". The prompt says: "Jeżeli imiona, nazwiska, lub miejscowość napisane są kapitalikami to zamień je na zaczynające się od dużej litera a potem małe".
                // But the column header is "IMIĘ" (uppercase) in the user query description?
                // Looking at the user query text:
                // "Poniżej szarego prostokąta tabelka z kolumnami: L.p. | ID | Nazwisko | IMIĘ | DATA URODZENIA | MIEJSCE URODZENIA"
                // And then current instruction: "Jeżeli imiona, nazwiska, lub miejscowość napisane są kapitalikami to zamień je na zaczynające się od dużej litera a potem małe"
                // This implies the content should be formatted, even if the header is uppercase.
                // However, in the previous code, I explicitly made first_name uppercase: <td class="uppercase">{{ $participant->first_name }}</td>
                // The user is asking to fix capitalization if it's ALL CAPS in DB.
                
                // Let's format all of them.
                $firstName = $formatName($participant->first_name);

            @endphp
            <tr>
                <td class="col-lp">{{ $index + 1 }}</td>
                <td class="col-id">
                    {{ $participant->id }}
                </td>
                <td>{{ $lastName }}</td>
                <td>{{ $firstName }}</td> 
                <td class="col-date">{{ $participant->birth_date ? $participant->birth_date->format('d.m.Y') : '-' }}</td>
                <td>{{ $birthPlace }}</td>
                <td>
                    {{ $participant->certificate ? $participant->certificate->certificate_number : '-' }}
                </td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>


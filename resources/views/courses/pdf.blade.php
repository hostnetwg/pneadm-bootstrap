<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista szkoleń</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            margin: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: bold;
        }
        
        .header .subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .filters-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            font-size: 11px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 10px;
        }
        
        td {
            font-size: 10px;
        }
        
        .course-id {
            text-align: center;
            width: 40px;
        }
        
        .course-date {
            width: 80px;
            text-align: center;
        }
        
        .course-title {
            font-weight: bold;
            width: 150px;
        }
        
        .course-type {
            width: 80px;
        }
        
        .course-location {
            width: 120px;
        }
        
        .course-instructor {
            width: 100px;
        }
        
        .course-participants {
            text-align: center;
            width: 50px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            border-radius: 3px;
            margin: 1px;
        }
        
        .badge-paid {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-free {
            background-color: #28a745;
            color: #fff;
        }
        
        .badge-online {
            background-color: #17a2b8;
            color: #fff;
        }
        
        .badge-offline {
            background-color: #6c757d;
            color: #fff;
        }
        
        .badge-open {
            background-color: #28a745;
            color: #fff;
        }
        
        .badge-closed {
            background-color: #dc3545;
            color: #fff;
        }
        
        .badge-active {
            background-color: #28a745;
            color: #fff;
        }
        
        .badge-inactive {
            background-color: #dc3545;
            color: #fff;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @page {
            margin: 20mm;
        }
        
        .page-number {
            position: fixed;
            bottom: 15mm;
            right: 15mm;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lista szkoleń</h1>
        <div class="subtitle">Wygenerowano: {{ now()->format('d.m.Y H:i') }}</div>
    </div>
    
    @if($appliedFilters)
        <div class="filters-info">
            <strong>Zastosowane filtry:</strong><br>
            @foreach($appliedFilters as $filter => $value)
                @if($value)
                    {{ ucfirst(str_replace('_', ' ', $filter)) }}: {{ $value }}<br>
                @endif
            @endforeach
        </div>
    @endif
    
    <table>
        <thead>
            <tr>
                <th class="course-id">ID</th>
                <th class="course-date">Data</th>
                <th class="course-title">Tytuł</th>
                <th class="course-type">Rodzaj</th>
                <th class="course-location">Lokalizacja / Dostęp</th>
                <th class="course-instructor">Instruktor</th>
                <th class="course-participants">Uczestnicy</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courses as $course)
                <tr>
                    <td class="course-id">{{ $course->id }}</td>
                    <td class="course-date">{{ $course->start_date ? date('d.m.Y H:i', strtotime($course->start_date)) : 'Brak daty' }}</td>
                    <td class="course-title">{{ $course->title }}</td>
                    <td class="course-type">
                        <span class="badge {{ $course->is_paid ? 'badge-paid' : 'badge-free' }}">
                            {{ $course->is_paid ? 'Płatny' : 'Bezpłatny' }}
                        </span><br>
                        <span class="badge {{ $course->type === 'online' ? 'badge-online' : 'badge-offline' }}">
                            {{ ucfirst($course->type) }}
                        </span><br>
                        <span class="badge {{ $course->category === 'open' ? 'badge-open' : 'badge-closed' }}">
                            {{ $course->category === 'open' ? 'Otwarte' : 'Zamknięte' }}
                        </span>
                    </td>
                    <td class="course-location">
                        @if ($course->type === 'offline' && $course->location)
                            <strong>{{ $course->location->location_name ?? 'Brak nazwy lokalizacji' }}</strong><br>
                            {{ $course->location->address ?? 'Brak adresu' }}<br>
                            {{ $course->location->postal_code ?? '' }} {{ $course->location->post_office ?? '' }}
                        @elseif ($course->type === 'online' && $course->onlineDetails)
                            @if (strtolower($course->onlineDetails->platform ?? '') === 'youtube')
                                {{ $course->onlineDetails->meeting_link ?? 'Brak linku' }}
                            @else
                                <strong>Platforma:</strong> {{ $course->onlineDetails->platform ?? 'Nieznana' }}<br>
                                <strong>Link:</strong> {{ $course->onlineDetails->meeting_link ?? 'Brak linku' }}
                            @endif
                        @else
                            Brak danych
                        @endif
                    </td>
                    <td class="course-instructor">
                        {{ $course->instructor ? $course->instructor->first_name . ' ' . $course->instructor->last_name : 'Brak instruktora' }}
                    </td>
                    <td class="course-participants">{{ $course->participants->count() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        <strong>Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji</strong><br>
        ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>
        - AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -
    </div>
    
    <div class="page-number">
        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $size = 10;
                $pageText = "Strona " . $PAGE_NUM . " z " . $PAGE_COUNT;
                $y = $pdf->get_height() - 20;
                $x = $pdf->get_width() - 50 - $fontMetrics->get_text_width($pageText, $font, $size);
                $pdf->text($x, $y, $pageText, $font, $size);
            }
        </script>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zamówienie {{ $order->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.5;
            margin: 0;
            padding: 40px;
            padding-bottom: 120px;
            position: relative;
            min-height: 100vh;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            padding: 4px 10px 10px 10px;
            vertical-align: top;
            width: 50%;
        }
        .header-left {
            text-align: left;
        }
        .header-right {
            text-align: right;
        }
        .header-section-label {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 6px 0;
            line-height: 1.2;
        }
        .header-org {
            margin-top: 18px;
        }
        .header-org h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 2px 0;
            line-height: 1.2;
        }
        .header-org p {
            font-size: 10px;
            margin: 0 0 1px 0;
            line-height: 1.15;
        }
        .header-table h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
            line-height: 1.25;
        }
        .header-table .header-left > p {
            font-size: 10px;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .divider {
            border-top: 1px solid #000;
            margin: 20px 0;
        }
        .order-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0 5px 0;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.order-table {
            border: 1px solid #000;
            margin-bottom: 0;
        }
        table.order-table th,
        table.order-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        table.order-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        table.order-table td:nth-child(1) {
            width: 7%;
            text-align: center;
        }
        table.order-table td:nth-child(2) {
            width: 68%;
        }
        table.order-table td:nth-child(3) {
            width: 8%;
            text-align: center;
        }
        table.order-table td:nth-child(4) {
            width: 17%;
            text-align: center;
        }
        table.invoice-table {
            margin-top: 10px;
            margin-bottom: 0;
            border: 1px solid #000;
        }
        table.invoice-table th,
        table.invoice-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }
        table.invoice-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            width: 50%;
        }
        table.invoice-table td {
            text-align: center;
        }
        table.access-table {
            margin-top: 10px;
            margin-bottom: 0;
            border: 1px solid #000;
        }
        table.access-table th,
        table.access-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        table.access-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0 5px 0;
        }
        .vat-info {
            font-style: italic;
            font-size: 10px;
            margin-top: -5px;
            margin-bottom: 5px;
        }
        .info-text {
            font-size: 10px;
            margin: 2px 0;
            line-height: 1.2;
        }
        .info-text-compact {
            font-size: 10px;
            margin: 0;
            line-height: 1.2;
        }
        .info-text strong {
            font-weight: bold;
        }
        a {
            color: #0066cc;
            text-decoration: underline;
        }
        a:visited {
            color: #0066cc;
        }
        .footer {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
            margin-top: 0;
            padding-top: 10px;
            border-top: 1px solid #000;
            font-size: 9px;
            color: #333;
            text-align: left;
            line-height: 1.4;
        }
        .footer-info {
            margin: 0;
            padding: 0;
        }
        .footer-text {
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .signature-section {
            margin-top: 80px;
            text-align: right;
            font-size: 10px;
            padding-bottom: 5px;
        }
        .signature-dots {
            margin-bottom: 5px;
            letter-spacing: 2px;
        }
        .signature-text {
            padding-right: 20px;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
    <!-- Nagłówek - tabela z 2 kolumnami -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="header-section-label">Zamawiający</div>
                <h1>{{ $order->orderer_name }}</h1>
                <p>tel. {{ $order->orderer_phone }}</p>
                <p>e-mail: {{ $order->orderer_email }}</p>
            </td>
            <td class="header-right">
                <div class="header-org">
                    <h1>Platforma Nowoczesnej Edukacji</h1>
                    <p>ul. A. Zamoyskiego 30/14, 09-320 Bieżuń</p>
                    <p>RSPO: 481379 NIP: 7392137630</p>
                    <p>NR KONTA: {{ substr('25114020040000300282222577', 0, 2) }} {{ substr('25114020040000300282222577', 2, 4) }} {{ substr('25114020040000300282222577', 6, 4) }} {{ substr('25114020040000300282222577', 10, 4) }} {{ substr('25114020040000300282222577', 14, 4) }} {{ substr('25114020040000300282222577', 18, 4) }} {{ substr('25114020040000300282222577', 22, 4) }}</p>
                    <p>e-mail: {{ $contactEmail }}</p>
                    <p>tel. +48501654274</p>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tytuł ZAMÓWIENIE -->
    <div class="order-title">ZAMÓWIENIE nr {{ $order->id }}</div>

    <!-- Tabela z zamówieniem -->
    @php
        $orderQty = $order->relationLoaded('participants')
            ? max(1, $order->participants->count())
            : max(1, (int) $order->participants()->count());
    @endphp
    <table class="order-table">
        <thead>
            <tr>
                <th>L.p.</th>
                <th>Nazwa produktu</th>
                <th>szt.</th>
                <th>Cena brutto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    SZKOLENIE: {{ str_replace('&nbsp;', ' ', strip_tags($order->product_name)) }}
                </td>
                <td>{{ $orderQty }}</td>
                <td>{{ number_format($order->product_price, 0, ',', ' ') }} PLN</td>
            </tr>
        </tbody>
    </table>
    <!-- Informacja o zwolnieniu VAT -->
    <div class="vat-info">
        * Platforma Nowoczesnej Edukacji Waldemar Grabowski - zwolnienie VAT, Art. 43 ust. 1 pkt 29 lit. b)
    </div>

    <!-- Dane na fakturze -->
    <div class="section-title">Dane na fakturze</div>
    
    <table class="invoice-table">
        <thead>
            <tr>
                <th>NABYWCA</th>
                <th>ODBIORCA</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $order->buyer_name }}<br>
                    {{ $order->buyer_postal_code }}, {{ $order->buyer_city }}, {{ $order->buyer_address }}<br>
                    NIP: {{ $order->buyer_nip }}
                </td>
                <td>
                    @if($order->recipient_name)
                        {{ $order->recipient_name }}<br>
                        @if($order->recipient_postal_code && $order->recipient_city && $order->recipient_address)
                            {{ $order->recipient_postal_code }}, {{ $order->recipient_city }}, {{ $order->recipient_address }}<br>
                        @endif
                        @if($order->recipient_nip)
                            NIP: {{ $order->recipient_nip }}@if($order->ksef_additional_entity_id_type === 'IDWew' && $order->ksef_additional_entity_identifier)<br>@endif
                        @endif
                        @if($order->ksef_additional_entity_id_type === 'IDWew' && $order->ksef_additional_entity_identifier)
                            @php
                                $idwewDisplay = trim((string) $order->ksef_additional_entity_identifier);
                                if (preg_match('/^([0-9]{10})([0-9]{5})$/', $idwewDisplay, $m)) {
                                    $idwewDisplay = $m[1].'-'.$m[2];
                                }
                            @endphp
                            Id. wewn.: {{ $idwewDisplay }}
                        @endif
                    @else
                        -
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Uwagi do faktury -->
    @if($order->invoice_notes)
    <div class="info-text-compact" style="margin-top: 0;">
        Dodatkowe uwagi do faktury: {{ $order->invoice_notes }}
    </div>
    @endif

    <!-- Termin płatności -->
    @if($order->invoice_payment_delay)
    <div class="info-text-compact">
        Proszę o wystawienie faktury z odroczonym terminem płatności: {{ $order->invoice_payment_delay }} dni.
    </div>
    @endif

    <!-- Informacja o przesłaniu faktury -->
    <div class="info-text-compact">
        Na podany w zamówieniu adres e-mail: <strong>{{ $order->orderer_email }}</strong> zostanie przesłana faktura.
    </div>

    <!-- DANE DOSTĘPOWE DO KURSU -->
    <div class="section-title">DANE DOSTĘPOWE DO KURSU</div>
    
    <div class="info-text" style="margin-bottom: 10px;">
        strona logowania: <a href="{{ $brandPublicUrl }}" target="_blank">{{ $brandPublicLabel }}</a>
    </div>

    <table class="access-table">
        <thead>
            <tr>
                <th>imię i nazwisko</th>
                <th>adres e-mail (proszę o bezbłędne wypełnienie)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $pdfParticipants = $order->relationLoaded('participants')
                    ? $order->participants->sortBy('id')->values()
                    : $order->participants()->orderBy('id')->get();
                if ($pdfParticipants->isEmpty() && $order->primaryParticipant) {
                    $pdfParticipants = collect([$order->primaryParticipant]);
                }
            @endphp
            @forelse($pdfParticipants as $p)
                <tr>
                    <td>{{ trim(($p->participant_firstname ?? '').' '.($p->participant_lastname ?? '')) ?: '—' }}</td>
                    <td>{{ $p->participant_email ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td>{{ $order->display_participant_name ?: '—' }}</td>
                    <td>{{ $order->display_participant_email ?: '—' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="info-text" style="margin-top: 0; font-style: italic;">
        * na {{ $pdfParticipants->count() > 1 ? 'powyższe adresy e-mail' : 'powyższy e-mail' }} zostaną przesłane dane dostępowe do szkolenia.
    </div>

    <!-- Sekcja z pieczątką i podpisem -->
    <div class="signature-section">
        <div class="signature-dots">...........................................................</div>
        <div class="signature-text">   pieczątka szkoły, podpis</div>
    </div>
    <!-- Stopka -->
    <div class="footer">
        <div class="footer-info">
            Zamówienie nr {{ $order->id }} | data zamówienia: {{ $order->formatOrderDateLocal('Y-m-d H:i:s') }}@if($order->ip_address) | IP: {{ $order->ip_address }}@endif
        </div>
        <div class="footer-text">
            Po złożeniu zamówienia prosimy o wydrukowanie wygenerowanego zamówienia w celu ewentualnej weryfikacji.
        </div>
        <div class="footer-text">
            Kontakt: {{ $contactEmail }} lub 501 654 274
        </div>
    </div>
    </div>
</body>
</html>

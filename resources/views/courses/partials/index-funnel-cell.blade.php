@php
    $fs = $fs ?? [];
    $courseId = (int) ($courseId ?? 0);
    $funnelStatsDays = (int) ($funnelStatsDays ?? 30);
    $campaignCount = (int) ($fs['campaigns_count'] ?? 0);
@endphp
<div class="mb-1" title="Kampanie marketingowe przypisane do tego szkolenia (cała historia)">
    <a href="{{ route('marketing-campaigns.index', ['course_id' => $courseId]) }}"
       class="badge text-bg-success text-decoration-none fw-bold px-2 py-1 {{ $campaignCount === 0 ? 'opacity-75' : '' }}">
        <i class="bi bi-megaphone-fill"></i> {{ $campaignCount }}
    </a>
</div>
<div title="Wejścia na opis szkolenia (pnedu.pl, unikalne/dzień, ostatnie {{ $funnelStatsDays }} dni)">
    <i class="bi bi-eye text-muted"></i> {{ number_format((int) ($fs['views_course_show'] ?? 0), 0, ',', ' ') }}
</div>
<div title="Wejścia na formularz (order-form + deferred-order, unikalne/dzień, ostatnie {{ $funnelStatsDays }} dni)">
    <i class="bi bi-ui-checks text-muted"></i> {{ number_format((int) ($fs['views_order_form'] ?? 0), 0, ',', ' ') }}
</div>
<div title="Złożone zamówienia — cała historia (bez anulowanych)">
    <i class="bi bi-cart text-muted"></i> {{ number_format((int) ($fs['orders_submitted'] ?? 0), 0, ',', ' ') }}
</div>
<div title="Wystawiona faktura (invoice_number) — cała historia">
    <i class="bi bi-receipt text-muted"></i> {{ number_format((int) ($fs['orders_invoiced'] ?? $fs['orders_paid'] ?? 0), 0, ',', ' ') }}
</div>
@if(($fs['cr_show_to_invoiced'] ?? null) !== null)
    <div class="text-success fw-semibold" title="Konwersja: opis → faktura (ostatnie {{ $funnelStatsDays }} dni)">
        {{ number_format((float) $fs['cr_show_to_invoiced'], 1, ',', ' ') }}%
    </div>
@elseif(($fs['cr_show_to_order'] ?? null) !== null)
    <div class="text-primary fw-semibold" title="Konwersja: opis → zamówienie (ostatnie {{ $funnelStatsDays }} dni)">
        {{ number_format((float) $fs['cr_show_to_order'], 1, ',', ' ') }}%
    </div>
@endif

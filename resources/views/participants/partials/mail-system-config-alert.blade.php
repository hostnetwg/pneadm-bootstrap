@php
    $cfg = $mailSystemConfig ?? null;
@endphp
@if(is_array($cfg) && !($cfg['real_delivery'] ?? true))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Maile systemowe nie wychodzą na zewnątrz.</strong>
        Skonfigurowany mailer: <code>{{ $cfg['mailer'] ?? '?' }}</code>,
        transport: <code>{{ $cfg['transport'] ?? '?' }}</code>.
        Status „wysłano” w bazie oznacza wtedy zapis do logu Laravel, a nie dostarczenie do skrzynki odbiorcy.
        @if(!empty($cfg['warning']))
            <span class="d-block mt-2 small">{{ $cfg['warning'] }}</span>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
    </div>
@endif

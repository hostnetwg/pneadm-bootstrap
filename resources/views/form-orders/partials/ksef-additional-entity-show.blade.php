{{--
    Blok prezentacji „KSeF – Podmiot3 (metadane)” na widoku szczegółów zamówienia.
    Tylko do odczytu. Logika i ograniczenia opisane w docs/KSEF_FORM_ORDERS.md.

    ETAP 2: obsługiwane role → odbiorca, jst_recipient (rola 8), vat_group_member (rola 9).
    Dla JST i grupy VAT wymagany jest niepusty NIP (fail-fast przed requestem do iFirma).
--}}
@php
    /** @var \App\Models\FormOrder $zamowienie */
    use App\Models\FormOrder;

    $source = $zamowienie->ksef_entity_source ?? FormOrder::KSEF_ENTITY_SOURCE_NONE;
    $role = $zamowienie->ksef_additional_entity_role;
    $idType = $zamowienie->ksef_additional_entity_id_type;
    $identifier = $zamowienie->ksef_additional_entity_identifier;
    $adminNote = $zamowienie->ksef_admin_note;

    $isActive = $zamowienie->isKsefAdditionalEntityEnabled();
    $roleSupported = FormOrder::isKsefRoleSupported($role);
    $idTypeSupported = FormOrder::isKsefIdTypeSupported($idType);
    $roleRequiresNip = FormOrder::isKsefRoleRequiringNip($role);

    $effectiveRole = $isActive ? ($role ?: FormOrder::KSEF_ROLE_ODBIORCA) : null;
    $effectiveIfirmaRole = $isActive && $roleSupported ? FormOrder::ksefRoleIfirmaCode($effectiveRole) : null;

    $effectiveIdentifier = null;
    if ($isActive) {
        if ($idType === FormOrder::KSEF_ID_TYPE_NIP && ! empty(trim((string) $identifier))) {
            $effectiveIdentifier = preg_replace('/[^0-9]/', '', (string) $identifier);
        } elseif (! empty(trim((string) $zamowienie->recipient_nip))) {
            $effectiveIdentifier = preg_replace('/[^0-9]/', '', (string) $zamowienie->recipient_nip);
        }
    }
    $missingRequiredNip = $isActive && $roleRequiresNip && ($effectiveIdentifier === null || $effectiveIdentifier === '');

    $invoiceNotesRaw = (string) ($zamowienie->invoice_notes ?? '');
    $invoiceNotes = mb_strtolower($invoiceNotesRaw);
    $hints = [];
    foreach ([
        'jst' => 'W uwagach do faktury jest „JST” — rozważ rolę jst_recipient (KSeF rola 8).',
        'rola 8' => 'W uwagach do faktury jest „rola 8” (KSeF JST) — rozważ rolę jst_recipient.',
        'rola 9' => 'W uwagach do faktury jest „rola 9” (KSeF członek grupy VAT) — rozważ rolę vat_group_member.',
        'grupa vat' => 'W uwagach do faktury jest „grupa VAT” — rozważ rolę vat_group_member.',
        'podmiot 3' => 'W uwagach do faktury wspomniano „Podmiot 3” — zweryfikuj ksef_entity_source.',
    ] as $needle => $hint) {
        if ($invoiceNotes !== '' && str_contains($invoiceNotes, $needle)) {
            $hints[] = $hint;
        }
    }
@endphp

<div class="mt-2 p-2 border rounded bg-white small">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <strong>
            <i class="bi bi-shield-check"></i> KSeF – Podmiot3 (metadane)
            <span class="badge bg-dark ms-1">ETAP 2</span>
        </strong>
        @if ($isActive)
            <span class="badge bg-success">aktywny</span>
        @else
            <span class="badge bg-secondary">nieaktywny</span>
        @endif
    </div>

    <div class="row g-1">
        <div class="col-md-6">
            <div><span class="text-muted">Źródło:</span> <code>{{ $source }}</code> — {{ FormOrder::ksefEntitySourceLabel($source) }}</div>
            <div>
                <span class="text-muted">Rola:</span>
                <code>{{ $role ?: '—' }}</code>
                @if ($role)
                    — {{ FormOrder::ksefAdditionalEntityRoleLabel($role) }}
                @else
                    — domyślnie: odbiorca
                @endif
                @if ($isActive && ! $roleSupported)
                    <span class="badge bg-warning text-dark ms-1" title="Rola nieobsługiwana przez mapowanie iFirma">fail-fast</span>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div>
                <span class="text-muted">Typ identyfikatora:</span>
                <code>{{ $idType ?: '—' }}</code>
                @if ($isActive && ! $idTypeSupported)
                    <span class="badge bg-warning text-dark ms-1" title="Typ identyfikatora nieobsługiwany (brak cichego fallbacku)">fail-fast</span>
                @endif
            </div>
            <div><span class="text-muted">Identyfikator (zapis):</span> <code>{{ $identifier ?: '—' }}</code></div>
        </div>
    </div>

    @if ($isActive && $roleSupported)
        <div class="mt-1">
            <span class="text-muted">Podmiot3 efektywny:</span>
            rola <code>{{ $effectiveRole }}</code> (iFirma: <code>{{ $effectiveIfirmaRole }}</code>),
            NIP w payloadzie: <code>{{ $effectiveIdentifier ?: '— brak NIP —' }}</code>
            @if ($missingRequiredNip)
                <span class="badge bg-warning text-dark ms-1" title="Rola wymaga niepustego NIP — mapper zablokuje request (fail-fast)">fail-fast: brak NIP</span>
            @endif
        </div>
    @endif

    @if (! empty($adminNote))
        <div class="mt-1">
            <span class="text-muted">Notatka admina:</span>
            <span class="fst-italic">{{ $adminNote }}</span>
        </div>
    @endif

    @if (! empty($hints))
        <div class="alert alert-info py-1 mt-2 mb-0">
            <strong>Sugestie (heurystyka z „Uwagi do faktury”):</strong>
            <ul class="mb-0 mt-1">
                @foreach ($hints as $hint)
                    <li>{{ $hint }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

{{--
    Edytowalna sekcja KSeF – Podmiot3 na widoku szczegółów zamówienia (zapis AJAX).
    Dokumentacja: docs/KSEF_FORM_ORDERS.md
--}}
@php
    /** @var \App\Models\FormOrder $zamowienie */
    use App\Models\FormOrder;

    $ksefSource = $zamowienie->ksef_entity_source ?? FormOrder::KSEF_ENTITY_SOURCE_NONE;
    $ksefRole = $zamowienie->ksef_additional_entity_role;
    $ksefIdType = $zamowienie->ksef_additional_entity_id_type;
    $ksefIdentifier = $zamowienie->ksef_additional_entity_identifier;
    $ksefAdminNote = $zamowienie->ksef_admin_note;

    $invoiceNotes = mb_strtolower((string) ($zamowienie->invoice_notes ?? ''));
    $ksefHints = [];
    foreach ([
        'jst' => 'JST → rozważ jst_recipient (rola 8).',
        'rola 8' => '„rola 8” → jst_recipient.',
        'rola 9' => '„rola 9” → vat_group_member.',
        'grupa vat' => 'grupa VAT → vat_group_member.',
        'podmiot 3' => 'Podmiot 3 → zweryfikuj źródło.',
    ] as $needle => $hint) {
        if ($invoiceNotes !== '' && str_contains($invoiceNotes, $needle)) {
            $ksefHints[] = $hint;
        }
    }

    $isRecipient = $ksefSource === FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT;
    $isRoleJst = $ksefRole === FormOrder::KSEF_ROLE_JST_RECIPIENT;
    $isRoleVatGroup = $ksefRole === FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER;
    $hasExtra = ($ksefIdType !== null && $ksefIdType !== '')
        || ($ksefIdentifier !== null && trim((string) $ksefIdentifier) !== '')
        || ($ksefAdminNote !== null && trim((string) $ksefAdminNote) !== '');
@endphp

<div class="card mt-3" id="ksefSettingsCard">
    <div class="card-header bg-warning text-dark py-1 px-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 small">
            <i class="bi bi-shield-check"></i> KSeF Podmiot3
            <span class="badge bg-dark ms-1" style="font-size:0.65rem">ETAP 2</span>
        </h6>
        <span class="badge bg-secondary" id="ksefSaveStatus" style="font-size:0.65rem" title="Status zapisu">—</span>
    </div>
    <div class="card-body py-2 px-2" id="ksefSettingsForm"
         data-save-url="{{ route('form-orders.ksef-settings.update', $zamowienie->id) }}">

        <div id="ksefValidationErrors" class="alert alert-danger py-1 px-2 mb-1 small d-none" role="alert"></div>

        @if (! empty($ksefHints))
            <div class="small text-info mb-1" id="ksefInlineHints" title="{{ implode(' ', $ksefHints) }}">
                <i class="bi bi-info-circle"></i> {{ $ksefHints[0] }}
                @if (count($ksefHints) > 1)
                    <span class="text-muted">(+{{ count($ksefHints) - 1 }})</span>
                @endif
            </div>
        @endif

        <div class="row g-1 mb-1">
            <div class="col-6">
                <label for="show_ksef_entity_source" class="form-label small mb-0 text-muted">Źródło Podmiot3</label>
                <select class="form-select form-select-sm" id="show_ksef_entity_source" data-ksef-field>
                    <option value="{{ FormOrder::KSEF_ENTITY_SOURCE_NONE }}" @selected($ksefSource === FormOrder::KSEF_ENTITY_SOURCE_NONE)>
                        Brak (none)
                    </option>
                    <option value="{{ FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT }}" @selected($ksefSource === FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT)>
                        recipient_*
                    </option>
                </select>
            </div>
            <div class="col-6">
                <label for="show_ksef_additional_entity_role" class="form-label small mb-0 text-muted">Rola (kod)</label>
                <select class="form-select form-select-sm" id="show_ksef_additional_entity_role" data-ksef-field>
                    <option value="" @selected($ksefRole === null || $ksefRole === '')>— odbiorca —</option>
                    <option value="{{ FormOrder::KSEF_ROLE_ODBIORCA }}" @selected($ksefRole === FormOrder::KSEF_ROLE_ODBIORCA)>
                        odbiorca
                    </option>
                    <option value="{{ FormOrder::KSEF_ROLE_JST_RECIPIENT }}" @selected($ksefRole === FormOrder::KSEF_ROLE_JST_RECIPIENT)>
                        JST (rola 8)
                    </option>
                    <option value="{{ FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER }}" @selected($ksefRole === FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER)>
                        grupa VAT (9)
                    </option>
                </select>
            </div>
        </div>

        <div id="ksefRoleHintJst" class="small text-info mb-1 @if(!($isRoleJst && $isRecipient)) d-none @endif">
            <i class="bi bi-info-circle"></i> JST: wymagany NIP (recipient_* lub identyfikator).
        </div>
        <div id="ksefRoleHintVat" class="small text-info mb-1 @if(!($isRoleVatGroup && $isRecipient)) d-none @endif">
            <i class="bi bi-info-circle"></i> Grupa VAT: NIP członka obowiązkowy.
        </div>
        <div id="ksefIdTypeWarning" class="small text-warning mb-1 @if(!($ksefIdType !== null && $ksefIdType !== '' && $ksefIdType !== FormOrder::KSEF_ID_TYPE_NIP && $isRecipient)) d-none @endif">
            <i class="bi bi-exclamation-triangle"></i> Typ ≠ NIP — fail-fast przy wystawianiu.
        </div>

        <p class="mb-1">
            <a class="small text-decoration-none" data-bs-toggle="collapse" href="#ksefExtraFields" role="button"
               aria-expanded="{{ $hasExtra ? 'true' : 'false' }}" aria-controls="ksefExtraFields">
                <i class="bi bi-chevron-down"></i> Identyfikator i notatka
            </a>
            <span class="text-muted small">· zapis auto</span>
            <a href="{{ route('form-orders.edit', $zamowienie->id) }}" class="small text-decoration-none ms-1">edycja</a>
        </p>

        <div class="collapse @if($hasExtra) show @endif" id="ksefExtraFields">
            <div class="row g-1 mb-1">
                <div class="col-4">
                    <label for="show_ksef_additional_entity_id_type" class="form-label small mb-0 text-muted">Typ ID</label>
                    <select class="form-select form-select-sm" id="show_ksef_additional_entity_id_type" data-ksef-field>
                        <option value="" @selected($ksefIdType === null || $ksefIdType === '')>—</option>
                        @foreach (FormOrder::KSEF_ADDITIONAL_ENTITY_ID_TYPES as $idTypeOption)
                            <option value="{{ $idTypeOption }}" @selected($ksefIdType === $idTypeOption)>
                                {{ $idTypeOption }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-8">
                    <label for="show_ksef_additional_entity_identifier" class="form-label small mb-0 text-muted">Wartość ID</label>
                    <input type="text" maxlength="50" class="form-control form-control-sm"
                           id="show_ksef_additional_entity_identifier" data-ksef-field
                           value="{{ $ksefIdentifier }}" placeholder="NIP">
                </div>
            </div>
            <div class="mb-0">
                <label for="show_ksef_admin_note" class="form-label small mb-0 text-muted">Notatka wewn.</label>
                <textarea class="form-control form-control-sm" id="show_ksef_admin_note" rows="1"
                          data-ksef-field placeholder="informacje wewnętrzne dla administratorów">{{ $ksefAdminNote }}</textarea>
            </div>
        </div>
    </div>
</div>

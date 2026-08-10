{{--
    Sekcja formularza „KSeF – Podmiot3 (metadane)” — stan: ETAP 3 (adm.pnedu.pl).

    Dokumentacja techniczna: docs/KSEF_FORM_ORDERS.md
    Założenia:
      - obsługujemy role: odbiorca, jst_recipient (rola 8), vat_group_member (rola 9),
      - `ksef_entity_source ∈ {none, recipient}`,
      - kolumny `recipient_*` są historycznie nazwane, ale pełnią rolę danych Podmiotu3
        niezależnie od wybranej roli (nota nazewnicza w KSEF_FORM_ORDERS.md),
      - payload iFirma: root PodmiotyDodatkowe (od 2026-08-04; nie Kontrahent.OdbiorcaNaFakturze),
      - inne role / id_type niż NIP → fail-fast (HTTP 422 w kontrolerze),
      - dla JST i grupy VAT dodatkowo wymagany niepusty NIP — fail-fast przed requestem do iFirma.
      - bez zmian w publicznym formularzu pnedu.pl.
--}}
@php
    /** @var \App\Models\FormOrder|null $zamowienie */
    use App\Models\FormOrder;

    // Nowe zamówienie: domyślnie włączony Podmiot3 z recipient_*; edycja: wartość z rekordu.
    $ksefSourceDefault = $zamowienie
        ? ($zamowienie->ksef_entity_source ?? FormOrder::KSEF_ENTITY_SOURCE_NONE)
        : FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT;
    $ksefSource = old('ksef_entity_source', $ksefSourceDefault);
    $ksefRole = old('ksef_additional_entity_role', $zamowienie?->ksef_additional_entity_role);
    $ksefIdType = old('ksef_additional_entity_id_type', $zamowienie?->ksef_additional_entity_id_type);
    $ksefIdentifier = old('ksef_additional_entity_identifier', $zamowienie?->ksef_additional_entity_identifier);
    $ksefAdminNote = old('ksef_admin_note', $zamowienie?->ksef_admin_note);

    // Heurystyka alertowa na podstawie invoice_notes (TYLKO alert, bez auto-mapowania).
    $invoiceNotesRaw = (string) ($zamowienie?->invoice_notes ?? '');
    $invoiceNotes = mb_strtolower($invoiceNotesRaw);
    $ksefHints = [];
    foreach ([
        'jst' => 'W uwagach do faktury wykryto frazę „JST”. Rozważ wybór roli „JST — rola 8”, jeśli Odbiorca jest jednostką samorządu terytorialnego.',
        'rola 8' => 'W uwagach do faktury wykryto „rola 8”. Odpowiednikiem w kanonicznych kodach jest rola „jst_recipient” (iFirma: JEDN_SAMORZADU_TERYT).',
        'rola 9' => 'W uwagach do faktury wykryto „rola 9”. Odpowiednikiem w kanonicznych kodach jest rola „vat_group_member” (iFirma: CZLONEK_GRUPY_VAT).',
        'grupa vat' => 'W uwagach do faktury wspomniano „grupę VAT”. Rozważ rolę „Członek grupy VAT — rola 9”, jeśli Odbiorca jest członkiem grupy VAT.',
        'podmiot 3' => 'W uwagach do faktury wspomniano „Podmiot 3”. Zweryfikuj ksef_entity_source i wybraną rolę.',
        'odbior' => 'W uwagach do faktury wykryto frazę „odbiorca”. Upewnij się, że dane Podmiotu3 (recipient_*) są uzupełnione.',
    ] as $needle => $hint) {
        if ($invoiceNotes !== '' && str_contains($invoiceNotes, $needle)) {
            $ksefHints[] = $hint;
        }
    }

    $isRecipient = $ksefSource === FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT;
    $isRoleJst = $ksefRole === FormOrder::KSEF_ROLE_JST_RECIPIENT;
    $isRoleVatGroup = $ksefRole === FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER;
@endphp

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h6 class="mb-0">
            <i class="bi bi-shield-check"></i> KSeF – Podmiot3 (metadane) <span class="badge bg-dark ms-2">ETAP 3</span>
        </h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Metadane sterujące traktowaniem danych <code>recipient_*</code> jako Podmiotu3 (dodatkowego podmiotu na fakturze) przy wystawianiu faktury w iFirma i wysyłce do KSeF.
            Obsługiwane role: <code>odbiorca</code> (iFirma: <code>ODBIORCA</code>), <code>jst_recipient</code> (iFirma: <code>JEDN_SAMORZADU_TERYT</code>, KSeF rola 8), <code>vat_group_member</code> (iFirma: <code>CZLONEK_GRUPY_VAT</code>, KSeF rola 9). Typ ID: <code>NIP</code> lub <code>IDWew</code> (przy JST/VAT + IDWew wymagany też NIP w <code>recipient_nip</code>).
            Szczegóły: <code>docs/KSEF_FORM_ORDERS.md</code>.
        </p>

        <div class="alert alert-secondary py-2 small mb-3">
            <i class="bi bi-info-circle"></i>
            <strong>Nota nazewnicza:</strong> kolumny <code>recipient_*</code> historycznie nazywały się „odbiorca”, ale semantycznie trzymają dane <strong>Podmiotu3</strong> niezależnie od wybranej roli. Nazwa kolumn pozostaje dla zgodności wstecznej z publicznym formularzem pnedu.pl.
        </div>

        @if (! empty($ksefHints))
            <div class="alert alert-info py-2 mb-3">
                <strong><i class="bi bi-info-circle"></i> Sugestia (heurystyka z „Uwagi do faktury”):</strong>
                <ul class="mb-0 mt-1 small">
                    @foreach ($ksefHints as $hint)
                        <li>{{ $hint }}</li>
                    @endforeach
                </ul>
                <div class="small text-muted mt-1">Heurystyka informacyjna — nic nie jest ustawiane automatycznie, decyzję podejmuje administrator.</div>
            </div>
        @endif

        <div class="row g-3 mb-2 align-items-end">
            <div class="col-md-6">
                <input type="hidden" name="ksef_entity_source" value="{{ FormOrder::KSEF_ENTITY_SOURCE_NONE }}">
                <div class="form-check mb-0">
                    <input class="form-check-input @error('ksef_entity_source') is-invalid @enderror"
                           type="checkbox" id="ksef_entity_source" name="ksef_entity_source"
                           value="{{ FormOrder::KSEF_ENTITY_SOURCE_RECIPIENT }}"
                           @checked($isRecipient)>
                    <label class="form-check-label" for="ksef_entity_source">
                        Użyj danych Odbiorcy (<code>recipient_*</code>) jako Podmiot3 na fakturze
                    </label>
                    @error('ksef_entity_source')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-text mb-0">
                    Zaznaczone = Podmiot3 z ODBIORCY. Odznaczone = bez <code>PodmiotyDodatkowe</code> (rola ignorowana przy wystawianiu).
                </div>
            </div>
            <div class="col-md-6">
                <label for="ksef_additional_entity_role" class="form-label">Rola Podmiotu3 (kanoniczny kod)</label>
                <select class="form-select @error('ksef_additional_entity_role') is-invalid @enderror"
                        id="ksef_additional_entity_role" name="ksef_additional_entity_role">
                    <option value="" @selected($ksefRole === null || $ksefRole === '')>
                        — nie ustawiono (domyślnie: odbiorca) —
                    </option>
                    <option value="{{ FormOrder::KSEF_ROLE_ODBIORCA }}" @selected($ksefRole === FormOrder::KSEF_ROLE_ODBIORCA)>
                        {{ FormOrder::ksefAdditionalEntityRoleLabel(FormOrder::KSEF_ROLE_ODBIORCA) }}
                    </option>
                    <option value="{{ FormOrder::KSEF_ROLE_JST_RECIPIENT }}" @selected($ksefRole === FormOrder::KSEF_ROLE_JST_RECIPIENT)>
                        {{ FormOrder::ksefAdditionalEntityRoleLabel(FormOrder::KSEF_ROLE_JST_RECIPIENT) }}
                    </option>
                    <option value="{{ FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER }}" @selected($ksefRole === FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER)>
                        {{ FormOrder::ksefAdditionalEntityRoleLabel(FormOrder::KSEF_ROLE_VAT_GROUP_MEMBER) }}
                    </option>
                </select>
                @error('ksef_additional_entity_role')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @if ($isRoleJst && $isRecipient)
            <div class="alert alert-info py-1 mb-2 small">
                <i class="bi bi-info-circle"></i>
                <strong>JST — rola 8:</strong> w <code>recipient_*</code> powinny być dane <strong>jednostki samorządu terytorialnego</strong> (gminy/powiatu/województwa), a nie jednostki podrzędnej. NIP podmiotu jest obowiązkowy — przy pustym NIP request do iFirma zostanie zablokowany (fail-fast).
            </div>
        @endif
        @if ($isRoleVatGroup && $isRecipient)
            <div class="alert alert-info py-1 mb-2 small">
                <i class="bi bi-info-circle"></i>
                <strong>Członek grupy VAT — rola 9:</strong> w <code>recipient_*</code> powinny być dane <strong>członka grupy VAT</strong> (jednostka, która faktycznie otrzymała towar/usługę) z jego NIP. NIP <strong>grupy VAT</strong> wpisujesz w nagłówku nabywcy (firm_nip). NIP członka jest obowiązkowy — fail-fast przy pustym NIP.
            </div>
        @endif

        <div class="row mt-3">
            <div class="col-md-4">
                <label for="ksef_additional_entity_id_type" class="form-label">Typ identyfikatora</label>
                <select class="form-select @error('ksef_additional_entity_id_type') is-invalid @enderror"
                        id="ksef_additional_entity_id_type" name="ksef_additional_entity_id_type">
                    <option value="" @selected($ksefIdType === null || $ksefIdType === '')>
                        — nie ustawiono (fallback do recipient_nip) —
                    </option>
                    @foreach (FormOrder::KSEF_ADDITIONAL_ENTITY_ID_TYPES as $idTypeOption)
                        <option value="{{ $idTypeOption }}" @selected($ksefIdType === $idTypeOption)>
                            {{ $idTypeOption }}{{ in_array($idTypeOption, [FormOrder::KSEF_ID_TYPE_NIP, FormOrder::KSEF_ID_TYPE_IDWEW], true) ? ' — obsługiwane' : ' — nieobsługiwane (fail-fast)' }}
                        </option>
                    @endforeach
                </select>
                @error('ksef_additional_entity_id_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if ($ksefIdType !== null && $ksefIdType !== '' && ! FormOrder::isKsefIdTypeSupported($ksefIdType) && $isRecipient)
                    <div class="alert alert-warning py-1 mt-2 mb-0 small">
                        <i class="bi bi-exclamation-triangle"></i>
                        Typ <code>{{ $ksefIdType }}</code> nie jest obsługiwany. Wystawienie faktury z Podmiotem3 zostanie zablokowane (fail-fast, bez cichego fallbacku do recipient_nip).
                    </div>
                @endif
            </div>

            <div class="col-md-8">
                <label for="ksef_additional_entity_identifier" class="form-label">Wartość identyfikatora</label>
                <input type="text" maxlength="50"
                       class="form-control @error('ksef_additional_entity_identifier') is-invalid @enderror"
                       id="ksef_additional_entity_identifier" name="ksef_additional_entity_identifier"
                       value="{{ $ksefIdentifier }}">
                @error('ksef_additional_entity_identifier')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Dla typu <code>NIP</code> nadpisuje <code>recipient_nip</code> w payloadzie iFirma (po usunięciu znaków nie-cyfrowych).
                    Puste + <code>NIP</code> ⇒ używamy <code>recipient_nip</code>.
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <label for="ksef_admin_note" class="form-label">Notatka administratora (wewnętrzna)</label>
                <textarea class="form-control @error('ksef_admin_note') is-invalid @enderror"
                          id="ksef_admin_note" name="ksef_admin_note" rows="2"
                          placeholder="Kontekst decyzji, tickety, przypomnienia — nie jest wysyłane do iFirma ani do KSeF.">{{ $ksefAdminNote }}</textarea>
                @error('ksef_admin_note')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="alert alert-secondary py-2 mt-3 mb-0 small">
            <i class="bi bi-info-circle"></i>
            Gdy checkbox Podmiot3 jest odznaczony, wartości roli / typu identyfikatora / identyfikatora nie są usuwane automatycznie — pozostają w bazie, ale są ignorowane przez mapowanie do iFirma. Jeśli chcesz je zresetować, wyczyść je ręcznie i zapisz formularz.
        </div>
    </div>
</div>

<script>
    (function () {
        const roleEl = document.getElementById('ksef_additional_entity_role');
        const sourceEl = document.getElementById('ksef_entity_source');
        if (!roleEl || !sourceEl) {
            return;
        }
        roleEl.addEventListener('change', function () {
            const role = roleEl.value;
            if ((role === 'jst_recipient' || role === 'vat_group_member') && !sourceEl.checked) {
                sourceEl.checked = true;
            }
        });
    })();
</script>

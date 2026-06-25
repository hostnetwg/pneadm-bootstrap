# Etap B — JS tracking formularza zamówienia + porzucenia

Data utworzenia: 2026-06-25
Status: **PR B1 + B1a wdrożone lokalnie** (backend endpoint + kontrakt + hardening + testy). B2/B3/B4 — do zrobienia.

## Cel etapu

Uzupełnić lukę między backendowym `order_form_viewed` a `order_form_submit_attempted` / `form_order_created`:

1. Ilu użytkowników wchodzi w formularz, ale go nie zaczyna?
2. Ilu zaczyna, ale nie klika wysłania?
3. Na których sekcjach formularza najczęściej kończą aktywność?
4. Czy klikają CTA, ale nie tworzą zamówienia?
5. Które kursy / warianty ceny / kampanie generują najwięcej porzuceń?

## Kolejność PR-ów

| PR | Zakres | Status |
|----|--------|--------|
| **B1** | Endpoint batch + enumy + sanitizer + tryby + testy RODO | ✅ ZROBIONE |
| **B1a** | Hardening: same-origin guard + namespacowanie `event_uuid` | ✅ ZROBIONE |
| B2 | Lekki JS collector na formularzu zamówienia | ⏳ TODO |
| B3 | Agregacja porzuceń (komenda, idempotentna) | ⏳ TODO |
| B4 | Dashboard porzuceń | ⏳ TODO |

---

## PR B1 — szczegóły wdrożenia

### Endpoint

```
POST /analytics/client-events        (nazwa trasy: analytics.client-events.store)
```

Projekt: **pnedu** (formularz zamówienia żyje w pnedu, baza analityki = `pne_analytics`).

### Kontrakt wejścia (JSON)

```json
{
  "course_id": 524,
  "price_variant_id": 78,
  "events": [
    { "event_name": "order_form_started", "event_uuid": "<uuid>", "trigger": "first_interaction" },
    { "event_name": "order_form_section_interacted", "section_key": "buyer_data" },
    { "event_name": "order_form_cta_clicked", "cta_key": "add_participant" },
    { "event_name": "order_form_submit_clicked" }
  ]
}
```

- `course_id` — wymagany, dodatnia liczba całkowita (inaczej batch jest porzucany).
- `price_variant_id` — opcjonalny; trafia do `metadata.price_variant_id`.
- `events` — tablica, maks. **20** eventów (nadmiar jest ucinany).
- `event_uuid` — opcjonalny; akceptowany **tylko** gdy jest poprawnym UUID (dedup retry), inaczej serwer generuje własny.
- `occurred_at_client` — **ignorowane**; źródłem prawdy jest czas serwera.

### Eventy dozwolone z przeglądarki (MVP)

Tylko te 4 nazwy mogą pochodzić z JS (reszta wyłącznie z backendu):

| event_name | wymaga | kategoria |
|------------|--------|-----------|
| `order_form_started` | (opcjonalnie `trigger`) | `order_form` |
| `order_form_section_interacted` | `section_key` (whitelist) | `order_form` |
| `order_form_cta_clicked` | `cta_key` (whitelist) | `order_form` |
| `order_form_submit_clicked` | — | `order_form` |

Event sekcji/CTA bez whitelistowanego klucza jest **pomijany** (nie zapisujemy tekstu z DOM).

### Whitelisty wartości (kontroler `ClientEventController`)

- `section_key`: `buyer_data`, `recipient_data`, `participants`, `payment_method`, `invoice`, `consents`, `summary`
- `cta_key`: `add_participant`, `remove_participant`, `select_online_payment`, `select_deferred_invoice`, `back_to_course`, `submit_order`
- `trigger`: `first_interaction`, `field_change`, `section_click`, `payment_select`, `cta_click`, `page_focus`

### Tryby analityki a eventy JS

Egzekwowane przez `AnalyticsModeResolver` (jak dla eventów backendowych):

| Tryb | Eventy JS |
|------|-----------|
| `off` | brak |
| `aggregate_only` | brak |
| `light` | tylko `order_form_started` i `order_form_submit_clicked` |
| `standard` | wszystkie 4 (MVP) |
| `full` | wszystkie 4 (MVP) + przyszłe szczegółowe |

Decyzja: **standard obejmuje MVP JS** — inaczej po wdrożeniu na produkcji (która jest na `standard`) nie byłoby żadnych danych.

### Limity i rate limit (konfigurowalne w `config/analytics.php`)

| Ustawienie | Domyślnie | ENV |
|-----------|-----------|-----|
| maks. eventów / batch | 20 | `ANALYTICS_CLIENT_EVENTS_MAX_BATCH` |
| maks. rozmiar payloadu | 10240 B | `ANALYTICS_CLIENT_EVENTS_MAX_PAYLOAD_BYTES` |
| rate limit / min / IP | 60 | `ANALYTICS_CLIENT_EVENTS_RATE_LIMIT` |

Rate limiter: nazwany limiter `analytics-client-events` (per IP) w `AppServiceProvider`.

### Bezpieczeństwo / RODO

- Endpoint **zawsze** odpowiada `204 No Content` — nigdy nie blokuje użytkownika (fail-silent).
- Zapisujemy wyłącznie techniczne klucze z whitelisty (`AnalyticsPayloadSanitizer`). Żadnych wartości pól, e-maili, telefonów, NIP, nazwisk, adresów, danych fakturowych ani tekstu z DOM.
- Ruch wewnętrzny (cookie `pne_skip_analytics` / `pne_skip_funnel`) oraz boty/preview są pomijane.
- Trasa zwolniona z CSRF (`bootstrap/app.php`) — by wspierać `navigator.sendBeacon` (który nie ustawi tokenu). Brak mutacji stanu biznesowego, more-origin web, fail-silent, bez PII.

### Pliki zmienione/dodane (pnedu)

- `app/Enums/Analytics/AnalyticsEventName.php` — 4 nowe eventy + kategorie + `clientJsEvents()` / `isClientJsEvent()`.
- `app/Services/Analytics/AnalyticsModeResolver.php` — `light` dopuszcza `order_form_started` + `order_form_submit_clicked`.
- `app/Services/Analytics/AnalyticsPayloadSanitizer.php` — dodane klucze metadata `cta_key`, `trigger` (`section_key` już był).
- `app/Http/Controllers/Analytics/ClientEventController.php` — **nowy** endpoint batch.
- `app/Providers/AppServiceProvider.php` — rate limiter `analytics-client-events`.
- `config/analytics.php` — sekcja `client_events`.
- `routes/web.php` — trasa `analytics.client-events.store` z throttlem.
- `bootstrap/app.php` — `analytics/client-events` na liście wyjątków CSRF.
- `tests/Feature/AnalyticsClientEventsStageB1Test.php` — **19 testów** (RODO, limity, tryby, fail-silent, skip admina/botów, dedup UUID).

### Testy

`sail artisan test --filter=Analytics` → 94 passed (675 assertions), w tym 19 nowych dla B1.

---

## PR B1a — hardening backendu (2026-06-25)

Dwa zabezpieczenia dodane przed B2. **Bez** zmian zakresu eventów, bez JS, bez porzuceń.

### 1. Same-origin guard

CSRF-exempt dla `POST /analytics/client-events` **zostaje** (wsparcie `sendBeacon`, brak mutacji stanu, fail-silent, bez PII). Dodano lekką kontrolę nagłówków (porównanie po **HOŚCIE**, nie po pełnym URL):

- jest `Origin` i host ≠ host aplikacji → `204`, nie zapisujemy eventów;
- brak `Origin`, jest `Referer` i host ≠ host aplikacji → `204`, nie zapisujemy eventów;
- oba nagłówki puste → best-effort, **nie blokujemy** (przepuszczamy przez pozostałe zabezpieczenia);
- nigdy `403` (żeby nie generować problemów po stronie formularza);
- **nie logujemy** pełnych URL-i ani referrerów; raw `Origin`/`Referer` **nie trafiają** do analityki (zapisujemy tylko `referrer_domain` z istniejącego kontekstu).

Implementacja: `ClientEventController::isCrossOriginRequest()` + `hostMatches()`.

### 2. Namespacowanie `event_uuid` (UUID klienta = tylko seed dedup)

Kolumna `analytics_events.event_uuid` to typ `uuid` (**char(36)**, unique) — `client_js|sha256(...)` by się nie zmieścił. Dlatego klientowski UUID jest **tylko seedem deduplikacji**, a finalny `event_uuid` to deterministyczny **UUIDv5** (jak w `BackendAnalyticsTracker`):

```
event_uuid = Uuid::uuid5(NAMESPACE_URL, "client_js|{order_form_session_id}|{event_name}|{client_event_uuid}")
```

Gwarancje:

- ten sam `client_event_uuid` + ta sama sesja formularza + ten sam `event_name` → **ten sam** `event_uuid` (dedup na unique + insertOrIgnore);
- inna sesja formularza → **brak kolizji**;
- inny `event_name` → **brak kolizji**;
- brak/niepoprawny UUID klienta → serwer generuje własny `event_uuid` (jak dotąd).

`client_event_uuid` **nie jest** zapisywany do metadata (brak potrzeby diagnostycznej, czystość RODO).

Implementacja: `ClientEventController::namespacedEventUuid()` (Ramsey `Uuid::uuid5`).

### Audyt whitelist (bez zmian)

Audyt realnego `resources/views/courses/order-form.blade.php` (pnedu):

- sekcje formularza: buyer (`buyer_data`), odbiorca (`recipient_data`), uczestnik (`participants`), uwagi do faktury (`invoice`), płatność (`payment_method`) — **pokrywają się** z whitelistą `section_key`;
- `cta_key`: submit (`submit_order`), „Powrót do szczegółów szkolenia" (`back_to_course`), wybór płatności online/odroczona (`select_online_payment`/`select_deferred_invoice`) — **pokrywają się**;
- `consents`, `summary`, `add_participant`, `remove_participant` — nie występują w obecnym jednokrokowym formularzu (zostają w whiteliście jako rezerwa na przyszłość, są nieszkodliwe);
- przyciski GUS (buyer/recipient) — **świadomie poza zakresem** (B-future), JS ich nie emituje;
- `invoice` zostaje (NIE dodajemy `invoice_data` — kolidowałoby z zakazanym fragmentem klucza `invoice_data` w sanitizerze).

**Wniosek: whitelisty bez zmian.**

### Batch limit (bez zmian)

> 20 eventów → zapisujemy maks. 20 pierwszych; reszta ucinana. Całego batcha **nie odrzucamy** (best-effort).

### Testy B1a

`sail artisan test --filter=Analytics` → **102 passed** (706 assertions). W tym 27 testów klasy B1/B1a, m.in.: matching/foreign Origin, matching/foreign Referer, brak nagłówków, namespacowanie UUID, dedup w tej samej sesji, brak kolizji między sesjami i między `event_name`, niepoprawny UUID → serwerowy, limit batcha, brak PII.

---

## Co dalej (B2–B4) — skrót

- **B2**: mały moduł JS ładowany tylko na stronie formularza zamówienia; debounce 2–5 s, batch ≤20, flush na `visibilitychange`, `sendBeacon` best-effort; działa tylko gdy analityka włączona i tryb pozwala; brak wartości pól; awaria JS/endpointu nie blokuje submitu.
- **B3**: porzucenia jako **agregacja po czasie** (nie event `order_form_abandoned`). Okno startowe: **24 h**. Definicje: `viewed_not_started`, `started_not_submitted`, `submit_clicked_not_submitted`, `submitted_not_created`. Komenda np. `analytics:aggregate-abandonments` (idempotentna).
- **B4**: prosty dashboard porzuceń (filtry kurs/kampania/data). Najpierw dane oglądamy w `/analytics/debug-events`.

## Smoke test produkcyjny (po B2)

1. Tryb analityki `standard` (lub `full`).
2. Formularz w incognito, bez cookie admina `pne_skip_analytics`.
3. Wejść z UTM, kliknąć pierwsze pole, przejść 2–3 sekcje.
4. Sprawdzić `/analytics/debug-events` — oczekiwane: `order_form_viewed`, `order_form_started`, `order_form_section_interacted`, `order_form_cta_clicked`, ew. `order_form_submit_clicked`.
5. Potwierdzić brak PII w `metadata`. Sprawdzić logi i failed jobs.

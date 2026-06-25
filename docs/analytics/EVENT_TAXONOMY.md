# Taksonomia Eventów Analitycznych

Data utworzenia/aktualizacji: 2026-06-24  
Status: wersja robocza, do potwierdzenia przez właściciela

## Cel Dokumentu

Dokument opisuje planowane eventy analityczne dla `pne_analytics`. Taksonomia ma ograniczać chaos nazewniczy i zapobiegać przypadkowemu zapisywaniu danych osobowych.

## Zasady Ogólne

Każdy event powinien mieć:

- `event_uuid`,
- `event_name`,
- `event_category`,
- `occurred_at`,
- `app_source`,
- `analytics_session_id`, jeśli dostępny,
- `course_id`, jeśli dotyczy szkolenia,
- `campaign_code`, jeśli dotyczy kampanii,
- `order_form_session_id`, jeśli dotyczy formularza,
- `form_order_id`, jeśli powstało zamówienie; ten identyfikator nie może trafiać do eksportów AI-safe ani raportów dla zewnętrznego AI,
- `metadata_json` tylko z dozwolonymi metadanymi.

Nie zapisywać wartości pól formularza.

## Dane Zakazane W Eventach

Nie wolno zapisywać:

- e-maili,
- telefonów,
- NIP,
- adresów,
- imion i nazwisk,
- nazw konkretnych szkół lub instytucji, jeśli mogą identyfikować klienta,
- danych fakturowych,
- danych płatności,
- tokenów,
- kluczy API,
- surowych payloadów bramek płatności,
- surowych logów z danymi osobowymi.

## Dozwolone Metadane

Dozwolone przykłady:

- `buyer_type`,
- `payment_type`,
- `payment_gateway`,
- `has_recipient`,
- `recipient_section_used`,
- `gus_lookup_used`,
- `gus_lookup_success`,
- `participant_count`,
- `order_flow`,
- `amount_gross`,
- `form_order_status`,
- `participant_count_bucket`,
- `ksef_option_selected`,
- `invoice_path_type`,
- `landing_target`,
- `campaign_content_depth`,
- `campaign_channel`,
- `cta_type`,
- `field_key`,
- `section_key`,
- `error_rule`,
- `time_spent_seconds`,
- `device_type`,
- `browser_family`.

## Eventy Backendowe

| Event | Kiedy powstaje | Metadane dozwolone | Tryby |
|---|---|---|---|
| `campaign_short_link_visit` | wejście na `/l/{campaign_code}` | `campaign_code`, `landing_target`, `utm_*`, `device_type` | `full`, `standard`, `light`, `aggregate_only` |
| `campaign_redirect_resolved` | po rozwiązaniu short linku do docelowego URL | `campaign_code`, `course_id`, `landing_target` | `full`, `standard`, `light` |
| `utm_captured` | przy przechwyceniu UTM/cookie atrybucji | `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `campaign_code` | `full`, `standard` |
| `course_description_viewed` | wyświetlenie opisu szkolenia | `course_id`, `campaign_code`, `landing_target` | `full`, `standard`, `light`, `aggregate_only` |
| `order_form_viewed` | wejście w formularz | `course_id`, `campaign_code`, `landing_target`, `price_variant_id` | `full`, `standard`, `light`, `aggregate_only` |
| `order_form_submit_attempted` | submit formularza przed zapisem | `course_id`, `campaign_code`, `payment_type`, `buyer_type`, `participant_count` | `full`, `standard`, `light` |
| `order_form_validation_failed` | nieudana walidacja backendowa | `course_id`, `field_keys`, `error_rules`, `buyer_type`, `payment_type` | `full`, `standard` |
| `form_order_created` | po skutecznym zapisie `FormOrder` i uczestników, w online przed przejściem do bramki | `form_order_id`, `course_id`, `campaign_code`, `order_flow`, `payment_type`, `buyer_type`, `participant_count`, `has_price_variant`, `has_recipient`, `amount_gross`, `form_order_status` | `full`, `standard`, `light` |
| `online_payment_selected` | wybór płatności online po udanej walidacji (wdrożone w Etapie 2A-1) | `course_id`, `payment_type=online`, `payment_gateway` (`payu`/`paynow`/`unknown`), `buyer_type`, `has_price_variant`, `order_flow=online` | `full`, `standard`, `light` |
| `deferred_invoice_selected` | wybór faktury / płatności odroczonej po udanej walidacji (wdrożone w Etapie 2A-1) | `course_id`, `payment_type=deferred_invoice`, `buyer_type`, `has_price_variant`, `order_flow=deferred` | `full`, `standard`, `light` |
| `payment_order_created` | utworzenie `OnlinePaymentOrder` (wdrożone w Etapie 2A-2) | `payment_order_id`, `form_order_id`, `payment_gateway`, `amount_snapshot`, `buyer_type`, `has_price_variant`, `order_flow=online` | `full`, `standard`, `light` |
| `payment_status_changed` | zmiana statusu płatności online (webhook/return sync PayU/PayNow; wdrożone w Etapie 2B-1) | `payment_order_id`, `form_order_id`, `course_id`, `amount_snapshot`, `payment_gateway`, `payment_status` (`created`/`pending`/`paid`/`failed`/`canceled`/`expired`/`unknown`), `payment_previous_status`, `status_source` (`webhook`/`return_sync`), `payment_type=online`, `order_flow=online`; deterministyczny event_uuid; bez sesji i kontekstu requestu | `full`, `standard`, `light` |
| `invoice_created` | wdrożone w Etapie 2C-1 (`pneadm`, observer `FormOrderObserver`) — pierwsze ustawienie poprawnego `form_orders.invoice_number` (przejście empty→present; iFirma lub ręcznie); oznacza „zafakturowane / rozliczone operacyjnie", NIE wpływ przelewu (patrz ADR-005) | `form_order_id`, `course_id`, `amount_snapshot`; `metadata`: `order_flow` (`deferred`/`online`/`unknown`), `invoice_path_type` (`ifirma`/`manual`/`unknown`), `payment_type`, `amount_gross`; idempotencja `event_uuid = invoice_created\|{form_order_id}`; event backoffice (bez sesji/route/path/referrer/device); ZAKAZANE: numer faktury, NIP, nazwy/adresy, dane uczestników, dane/raw iFirma, KSeF, raw request | `full`, `standard`, `light` |

## Eventy JavaScript Formularza

> **Wdrożone w PR B1 (MVP — stan faktyczny).** Endpoint `POST /analytics/client-events` (projekt pnedu)
> przyjmuje wyłącznie poniższe 4 eventy. Pełny kontrakt: [`STAGE_B_CLIENT_TRACKING.md`](./STAGE_B_CLIENT_TRACKING.md).
>
> | Event | Kiedy powstaje | Metadane dozwolone | Tryby |
> |---|---|---|---|
> | `order_form_started` | pierwsza sensowna interakcja z formularzem | `trigger` (whitelist), `price_variant_id` | `full`, `standard`, `light` |
> | `order_form_section_interacted` | pierwsza interakcja z sekcją | `section_key` (whitelist) | `full`, `standard` |
> | `order_form_cta_clicked` | kliknięcie ważnej akcji/CTA | `cta_key` (whitelist) | `full`, `standard` |
> | `order_form_submit_clicked` | kliknięcie submitu po stronie JS (przed backendowym `order_form_submit_attempted`) | — | `full`, `standard`, `light` |
>
> Pozostałe pozycje w tabeli poniżej to **backlog** (B-future), niewdrożony w B1.
>
> **Hardening B1a (2026-06-25):**
> - **Same-origin guard** — żądania z obcego hosta (`Origin`/`Referer` po HOŚCIE) są ciche (`204`), bez zapisu; przy obu pustych nagłówkach: best-effort (nie blokujemy). CSRF-exempt zostaje (wsparcie `sendBeacon`). Nie logujemy URL-i/referrerów; do analityki trafia tylko `referrer_domain`.
> - **`event_uuid` namespacowany serwerowo** — klientowski UUID jest **tylko seedem deduplikacji**; finalny `event_uuid` to deterministyczny UUIDv5 z `client_js|{order_form_session_id}|{event_name}|{client_event_uuid}` (mieści się w `char(36)`). Brak/niepoprawny UUID klienta → serwer generuje własny. `client_event_uuid` nie jest zapisywany do metadata.

| Event | Kiedy powstaje | Metadane dozwolone | Tryby |
|---|---|---|---|
| `order_form_js_loaded` | po załadowaniu JS formularza | `course_id`, `order_form_session_id` | `full`, `standard` |
| `order_form_started` | pierwsza interakcja użytkownika z formularzem | `course_id`, `section_key` | `full`, `standard`, `light` |
| `buyer_type_selected` | wybór typu klienta | `buyer_type` | `full`, `standard` |
| `payment_type_selected` | wybór płatności online/faktury | `payment_type` | `full`, `standard` |
| `payment_gateway_selected` | wybór PayU/Paynow | `payment_gateway` | `full`, `standard` |
| `section_interacted` | pierwsza interakcja w sekcji formularza | `section_key` | `full`, `standard` |
| `field_interacted` | interakcja z polem bez wartości | `field_key`, `section_key`, `field_state` | `full` |
| `cta_clicked` | kliknięcie CTA | `cta_type`, `source_page`, `target_type` | `full`, `standard`, `light` |
| `time_checkpoint` | próg czasu w formularzu | `time_spent_seconds` | `full` |
| `before_unload_form_dirty` | opuszczenie strony po rozpoczęciu formularza | `last_section_key`, `last_field_key`, `time_spent_seconds` | `full`, `standard` |

## Eventy Przyszłych Funkcji Formularza

| Event | Kiedy powstaje | Metadane dozwolone | Tryby |
|---|---|---|---|
| `gus_lookup_started` | start pobierania danych z GUS | `lookup_target` (`buyer`/`recipient`) | `full`, `standard` |
| `gus_lookup_success` | poprawne pobranie danych z GUS | `lookup_target`, `duration_ms_bucket` | `full`, `standard` |
| `gus_lookup_failed` | błąd pobierania danych z GUS | `lookup_target`, `error_group` | `full`, `standard` |
| `gus_autofill_applied` | użytkownik zastosował dane z GUS | `lookup_target`, `fields_count` | `full`, `standard` |
| `invoice_role_option_selected` | wybór roli fakturowej | `invoice_role_option` | `full`, `standard` |
| `ksef_option_selected` | wybór opcji KSeF | `ksef_option_selected` | `full`, `standard` |
| `recipient_section_opened` | użytkownik otworzył/uzupełnia sekcję odbiorcy | `has_recipient` | `full`, `standard` |
| `recipient_data_completed` | sekcja odbiorcy uznana za kompletną | `required_fields_completed` | `full`, `standard` |
| `participant_added` | dodanie kolejnego uczestnika | `participant_count` | `full`, `standard` |
| `participant_removed` | usunięcie uczestnika | `participant_count` | `full`, `standard` |
| `participant_count_changed` | zmiana liczby uczestników | `participant_count`, `participant_count_bucket` | `full`, `standard` |
| `price_recalculated` | przeliczenie ceny | `participant_count`, `price_variant_id`, `amount_snapshot` | `full`, `standard` |
| `saved_billing_profile_used` | użycie zapisanego profilu fakturowego | `profile_source` (`account`/`email`) | `full`, `standard` |
| `saved_billing_profile_created` | zapis profilu fakturowego | `profile_scope` | `full`, `standard` |
| `saved_billing_profile_updated` | aktualizacja profilu fakturowego | `profile_scope` | `full`, `standard` |

## Terminologia Rozliczeń (zafakturowane vs opłacone) — ADR-005

Rozróżniamy dwa źródła prawdy o rozliczeniu zamówienia:

| Pojęcie | Definicja | Źródło prawdy |
|---|---|---|
| `online_paid` | zamówienie online opłacone | event `payment_status_changed` z `payment_status = paid` (bramka PayU/PayNow) |
| `deferred_invoiced` | zamówienie odroczone zafakturowane / rozliczone operacyjnie | pierwsze pojawienie się `form_orders.invoice_number` (przyszły event `invoice_created`) |
| `settled_orders_total` | rozliczone łącznie | `online_paid + deferred_invoiced` |

Metryki przychodu:

- `ordered_revenue_gross` — przychód zamówiony (data `form_order_created`),
- `online_paid_revenue_gross` — przychód opłacony online (data `payment_status_changed: paid`),
- `deferred_invoiced_revenue_gross` — przychód zafakturowany odroczony (data `invoice_created`),
- `settled_revenue_gross` — `online_paid_revenue_gross + deferred_invoiced_revenue_gross`.

Zasady:

- `paid` / „opłacone" rezerwujemy wyłącznie dla ścieżki online. Dla odroczonych używamy `invoiced` / „zafakturowane".
- `invoice_number` (niepuste, ≠ `''`, ≠ `'0'`) = zafakturowane, NIE wpływ przelewu.
- Pro-forma NIE ustawia `invoice_number` (trafia do `notes`) — nie jest rozliczeniem.
- Edge case: online z późniejszą fakturą liczymy raz (jako `online_paid`), faktura to tylko znacznik księgowy.
- Historyczny alias `orders_paid` (w `CourseFunnelStatsService`) faktycznie liczy zafakturowane = `orders_invoiced`. Alias zostaje dla kompatybilności, ale w NOWYCH agregatach/dashboardach nie używamy nazwy `paid` dla odroczonych (preferowane: `orders_invoiced` / `deferred_invoiced`).
- Ścieżkę odroczoną rozpoznajemy po jednoznacznym polu `FormOrder` (`payment_mode` / `order_flow`), a NIE po braku `OnlinePaymentOrder`.

Cel metryk przychodu: `ordered_revenue_gross` mierzy sprzedaż z kampanii; `settled_revenue_gross` mierzy przychód rozliczony operacyjnie.

Poza zakresem obecnego modelu (NIE teraz; osobne przyszłe eventy/decyzje): rekonsyliacja przelewów bankowych, częściowe płatności, zaliczki, faktury zaliczkowe, wiele faktur do jednego zamówienia, korekty/anulowanie faktur, zwroty online, chargebacki, ręczne korekty statusów, rozliczenia mieszane, KSeF, szczegółowe dane księgowe. Poglądowe przyszłe eventy: `bank_payment_confirmed`, `invoice_corrected`, `invoice_cancelled`, `refund_created`, `chargeback_received`.

Szczegóły: `docs/decisions/ADR-005-invoice-number-means-invoiced-not-paid.md`.

## Kategorie Eventów

Zalecane wartości `event_category`:

- `campaign`,
- `landing`,
- `order_form`,
- `validation`,
- `conversion`,
- `payment`,
- `invoice`,
- `ab_test`,
- `system`.

## Tryby Analityki A Eventy

| Tryb | Eventy |
|---|---|
| `full` | wszystkie eventy bez danych osobowych |
| `standard` | backend krytyczny + podstawowe JS formularza |
| `light` | wejścia, start formularza, submit, zamówienie, płatność |
| `aggregate_only` | tylko liczniki dzienne, bez raw sesji formularza |
| `off` | brak eventów analitycznych |

## Plan Etapu 1 — Do Implementacji

Data dopisania: 2026-06-24.  
Status: Etap 1A wdrożony lokalnie; Etap 1B-1 wdrożony lokalnie; Etap 1B-2 wdrożony lokalnie; Etap 1C wdrożony lokalnie w `adm.pnedu.pl`; dalsze eventy płatności/faktur do implementacji.

### Etap 1A — Kampanie I Wejścia

Eventy w pierwszym kroku:

- `campaign_short_link_visit`,
- `campaign_redirect_resolved`,
- `utm_captured`,
- `course_description_viewed`,
- `order_form_viewed`.

Status: wdrożone lokalnie w `pnedu.pl`.

Minimalne bezpieczne pola:

- `event_name`,
- `event_category`,
- `analytics_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_id`,
- `campaign_code`,
- `landing_target`,
- `utm_source`,
- `utm_medium`,
- `utm_campaign`,
- `utm_content`,
- `utm_term`,
- `order_form_session_id` dla formularza,
- `route_name`,
- `path`,
- `referrer_domain`,
- `device_type`,
- `browser_family`,
- `occurred_at`,
- `metadata` po sanitizerze.

### Etap 1B — Formularz I Zamówienie

Eventy w Etapie 1B-1:

- `order_form_submit_attempted`,
- `order_form_validation_failed`.

Status: wdrożone lokalnie w `pnedu.pl`.

Event w Etapie 1B-2:

- `form_order_created`.

Status: wdrożone lokalnie w `pnedu.pl`.

Minimalne bezpieczne pola:

- `analytics_session_id`,
- `order_form_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_code`,
- `form_order_id` tylko po sukcesie zapisu,
- `metadata.validation_context`,
- `metadata.error_count`,
- `metadata.field_keys`,
- `metadata.section_keys`,
- `metadata.error_codes`,
- `metadata.has_price_variant`,
- `metadata.price_variant_id`,
- `metadata.order_form_session_created_on_submit`,
- `metadata.order_flow`,
- `metadata.payment_type`,
- `metadata.participant_count`,
- `metadata.has_recipient`,
- `metadata.buyer_type`,
- `metadata.amount_gross`,
- `metadata.form_order_status`,
- `route_name`,
- `path`,
- `referrer_domain`.

### Etap 1C — Agregaty Dzienne

Status: wdrożone lokalnie w `adm.pnedu.pl`.

Komenda: `analytics:aggregate-daily` (domyślnie: wczoraj, `Europe/Warsaw`).

Mapowanie do `analytics_daily_course_stats`:

| Event | Kolumna docelowa |
|-------|------------------|
| `course_description_viewed` | `views_course_description` |
| `order_form_viewed` | `views_order_form` |
| `order_form_submit_attempted` | `submit_attempts` |
| `order_form_validation_failed` | `validation_failures` |
| `form_order_created` | `orders_created` |
| `form_order_created` → `metadata.amount_gross` | `revenue_snapshot` (suma) |

Uwaga: `submit_attempts` oznacza próby wysłania formularza (`order_form_submit_attempted`), a nie liczbę utworzonych zamówień.

Mapowanie do `analytics_daily_campaign_stats` (tylko eventy z `campaign_code`):

| Event | Kolumna docelowa |
|-------|------------------|
| `campaign_short_link_visit` | `link_entries` |
| `course_description_viewed` | `course_description_views` |
| `order_form_viewed` | `order_form_views` |
| `order_form_submit_attempted` | `submit_attempts` |
| `order_form_validation_failed` | `validation_failures` |
| `form_order_created` | `orders_created` |
| `form_order_created` → `metadata.amount_gross` | `revenue_snapshot` (suma) |

Eventy `campaign_redirect_resolved` i `utm_captured` nie mają dedykowanych kolumn w schemacie MVP — pomijane w 1C.

### Eventy Poza Zakresem Etapu 1

Nie wdrażać w Etapie 1:

- `online_payment_selected`,
- `deferred_invoice_selected`,
- `payment_order_created`,
- `payment_status_changed`,
- `invoice_created`.

Powód: dotyczą płatności, faktur lub ścieżek finansowych. Wymagały osobnego planu i testów regresji.

Status: wdrożone w Etapie 2 — `online_payment_selected`, `deferred_invoice_selected`, `payment_order_created`, `payment_status_changed` (w `pnedu`) oraz `invoice_created` (w `pneadm`, Etap 2C-1).

### Dodatkowe Zasady RODO Dla Etapu 1

- Nie zapisywać raw `url`.
- Nie zapisywać raw `referrer`.
- Nie zapisywać raw request.
- Nie zapisywać raw input formularza.
- `form_order_id` może być zapisany w `pne_analytics`, ale nie może trafiać do eksportów AI-safe.
- `form_order_id` nie może trafiać do raportów przekazywanych zewnętrznemu AI.
- `metadata` musi przejść przez `AnalyticsPayloadSanitizer`.
- Błędy walidacji zapisywać jako `field_keys`, `error_codes`, `section_keys`, bez wartości pól i bez komunikatów błędów.

### Faktyczne Payloady Etapu 1B-1

`order_form_submit_attempted`:

- `analytics_session_id`,
- `order_form_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_code`, jeśli dostępny,
- UTM, jeśli dostępne,
- `route_name`,
- `path`,
- `referrer_domain`,
- `device_type`,
- `browser_family`,
- `metadata.has_price_variant`,
- `metadata.price_variant_id`, jeśli dostępny,
- `metadata.order_form_session_created_on_submit`.

`order_form_validation_failed`:

- pola kontekstowe jak w `order_form_submit_attempted`,
- `metadata.validation_context`,
- `metadata.error_count`,
- `metadata.field_keys`,
- `metadata.section_keys`,
- `metadata.error_codes`.

`error_codes` zawierają wyłącznie techniczne nazwy reguł walidacyjnych, np. `required`, `email`, `integer`, albo ogólne `validation_failed` dla ręcznych `withErrors()`. Nie zapisujemy komunikatów błędów ani wartości pól.

### Faktyczne Payloady Etapu 1A

`campaign_short_link_visit`:

- `analytics_session_id`,
- `campaign_id`,
- `campaign_code`,
- `course_id`,
- `course_title_snapshot`,
- `landing_target`,
- `campaign_channel`,
- `utm_source`,
- `utm_medium`,
- `utm_campaign`,
- `utm_content`,
- `route_name`,
- `path`,
- `referrer_domain`,
- `device_type`,
- `browser_family`.

`campaign_redirect_resolved`:

- jak wyżej,
- bez raw URL,
- docelowa ścieżka reprezentowana przez `landing_target`.

`utm_captured`:

- `analytics_session_id`,
- `campaign_code`,
- `utm_source`,
- `utm_medium`,
- `utm_campaign`,
- `utm_content`,
- `utm_term`,
- `route_name`,
- `path`,
- `referrer_domain`.

`course_description_viewed`:

- `analytics_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_code`,
- `landing_target=course_description`,
- `utm_*`,
- `route_name`,
- `path`,
- `referrer_domain`,
- `device_type`,
- `browser_family`.

`order_form_viewed`:

- `analytics_session_id`,
- `order_form_session_id`,
- `course_id`,
- `course_title_snapshot`,
- `campaign_code`,
- `landing_target=order_form_direct`,
- `utm_*`,
- `route_name`,
- `path`,
- `referrer_domain`,
- `metadata.price_variant_id`, jeśli dostępne i bezpieczne.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalne payloady JSON dla każdego eventu.
- Dopisać wersjonowanie taksonomii eventów.
- Dopisać eventy faktycznie wdrożone w kodzie.
- Dopisać testy walidujące brak danych osobowych w eventach.

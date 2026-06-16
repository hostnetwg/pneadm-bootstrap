# Marketing — kampanie, UTM, lejek konwersji

Dokumentacja dla developerów **pneadm** (adm) i **pnedu** (front). Opisuje stan wdrożenia, konwencje UTM oraz plan rozwoju analityki (GA4 + wewnętrzny lejek).

Powiązane projekty: zobacz też [.ai/MULTI_PROJECT_CONTEXT.md](../.ai/MULTI_PROJECT_CONTEXT.md).

---

## 1. Do czego dążymy

### Cel biznesowy

Mierzyć skuteczność kampanii marketingowych w jednym, spójnym modelu:

1. **Ruch** — skąd przyszedł użytkownik (UTM / legacy `fb`).
2. **Zachowanie** — wejście na opis szkolenia, formularz zamówienia.
3. **Konwersja operacyjna** — złożone zamówienie (`form_orders`).
4. **Sukces biznesowy (adm)** — zamówienie z wypełnionym `invoice_number` (wystawiona faktura), **nie** status „zakończone” ani `payment_status`.

### Dwa kanały analityki (komplementarne)

| Kanał | Odpowiada na | Status |
|-------|----------------|--------|
| **Wewnętrzny lejek (adm)** | Ile zamówień / faktur przypisać do kampanii `1240`? | Wdrożony |
| **Google Analytics 4** | Skąd ruch, lejek w GA, porównanie kanałów | Przygotowany (Consent Mode, eventy częściowe); pełne KPI — roadmap |
| **Meta Pixel** | Remarketing / konwersje FB Ads | Wyłączony na pnedu (do decyzji) |

**Zasada:** adm = źródło prawdy dla **sprzedaży i faktur**. GA = źródło prawdy dla **ruchu i UX** (z uwzględnieniem zgody cookies).

### Konwencja linków (od 2026)

- **Nowe materiały:** link z generatora w adm (`Marketing → Kampanie`).
- **YouTube / Facebook / SMS:** preferuj **link krótki** `https://pnedu.pl/l/{campaign_code}` — po kliknięciu przekierowanie 302 na pełny URL z UTM.
- **Google Analytics / precyzyjna atrybucja:** pełny link **UTM** (ten sam efekt po przekierowaniu z `/l/`).
- **Legacy `?fb=`:** tylko stare publikacje; technicznie nadal działa (`fb_source` = kod kampanii).
- **Jeden kod kampanii** = `utm_campaign` = `campaign_code` = segment `/l/{code}` = historyczne `fb`.

Przykład linku krótkiego (social media):

```
https://pnedu.pl/l/1241
```

Przykład pełnego linku UTM:

```
https://pnedu.pl/courses/520/order-form?utm_source=newsletter&utm_medium=email&utm_campaign=1240
```

Implementacja przekierowania: `pnedu` → `GET /l/{campaign_code}` (`MarketingCampaignShortLinkController`), lookup kampanii w bazie `pneadm`.

**Nie używaj** adresu e-mail jako `utm_source` (np. `szkolenia@news.pnedu.pl`). Source = **platforma** (`newsletter`, `facebook`, `pnedu`). Adres nadawcy opisuj w **nazwie typu źródła** w adm.

---

## 2. Architektura

```
┌─────────────────────────────────────────────────────────────────┐
│  adm (pneadm)                                                   │
│  marketing_source_types → marketing_campaigns → generator URL   │
│  CourseFunnelStatsService, MarketingFunnelController            │
│  form_orders.fb_source ↔ marketing_campaigns.campaign_code      │
│  course_page_stats_daily (agregaty wejść)                       │
└────────────────────────────┬────────────────────────────────────┘
                             │ baza pneadm
┌────────────────────────────▼────────────────────────────────────┐
│  pnedu.pl (pnedu)                                               │
│  CaptureMarketingSource (middleware global) — cookie 7 dni        │
│  TrackCoursePageView — wejścia opis / formularz                 │
│  MarketingAttributionService — UTM + fb → sesja/cookie          │
│  formularz: hidden fb_source → zapis do form_orders             │
│  GA4: course_view, order_form_view (+ campaign_id jeśli gtag)  │
└─────────────────────────────────────────────────────────────────┘
```

### Kluczowe tabele (baza `pneadm`)

| Tabela | Rola |
|--------|------|
| `marketing_source_types` | Kanał → domyślne `utm_source`, `default_utm_medium` |
| `marketing_campaigns` | Kampania: `campaign_code`, `course_id`, `landing_target`, opcjonalne `utm_medium` |
| `form_orders` | `fb_source` = kod kampanii (legacy nazwa pola); `conversion_placement` = miejsce konwersji (np. panel klienta) |
| `course_page_stats_daily` | `views_course_show`, `views_order_form` per dzień/kurs |

### Konfiguracja

| Plik | Zmienne |
|------|---------|
| `pneadm/config/marketing.php` | `PNEDU_PUBLIC_URL`, `MARKETING_ATTRIBUTION_DAYS`, `MARKETING_FUNNEL_STATS_DAYS`, `utm_medium_options` |
| `pnedu/config/marketing.php` | `attribution_days`, `cookie_name`, `funnel_session_cookie` |
| `pnedu/.env` | `GOOGLE_TAG_MANAGER_ID`, `GOOGLE_ANALYTICS_ID`, `MARKETING_ATTRIBUTION_DAYS` |

---

## 3. Parametry UTM — mapowanie na pola adm

| Parametr URL | Skąd bierze wartość | Gdzie ustawić |
|--------------|---------------------|---------------|
| `utm_source` | `marketing_source_types.utm_source` (lub mapowanie ze `slug`) | Typ źródła |
| `utm_medium` | `marketing_campaigns.utm_medium` (tylko gdy zaznaczone „niestandardowe”) lub `default_utm_medium` typu | Typ źródła; override w kampanii — zaawansowane |
| `utm_campaign` | `marketing_campaigns.campaign_code` | Kod kampanii |
| `utm_content` | `marketing_campaigns.utm_content` lub `marketing_source_types.default_utm_content` | Typ źródła (domyślnie); override w kampanii |
| `utm_term` | — | Głównie Google Ads (`gclid`) |

Generator: `App\Services\MarketingCampaignUrlBuilder`.

Atrybucja na froncie: `pnedu/app/Services/MarketingAttributionService.php` — priorytet query: `utm_campaign` → `fb` → `fb_source`; okno **7 dni** (cookie `pne_marketing` + sesja).

**Miejsce konwersji (osobno od kampanii):** linki z panelu klienta (sidebar „Aktualna oferta”) używają `?entry=dashboard_sidebar` — zapis w sesji (`OrderEntryPlacementService`), bez nadpisywania `fb_source`. Pole `form_orders.conversion_placement`; filtr i statystyki na `/form-orders` w adm.

---

## 4. Lejek wewnętrzny (adm)

| Etap | Metryka | Definicja |
|------|---------|-----------|
| Wejście na opis | `views_course_show` | GET `/courses/{id}`, max 1×/gość/kurs/dzień, bez botów |
| Wejście na formularz | `views_order_form` | GET `order-form` + `deferred-order`, ten sam licznik |
| Złożone | `orders_submitted` | Rekord w `form_orders` w okresie; **bez** `status_completed = 1` przy braku faktury |
| Z fakturą | `orders_invoiced` | `invoice_number` wypełnione (jak `FormOrder::scopeWithInvoice()`) |

**Wykluczone z 🛒:** zamówienie oznaczone jako zakończone (`status_completed = 1`) i jednocześnie bez numeru faktury — np. rezygnacja zamknięta w panelu (spójnie z badge „niewprowadzone” = 0).

Serwis: `App\Services\CourseFunnelStatsService`.  
UI: `Marketing → Lejek konwersji`, kolumna „Lejek” na `/courses`.

---

## 5. Typy źródeł — audyt i rekomendacje

Stan po migracji `2026_06_16_100001_normalize_marketing_source_type_utm_values.php`.

| ID | Nazwa | Slug | utm_source | utm_medium | utm_content | Kampanie | Zamówienia | Akcja |
|----|-------|------|------------|------------|-------------|----------|------------|-------|
| 1 | Facebook | `facebook` | `facebook` | `paid` | `prospecting` | 427 | 1289 | **Zostaw** — płatny ruch zimny |
| 2 | Email (kontakt@nowoczesna-edukacja.pl) | `900` | `newsletter` | `email` | — | 166 | 1276 | **Zostaw**; slug legacy — nie zmieniać |
| 3 | Website | `website` | `pnedu` | `banner` | — | 10 | 13 | **Zostaw** — banery / landing na stronie |
| 4 | Remarketing | `remarketing` | `facebook` | `paid` | `remarketing` | 11 | 0 | **Zostaw** — retargeting Meta |
| 5 | Training | `training` | `pnedu` | `webinar` | `webinar` | 5 | 0 | **Zostaw** |
| 6 | Organic | `organic` | `facebook` | `social` | `organic` | 3 | 33 | **Zostaw** — posty organiczne FB |
| 7 | Email (waldemar@zdalna-lekcja.pl) | `email` | `newsletter` | `email` | — | 34 | 40 | **Zostaw** — Sendy NAUCZYCIELE |
| 8 | tiktok | `tiktok` | `tiktok` | `paid` | `prospecting` | 3 | 0 | **Zostaw** |
| 9 | Email (kontakt@pnedu.pl) | `kontakt@pnedu.pl` | `newsletter` | `email` | — | 10 | 62 | **Zostaw** |
| 10 | szkolenia@news.pnedu.pl | `szkolenia@news.pnedu.pl` | `newsletter` | `email` | — | 20 | 76 | **Zostaw** — główny newsletter PNE |
| 11 | Oferta na pnedu.pl | `oferta-na-pnedu-pl` | `pnedu` | `referral` | — | 0 | 0 | **Wyłączony** (`is_active=0`) — duplikat Website |
| — | YouTube — opisy i wydarzenia | `youtube` | `youtube` | `social` | `video-description` | 0 | 0 | **Dodany** — migracja `2026_06_18_100002_*` |

### Zasady dodawania nowych typów

1. **Nazwa** — czytelna dla operatora (może zawierać adres e-mail nadawcy).
2. **`utm_source`** — krótka platforma: `newsletter`, `facebook`, `google`, `tiktok`, `pnedu` (wymagane dla spójności GA).
3. **`default_utm_medium`** — z listy `config/marketing.php` → `utm_medium_options`.
4. **`default_utm_content`** — taktyka w GA4 (np. `prospecting`, `remarketing`); opcjonalnie, per typ źródła.
5. **`slug`** — stabilny identyfikator; **nie zmieniać** po utworzeniu kampanii.
6. Nieużywane typy → **dezaktywuj** (`is_active`), nie usuwaj (historia kampanii).

### Docelowy minimalny zestaw aktywnych (propozycja)

- Facebook — reklamy (`facebook` / `paid`)
- Facebook — organic (`facebook` / `social`)
- Newsletter — wszystkie listy e-mail (`newsletter` / `email`) — ewentualnie jeden typ + rozróżnienie w nazwie kampanii
- Strona pnedu.pl (`pnedu` / `banner` lub `referral`)
- Webinar / szkolenie na żywo (`pnedu` / `webinar`)
- Remarketing Meta (`facebook` / `paid` + domyślne `utm_content=remarketing`)
- TikTok (`tiktok` / `paid`)
- YouTube — opisy i wydarzenia (`youtube` / `social` + domyślne `utm_content=video-description`)
- Google Ads (`google` / `cpc`) — **dodać**, gdy ruszą kampanie

---

## 6. Przepływ pracy operatora

1. **Marketing → Typy źródeł** — utrzymuj poprawne UTM (lista + legenda).
2. **Marketing → Kampanie** — utwórz kampanię: typ źródła, szkolenie, strona docelowa (opis vs formularz).
3. Zapisz → **podgląd kampanii** z linkiem UTM do skopiowania (lub edycja / lista kampanii).
4. Wklej link UTM w newsletter / reklamę / post.
5. **Marketing → Lejek konwersji** — porównaj wejścia vs zamówienia vs faktury per kampania.

---

## 7. Google Analytics 4 — roadmap (niepełne wdrożenie)

### Co jest

- `pnedu/resources/views/layouts/analytics-head.blade.php` — GTM agencji + opcjonalne własne GA4, Consent Mode v2.
- Eventy: `course_view`, `order_form_view` z `course_id`, opcjonalnie `campaign_id` (`marketing-ga-event.blade.php`).
- Meta Pixel — kod gotowy, **wyłączony** w layoutach.

### Co dodać (kolejność)

1. Ustawić `GOOGLE_ANALYTICS_ID` na produkcji; uzgodnić z agencją rolę GTM vs własnego GA4.
2. Event konwersji przy **wysłaniu zamówienia** (`generate_lead` lub custom) z `utm_campaign`, `course_id`.
3. Raporty GA: Acquisition → kampanie po `utm_campaign` (zgodne z `campaign_code`).
4. Opcjonalnie: pole `utm_content` w kampanii + generator — **wdrożone**.
5. Opcjonalnie: włączenie Meta Pixel z tym samym `campaign_code`.

### Porównanie GA vs adm

| Pytanie | GA4 | adm |
|---------|-----|-----|
| Skąd ruch? | Tak | Tylko przez przypisaną kampanię |
| Odrzucenia cookies? | Tak | Nie (własny licznik bez GA) |
| Zamówienia / faktury? | Nie (bez eventu konwersji) | Tak |
| Okno atrybucji | Sesja GA / model GA | 7 dni last-touch |

---

## 8. Pliki — szybka nawigacja

### pneadm

| Obszar | Pliki |
|--------|-------|
| Modele | `app/Models/MarketingCampaign.php`, `MarketingSourceType.php`, `CoursePageStatsDaily.php`, `FormOrder.php` |
| URL | `app/Services/MarketingCampaignUrlBuilder.php` |
| Lejek | `app/Services/CourseFunnelStatsService.php`, `Http/Controllers/MarketingFunnelController.php` |
| Kampanie | `Http/Controllers/MarketingCampaignController.php` |
| Typy źródeł | `Http/Controllers/MarketingSourceTypeController.php` |
| Widoki | `resources/views/marketing-campaigns/`, `marketing-source-types/`, `marketing-funnel/` |
| Config | `config/marketing.php` |
| Migracje | `database/migrations/2026_06_02_*`, `2026_06_16_100001_*` |

### pnedu

| Obszar | Pliki |
|--------|-------|
| Atrybucja | `app/Services/MarketingAttributionService.php` |
| Wejścia | `app/Services/CoursePageViewTracker.php`, `Http/Middleware/TrackCoursePageView.php` |
| Cookie UTM | `Http/Middleware/CaptureMarketingSource.php` |
| Zamówienie | `Http/Controllers/CourseController.php` (`resolveFbSourceForFormOrder`) |
| GA eventy | `resources/views/courses/partials/marketing-ga-event.blade.php` |
| Analytics | `resources/views/layouts/analytics-head.blade.php` |

---

## 9. Rozszerzanie systemu — wskazówki dla developera

### Nowa wartość `utm_medium`

1. Dopisz do `pneadm/config/marketing.php` → `utm_medium_options`.
2. Ten sam klucz w formularzach typów źródeł i kampanii (czytają z config).

### Nowe pole w kampanii (np. `utm_content`)

1. Migracja w **pneadm** (`marketing_campaigns`).
2. `MarketingCampaignUrlBuilder::buildForCampaign()` — dodać do query string.
3. Formularze create/edit + walidacja w `MarketingCampaignController`.
4. Opcjonalnie: `MarketingAttributionService` na pnedu, jeśli ma trafiać do cookie.

### Zmiana definicji „sukcesu” w lejku

Edytuj `CourseFunnelStatsService::orderCountsForCourses()` — faktura: `invoicePresentSql()`, 🛒: `operationalSubmittedOrderSql()`.  
**Nie** mieszaj z `status_completed` bez wyraźnej decyzji biznesowej (patrz sekcja 4).

### Testy lokalne

- adm: `http://adm.localhost:8083/marketing-campaigns`, `/marketing-source-types`, `/marketing-funnel`
- pnedu: wejście z `?utm_source=newsletter&utm_medium=email&utm_campaign=TEST`
- Sprawdź `form_orders.fb_source` po złożeniu zamówienia testowego.

---

## 10. Changelog dokumentacji

| Data | Zmiana |
|------|--------|
| 2026-06-16 | Pierwsza wersja: audyt typów źródeł, migracja UTM, legenda w UI, roadmap GA4 |
| 2026-06-18 | Domyślne `utm_content` per typ źródła (prospecting/remarketing/organic); auto-uzupełnianie w kampanii |
| 2026-06-18 | Typ źródła YouTube (`youtube` / `social` / `video-description`) |
| 2026-06-16 | Link krótki `pnedu.pl/l/{campaign_code}` — przekierowanie 302 na pełny URL z UTM; wariant w generatorze + test w adm |

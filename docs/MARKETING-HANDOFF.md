# Marketing — handoff dla developera (kontynuacja bez historii chatów AI)

**Ostatnia aktualizacja:** 2026-06-19  
**Projekty:** `pneadm` (adm) + `pnedu` (front)  
**Baza danych:** wspólna `pneadm` dla kampanii, zamówień, statystyk

Ten dokument zbiera **cały kontekst z sesji czerwiec 2026** (opt-out analityki, wejścia z linków kampanii, filtry okresu). Czytaj go na nowym laptopie zamiast historii czatów AI.

Pełna dokumentacja techniczna: **[MARKETING.md](./MARKETING.md)**.

---

## 1. Szybki start na nowym laptopu

```bash
# pneadm (adm) — port 8083
cd /path/to/pneadm
sail up -d
sail artisan migrate
# .env: MARKETING_FUNNEL_SKIP_TOKEN, PNEDU_PUBLIC_URL=http://localhost:8081

# pnedu (front) — port 8081
cd /path/to/pnedu
sail up -d
sail artisan migrate   # tylko migracje bazy pnedu (users itd.)
# .env: MARKETING_FUNNEL_SKIP_TOKEN (ten sam token co adm!), APP_URL=http://localhost:8081
```

**Adresy dev:**

| Usługa | URL |
|--------|-----|
| adm | http://adm.localhost:8083 (preferuj ten host, nie `localhost:8083`) |
| pnedu | http://localhost:8081 |
| Kampanie | http://adm.localhost:8083/marketing-campaigns |
| Analityka (opt-out) | http://adm.localhost:8083/settings/analityka |
| Lejek | http://adm.localhost:8083/marketing-funnel |

**Zasada migracji:** tabele w bazie `pneadm` → migracje w **pneadm**; tabele w `pnedu` → migracje w **pnedu**.

---

## 2. Trzy rodzaje metryk (NIE mylić!)

| Metryka | Gdzie w adm | Tabela / źródło | Co liczy |
|---------|-------------|-----------------|----------|
| **Wejś. (kampania)** | Lista kampanii → kolumna **Wejś.** | `marketing_campaign_stats_daily` | Klik w **link kampanii** (`utm_campaign` / `fb` / `/l/{kod}`), max 1× gość/kampania/dzień |
| **Lejek (opis/formularz)** | `/courses` kolumna Lejek, `/marketing-funnel` | `course_page_stats_daily` | Wejście na **opis** lub **formularz** danego kursu (bez rozróżnienia kampanii) |
| **Zam. (kampania)** | Lista kampanii → kolumna **Zam.** | `form_orders.fb_source` | Zamówienia z kodem kampanii (logika operacyjna jak w lejku) |

**Wejścia z linku ≠ wejścia na opis szkolenia.** To osobne systemy celowo.

---

## 3. Co zostało zbudowane (czerwiec 2026)

### 3.1 Opt-out lejka i GA dla zespołu

- **UI:** Ustawienia → **Analityka** (`/settings/analityka`) — dwa niezależne przełączniki: **Lejek** i **Google Analytics / GTM**
- **Cookie:** `pne_skip_funnel`, `pne_skip_analytics` (+ `pne_skip_funnel_until` pomocnicze)
- **Token:** `MARKETING_FUNNEL_SKIP_TOKEN` w `.env` **obu** projektów (musi być identyczny)
- **Wyłączenie trwa do ręcznego ON** — cookie odnawiane przy każdej wizycie (`RefreshFunnelSkipOptOutCookies` middleware)
- **Przepływ przycisku:** adm → pnedu (`?pne_skip_*&token=…&adm_return=…`) → powrót na `/settings/analityka`
- **Dev:** używaj `adm.localhost:8083`; `return URL` budowany z bieżącego hosta żądania
- **Pułapka dev:** cookie opt-out **blokuje też** zliczanie wejść z linków kampanii — przed testem Wejś. ustaw **Lejek → ON**

### 3.2 Wejścia z linku kampanii (kolumna Wejś.)

- **Migracja:** `2026_06_17_120001_create_marketing_campaign_stats_daily_table.php` (pneadm)
- **Zliczanie (pnedu):** `MarketingCampaignLinkTracker` + middleware `CaptureMarketingSource` + skrócony link `/l/{code}`
- **Wyświetlanie (adm):** kolumna **Wejś.** na `/marketing-campaigns`
- **Deduplikacja:** max 1× gość / kampania / dzień (cache + cookie `pne_funnel_sid`)
- **Dane historyczne:** tylko od wdrożenia — starsze kliknięcia nie są backfillowane

### 3.3 Filtr okresu na liście kampanii (wariant 2)

- Presety: **Cała historia**, Dziś, Wczoraj, 7 dni, 30 dni, własny zakres dat
- Kolumny **Wejś. (okres)** i **Zam. (okres)** gdy wybrany przedział
- Przełącznik **Wejścia | Zamówienia** — domyślne sortowanie w trybie okresu
- Checkbox **„Tylko kampanie z aktywnością w okresie”** (wg wybranej metryki)
- Pasek podsumowania: suma wejść i zamówień w okresie (wg filtrów listy)
- **Serwis:** `MarketingCampaignStatsService`

### 3.4 Poprawki błędów

- Sortowanie **Wejś.** — naprawione (subquery zamiast nieistniejącego `orderBySum()`; bez `select()` nadpisującego `withSum`)
- Przełączniki analityki na dev — middleware odświeżania nie może nadpisywać `forget` cookie przy toggle
- Wielokampaniowe kliknięcia tego samego użytkownika — działają (osobny klucz cache per `campaign_code`)

### 3.5 Świadoma decyzja (bez zmian w kodzie)

- Badge **„nieznana kampania”** przy zamówieniu (`fb_source` bez dopasowania w `marketing_campaigns`) — np. stare linki, ID Meta zamiast kodu kampanii. **Zostawione jak jest.**

---

## 4. Kluczowe pliki

### pneadm

| Plik | Opis |
|------|------|
| `app/Services/MarketingCampaignStatsService.php` | Okres, agregaty Wejś./Zam., filtr aktywności |
| `app/Services/MarketingCampaignUrlBuilder.php` | Generator linków UTM + `/l/` |
| `app/Services/CourseFunnelStatsService.php` | Lejek per kurs i per kampania (okres) |
| `app/Services/FunnelSkipService.php` | Cookie opt-out, URL-e toggle |
| `app/Http/Controllers/MarketingCampaignController.php` | Lista kampanii + filtry okresu |
| `app/Http/Controllers/Settings/PneduPurchasesController.php` | `analytics()`, `funnelSkipToggle()` |
| `app/Http/Middleware/RefreshFunnelSkipOptOutCookies.php` | Odnawianie cookie opt-out (z wyjątkiem toggle) |
| `app/Models/MarketingCampaignStatsDaily.php` | Model statystyk wejść |
| `resources/views/marketing-campaigns/index.blade.php` | UI listy + filtry okresu |
| `resources/views/settings/analytics.blade.php` | Przełączniki Lejek / GA |
| `config/marketing.php` | URL-e, token, cookie domain |
| `database/migrations/2026_06_17_120001_*` | Tabela `marketing_campaign_stats_daily` |
| `tests/Unit/MarketingCampaignStatsServiceTest.php` | Testy okresów |
| `tests/Unit/RefreshFunnelSkipOptOutCookiesTest.php` | Test toggle vs renewal |

### pnedu

| Plik | Opis |
|------|------|
| `app/Services/MarketingCampaignLinkTracker.php` | Zliczanie kliknięć w link kampanii |
| `app/Services/MarketingAttributionService.php` | UTM → sesja/cookie |
| `app/Services/FunnelSkipService.php` | Opt-out (lustrzany do adm) |
| `app/Services/CoursePageViewTracker.php` | Lejek opis/formularz |
| `app/Http/Middleware/CaptureMarketingSource.php` | Atrybucja + wywołanie link trackera |
| `app/Http/Middleware/CaptureFunnelSkipOptOut.php` | Obsługa `?pne_skip_*&token=` |
| `app/Http/Middleware/RefreshFunnelSkipOptOutCookies.php` | Odnawianie cookie opt-out |
| `app/Http/Controllers/MarketingCampaignShortLinkController.php` | `/l/{campaign_code}` |
| `app/Models/MarketingCampaignStatsDaily.php` | Zapis do pneadm |
| `tests/Feature/MarketingCampaignLinkTrackerTest.php` | Testy wejść z linku |
| `tests/Unit/RefreshFunnelSkipOptOutCookiesTest.php` | Test renewal |

---

## 5. Zmienne `.env`

### Oba projekty (identyczne wartości)

```env
MARKETING_FUNNEL_SKIP_TOKEN=...   # długi losowy sekret
```

### pneadm

```env
PNEDU_PUBLIC_URL=http://localhost:8081          # dev
# produkcja: https://pnedu.pl
MARKETING_FUNNEL_SKIP_COOKIE_DOMAIN=.pnedu.pl  # tylko prod; na dev zostaw puste
```

### pnedu

```env
APP_URL=http://localhost:8081
MARKETING_FUNNEL_SKIP_COOKIE_DAYS=365
# services.pneadm.public_url — host adm do adm_return (np. adm.localhost w dev)
```

---

## 6. Testy

```bash
# pneadm
cd pneadm && sail artisan test --filter=MarketingCampaignStatsServiceTest
cd pneadm && sail artisan test --filter=RefreshFunnelSkipOptOutCookiesTest

# pnedu
cd pnedu && sail artisan test --filter=MarketingCampaignLinkTrackerTest
cd pnedu && sail artisan test --filter=RefreshFunnelSkipOptOutCookiesTest
cd pnedu && sail artisan test --filter=FunnelSkipOptOutTest
cd pnedu && sail artisan test --filter=MarketingAnalyticsOptOutTest
```

**Ręczny test Wejś. na dev:**

1. Ustawienia → Analityka → **Lejek ON**
2. Otwórz link kampanii z adm (localhost:8081) w nowej karcie
3. Odśwież `/marketing-campaigns` — kolumna Wejś. +1
4. Druga kampania — osobny link — druga kampania też +1

---

## 7. Deploy na produkcję (checklist)

- [ ] `git pull` na **pnedu** i **pneadm**
- [ ] `sail artisan migrate` na **pneadm** (tabela `marketing_campaign_stats_daily`)
- [ ] `MARKETING_FUNNEL_SKIP_TOKEN` w `.env` obu projektów
- [ ] `sail artisan optimize:clear` / restart queue jeśli dotyczy
- [ ] Sprawdź `/settings/analityka` — przełączniki ON/OFF
- [ ] Sprawdź `/marketing-campaigns` — sortowanie Wejś., filtr 7 dni
- [ ] Klik testowy w link kampanii na prod → Wejś. +1

---

## 8. Pułapki dev / FAQ

| Problem | Przyczyna | Rozwiązanie |
|---------|----------|-------------|
| Przełączniki analityki zawsze czerwone OFF | Middleware renewal nadpisywał `forget` | Naprawione; wyczyść cookie `pne_skip_*` lub incognito |
| Wejś. nie rośnie po kliknięciu | Opt-out lejka włączony | Lejek → ON na `/settings/analityka` |
| Wejś. na adm, klik na pnedu.pl | `PNEDU_PUBLIC_URL` wskazuje prod | Ustaw `http://localhost:8081` w dev |
| Drugie kliknięcie innej kampanii = 0 | Brak `utm_campaign` w URL (nawigacja wewnętrzna) | Testuj pełny link z adm, nie sam opis kursu |
| Sortowanie Wejś. = 500 | Stary `orderBySum()` | Już naprawione subquery |
| „Nieznana kampania” na zamówieniu | `fb_source` spoza listy kampanii | Zamierzone; nie wymaga akcji |

---

## 9. Pomysły na dalej (NIE zrobione)

- Eksport CSV kampanii z okresem
- CR% Wejś. → Zam. per kampania na liście
- Wykres dzienny na stronie szczegółów kampanii
- Kolumna Wejś. w tabeli kampanii na `/marketing-funnel`
- Backfill historycznych wejść (brak danych sprzed wdrożenia)
- Filtr „tylko z aktywnością” dla obu metryk jednocześnie (OR) — dziś checkbox filtruje wg wybranej metryki

---

## 10. Powiązane dokumenty

- [MARKETING.md](./MARKETING.md) — pełna dokumentacja modułu
- [.ai/MULTI_PROJECT_CONTEXT.md](../.ai/MULTI_PROJECT_CONTEXT.md) — architektura pneadm + pnedu
- [pnedu/SEO.md](../../pnedu/SEO.md) — SEO frontu (osobny temat)

---

*Przy kolejnej pracy z AI wklej na start: „Przeczytaj docs/MARKETING-HANDOFF.md i docs/MARKETING.md”.*

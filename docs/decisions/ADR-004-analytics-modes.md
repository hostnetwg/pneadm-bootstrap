# ADR-004: Tryby Analityki I Sampling

Data utworzenia/aktualizacji: 2026-06-25  
Status: zaakceptowane; panel runtime override wdrożony (wariant B+C)

## Kontekst

PNEdu prowadzi różne typy działań:

- płatne szkolenia strategiczne,
- kampanie sprzedażowe,
- bezpłatne wydarzenia,
- masowe webinary TIK,
- rejestracje obecności z dużym ruchem.

Nie każdy przypadek wymaga takiego samego poziomu trackingu. Dla masowych wejść 800-1400 osób pełny raw tracking JS mógłby obciążyć system i wygenerować niepotrzebne dane.

## Decyzja

Wprowadzamy tryby analityki:

- `full`,
- `standard`,
- `light`,
- `aggregate_only`,
- `off`.

Dodatkowo przewidujemy sampling:

- 100%,
- 50%,
- 10%.

## Znaczenie Trybów

### `full`

Dla:

- strategicznych płatnych kampanii,
- analizy UX formularza,
- testów A/B,
- diagnozy problemów.

Zapisuje:

- backend eventy,
- JS eventy,
- interakcje sekcji i pól bez wartości,
- time checkpoints,
- porzucenia,
- validation errors.

### `standard`

Dla:

- domyślnych płatnych szkoleń,
- typowych kampanii sprzedażowych.

Zapisuje:

- backend eventy,
- podstawowe JS eventy,
- start formularza,
- CTA,
- wybory płatności,
- błędy walidacji.

### `light`

Dla:

- większych bezpłatnych wydarzeń,
- lżejszych kampanii,
- sytuacji, gdzie potrzebne są tylko główne metryki.

Zapisuje:

- wejścia,
- widoki formularza,
- start formularza,
- submit,
- zamówienie,
- płatność/faktura.

### `aggregate_only`

Dla:

- masowych webinarów,
- TIK,
- rejestracji obecności,
- dużych wejść 800-1400 osób naraz.

Zapisuje:

- tylko liczniki/agregaty,
- bez pełnego raw event logu,
- bez szczegółowych eventów JS.

### `off`

Dla:

- awarii,
- testów technicznych,
- potrzeby natychmiastowego wyłączenia analityki.

Zapisuje:

- nic w `pne_analytics`.

## Sampling

Rekomendowane wartości:

- 100% dla `full`,
- 100% lub 50% dla `standard`,
- 50% lub 10% dla `light`,
- brak raw eventów dla `aggregate_only`.

Sampling powinien być deterministyczny po `analytics_session_id`, aby jedna sesja nie zmieniała losowo decyzji w trakcie wizyty.

## Zastosowanie Per Kampania / Szkolenie / Formularz

Priorytet ustawień:

1. awaryjny globalny `off`,
2. ustawienie kampanii,
3. ustawienie szkolenia,
4. ustawienie formularza,
5. domyślny tryb systemowy.

Przykłady:

- płatna kampania dyrektorska: `full`, 100%,
- zwykłe płatne szkolenie: `standard`, 100%,
- bezpłatny webinar: `light`, 50%,
- TIK z 1400 wejść: `aggregate_only`,
- awaria bazy: `off`.

## Uzasadnienie Wydajnościowe

Tryby i sampling pozwalają:

- ograniczyć liczbę eventów,
- zmniejszyć obciążenie Redis,
- zmniejszyć rozmiar `pne_analytics`,
- unikać problemów przy masowych wydarzeniach,
- nadal mierzyć strategiczne kampanie szczegółowo.

## Ryzyka

| Ryzyko | Ograniczenie |
---|---|
| zbyt mało danych przez sampling | wyższy sampling dla kampanii strategicznych |
| brak porównywalności kampanii | zapisywać `tracking_mode` i `sample_rate` |
| przypadkowe `off` | widoczny alert w panelu admina |
| nadmiar danych w `full` | retencja raw eventów i monitorowanie kolejki |

## Runtime Override Z Panelu (Wdrożone 2026-06-25, wariant B+C)

Dodaliśmy panel admina `Analityka -> Ustawienia` (`/analytics/settings`) pozwalający
zmieniać tryb analityki w czasie działania, bez edycji `.env` i bez `config:clear`.

### Źródło prawdy i tabela

- Ustawienia trzymane w bazie `pneadm`, tabela `analytics_settings` (connection domyślny `mysql`,
  NIE `analytics`/`pne_analytics`). Jeden rekord `id=1`.
- Pola: `enabled_override` (nullable boolean), `default_mode_override` (nullable string),
  `updated_by` (nullable user id), `created_at`, `updated_at`.
- Odczyt wspólny dla obu aplikacji:
  - `pneadm`: `App\Models\AnalyticsSetting` (connection domyślny),
  - `pnedu`: `App\Models\AnalyticsSetting` (connection `pneadm`, wzorzec jak `PaymentDisplayOption`).
- Cache: `Cache::remember('analytics_settings_singleton', 60s, ...)`, czyszczony po każdym zapisie
  w panelu (`forgetSettingsCache()`).

### Kolejność ustalania trybu w `AnalyticsModeResolver` (oba projekty)

1. **Hard kill switch**: jeśli `config('analytics.enabled') === false` → zawsze `off`
   (priorytet absolutny, override nie może tego nadpisać).
2. W przeciwnym razie odczyt runtime override z `pneadm.analytics_settings`:
   - `enabled_override === false` → `off`,
   - `enabled_override === true` → włączone (idź dalej do trybu),
   - `enabled_override === null` → użyj `.env/config`.
3. Tryb: `explicit mode` (jeśli przekazany) → `default_mode_override` → `config('analytics.default_mode')`.
4. Nieznany/niepoprawny tryb → bezpieczny fallback do `standard` (`AnalyticsMode::fromConfig`).

`enabled_override`/`default_mode_override = null` oznacza „użyj `.env/config`”.

### Zakres etapu B+C

- `sample_rate` jest **tylko podglądowy** w panelu (bez edycji). Edycja samplingu dla eventów
  server-to-server (płatności/faktury bez sesji) wymaga osobnej decyzji — patrz sekcja Sampling.
- Brak per-course / per-campaign / per-event override (pozostaje koncepcją na przyszłość).
- Dostęp do panelu: zalogowany admin (`isAdmin`), middleware jak panel debug.
- Audit log zmian: `ActivityLog::logCustom('analytics_settings_updated', ...)` (stare/nowe wartości,
  bez sekretów).

## Baner Ostrzegawczy Stanu Analityki (wdrożone 2026-06-25)

Dodano widoczny baner ostrzegawczy w panelach sekcji `Analityka` (`/analytics/sales-funnel`,
`/analytics/debug-events`, `/analytics/settings`), aby uniknąć sytuacji, w której analityka
zostanie przypadkowo wyłączona i powstanie luka w danych.

- Logika stanu: `App\Services\Analytics\AnalyticsRuntimeStatusService` (read-only; korzysta z
  `AnalyticsModeResolver` i `AnalyticsSetting`, bez duplikowania logiki w widokach).
- Partial: `resources/views/analytics/partials/status-banner.blade.php`.
- Poziomy:
  - **danger** (czerwony): hard kill switch `.env ANALYTICS_ENABLED=false` lub efektywny tryb `off`,
  - **warning** (żółty): efektywny tryb `aggregate_only` lub `light`,
  - brak banera dla `standard`/`full`.
- Baner na dashboardzie i debug zawiera link do `/analytics/settings`. Na stronie ustawień linku nie ma.
- Panel `pneadm` nadal **nie odpytuje `pnedu`** po HTTP (brak health endpointu). Na stronie ustawień
  jest informacja, że `pnedu` ma własny `.env` i może mieć lokalny hard kill switch.

## Do Aktualizacji Po Wdrożeniu

- ~~Dopisać finalne miejsce konfiguracji trybów.~~ Zrobione (panel + `analytics_settings`).
- ~~Dopisać UI w `adm.pnedu.pl`.~~ Zrobione (`Analityka -> Ustawienia`).
- ~~Dopisać zasady alertów przy `off`.~~ Zrobione (baner stanu analityki w panelach).
- Dopisać realne progi samplingowe po testach ruchu (osobny etap, na razie sample_rate read-only).

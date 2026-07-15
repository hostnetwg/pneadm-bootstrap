# Dashboard zamówień (`/`)

Panel operacyjny w **adm.pnedu.pl** — domyślna strona po zalogowaniu (`route('dashboard')`).

## Sekcje

| Sekcja | Źródło danych | Odświeżanie |
|--------|---------------|-------------|
| Karty „Dziś / Do obsługi / …” | `form_orders` (SQL, scope `includedInDashboardMetrics`) | Polling JSON co ~45 s (pauza gdy karta ukryta) |
| **Aktywni teraz** | `analytics_events` (lejek) | Osobny polling ~45 s, okno ~30 min |
| Wykres zamówień | `form_orders.order_date` w zakresie filtra (ten sam scope) | Przy zmianie statystyk + sekcji wykresu |
| Ostatnie FORM | `form_orders` (8 rekordów, ten sam scope) | Jak wykres |
| Terminy szkoleń | `courses.start_date` w zakresie filtra | Jak wykres |

Konfiguracja pollingu i „Aktywni teraz”: `config/analytics.php` → `live_visitors_dashboard`.

## API pollingu

| Endpoint | Opis |
|----------|------|
| `GET /api/dashboard/orders-stats` | Same liczniki co karty (headline) |
| `GET /api/dashboard/orders-stats?sections=1&date_from=&date_to=` | Headline + wykres, ostatnie zamówienia, terminy szkoleń |
| `GET /api/dashboard/live-visitors` | Blok „Aktywni teraz” |

## Dźwięk przy nowym zamówieniu

- Przycisk głośnika obok nagłówka — stan w `localStorage` (`dashboard_new_order_sound_enabled`).
- Sygnał: wzrost `latest_form_order_id` (`MAX(form_orders.id)`) między pollingsami — **nie** sam licznik „Dziś”, żeby uniknąć fałszywych alarmów (np. usunięcie + nowe zamówienie w tym samym oknie).
- Przeglądarka wymaga interakcji użytkownika (klik / klawisz) przed odtworzeniem dźwięku (AudioContext).
- Działa tylko przy otwartej karcie dashboardu.

## Aktywni teraz

Serwis: `App\Services\Analytics\AnalyticsLiveVisitorsService`.

- **Szkolenie:** tytuł z całej sesji (snapshot z wcześniejszych eventów lub lookup `courses.title` po `course_id`), nie tylko z ostatniego eventu JS.
- **Złożone zamówienie:** jeśli w sesji był `form_order_created`, wiersz jest **zielony** z linkiem `#id` — nawet gdy późniejszy event to np. opóźniony `order_form_submit_clicked`.
- **Wypełnianie formularza:** wiersz **czerwony**, gdy ostatni event to interakcja z formularzem (`order_form_started`, sekcje, CTA, submit) i brak `form_order_created` w sesji. Samo `order_form_viewed` **nie** podświetla.

Kolumna **Wejście:** referrer → kampania → UTM → `direct (bezpośrednio)`.

### Rozwiązywanie problemów („Aktywni teraz” = 0)

Blok czyta **wyłącznie** `analytics_events` (nie Google Analytics). Ruch w GA przy zerze w panelu zwykle oznacza, że **worker kolejki na pnedu.pl** nie zapisuje eventów — szczegóły i checklista: [`docs/deploy/PRODUCTION_QUEUE_OPS.md`](deploy/PRODUCTION_QUEUE_OPS.md).

Szybka diagnostyka na prod (SSH):

```bash
bash docs/deploy/scripts/prod-queue-healthcheck.sh --strict
bash docs/deploy/scripts/prod-queue-healthcheck.sh --restart   # jeśli worker zawieszony
```

Weryfikacja w panelu: `/analytics/debug-events` — po wejściu na kurs na pnedu.pl powinien pojawić się świeży event (np. `course_description_viewed`).

## Wykres — terminy szkoleń

Wzorowane na wykresie w **Analityka → Rozliczenia** (`/analytics/revenue`):

- Fioletowe markery 🎓 przy dacie startu szkolenia (`courses.start_date` w zakresie filtra wykresu).
- Checkbox „Terminy szkoleń (start)” — włącza/wyłącza dataset scatter.
- **Tooltip:** przy dniu wykresu lub markerze — lista szkoleń z godziną, tytułem i **instruktorem** (`instructor.full_title_name`).
- **Oś X (agregacja dzienna):** dwie linie — data (`7 lip`) i dzień tygodnia (`poniedziałek`). Tooltip: pełna data z dniem w nawiasie, np. `7 lip 2026 (poniedziałek)`.
- Przy zakresie **> 90 dni** wykres jest miesięczny — markery wg miesiąca startu, bez dnia tygodnia na osi.

Serwis: `App\Services\Dashboard\DashboardCourseScheduleService`.

## Tabela „Terminy szkoleń w zakresie”

Pod listą **Ostatnie zamówienia FORM** (lewa kolumna): data, godzina, link do kursu, instruktor. Liczba i zakres dat = filtr wykresu. Odświeża się razem z `?sections=1`.

## Metryki FORM (Dziś, wykres, ostatnie zamówienia)

Wspólny filtr: `FormOrder::includedInDashboardMetrics()` (`scopeIncludedInDashboardMetrics`).

- **Liczone:** zamówienia z wystawioną FV **lub** w otwartej kolejce operacyjnej (`withInvoiceOrNeedsHandling`).
- **Wykluczone:** anulowane (`cancelled_at IS NOT NULL`) — w tym po „Anuluj zamówienie” (często z legacy `status_completed = 1` bez FV).

Dzięki temu licznik **Dziś (FORM)**, słupek wykresu na bieżący dzień i tabela **Ostatnie zamówienia FORM** pokazują tę samą populację zamówień. Licznik **Do obsługi** nadal używa osobnego scope `needsActiveHandling`.

## Polling (wydajność LVE)

- Domyślny interwał: **45 s** (`ANALYTICS_LIVE_VISITORS_POLL_INTERVAL_SECONDS`).
- Przy ukrytej karcie przeglądarki polling się **nie wykonuje** (pauza na `visibilityState === hidden`).
- Snapshot liczników cache **20 s**; „Aktywni teraz” cache **15 s** (`ANALYTICS_LIVE_VISITORS_RESPONSE_CACHE_SECONDS`).

## Pliki

| Plik | Rola |
|------|------|
| `app/Http/Controllers/DashboardOrdersController.php` | SSR strony |
| `app/Http/Controllers/DashboardOrdersStatsController.php` | API pollingu |
| `app/Services/Dashboard/DashboardOrdersStatsService.php` | Liczniki headline + `latest_form_order_id` |
| `app/Services/Dashboard/DashboardOrdersDashboardService.php` | Wykres, ostatnie zamówienia, sekcje API |
| `app/Services/Dashboard/DashboardCourseScheduleService.php` | Terminy szkoleń w zakresie |
| `app/Services/Analytics/AnalyticsLiveVisitorsService.php` | Aktywni teraz |
| `app/Models/FormOrder.php` | Scope `includedInDashboardMetrics` |
| `resources/views/dashboard/orders.blade.php` | Widok + JS (Chart.js, polling, dźwięk) |

## Testy

```bash
sail artisan test --filter=AnalyticsLiveVisitorsDashboardTest
sail artisan test --filter=DashboardOrdersStatsApiTest
sail artisan test --filter=DashboardCourseScheduleServiceTest
sail artisan test --filter=FormOrderDashboardMetricsScopeTest
```

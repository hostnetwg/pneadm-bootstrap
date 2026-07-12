# Dashboard zamówień (`/`)

Panel operacyjny w **adm.pnedu.pl** — domyślna strona po zalogowaniu (`route('dashboard')`).

## Sekcje

| Sekcja | Źródło danych | Odświeżanie |
|--------|---------------|-------------|
| Karty „Dziś / Do obsługi / …” | `form_orders` (SQL) | Polling JSON co ~15 s |
| **Aktywni teraz** | `analytics_events` (lejek) | Osobny polling, okno ~30 min |
| Wykres zamówień | `form_orders.order_date` w zakresie filtra | Przy zmianie statystyk + sekcji wykresu |
| Ostatnie FORM | `form_orders` (8 rekordów) | Jak wykres |
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

## Pliki

| Plik | Rola |
|------|------|
| `app/Http/Controllers/DashboardOrdersController.php` | SSR strony |
| `app/Http/Controllers/DashboardOrdersStatsController.php` | API pollingu |
| `app/Services/Dashboard/DashboardOrdersStatsService.php` | Liczniki headline + `latest_form_order_id` |
| `app/Services/Dashboard/DashboardOrdersDashboardService.php` | Wykres, ostatnie zamówienia, sekcje API |
| `app/Services/Dashboard/DashboardCourseScheduleService.php` | Terminy szkoleń w zakresie |
| `app/Services/Analytics/AnalyticsLiveVisitorsService.php` | Aktywni teraz |
| `resources/views/dashboard/orders.blade.php` | Widok + JS (Chart.js, polling, dźwięk) |

## Testy

```bash
sail artisan test --filter=AnalyticsLiveVisitorsDashboardTest
sail artisan test --filter=DashboardOrdersStatsApiTest
sail artisan test --filter=DashboardCourseScheduleServiceTest
```

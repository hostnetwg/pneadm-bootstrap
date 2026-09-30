# Raporty automatów (`/ops-reports`)

Dziennik zleceń wykonywanych **w tle** (nie logi kliknięć z **Admin → Logi aktywności**).

**Panel:** `https://adm.pnedu.pl/ops-reports`  
Menu: **Admin → Raporty automatów**.

## Co jest w dzienniku

| Typ | Kiedy powstaje | Co widać |
|-----|----------------|----------|
| `ksef_background` | Czerwony przycisk „Wystaw Fakturę iFirma … KSeF” — jeden raport na dzień | ile FV zlecono, ile numerów KSeF wpadło, ile czeka, ile błędów; pozycje = zamówienia |
| `access_expiry_reminders` | Cron `participants:send-access-expiry-reminders` (oraz każdy ręczny `sail artisan …`) | ile szkoleń, ile wiadomości zlecono; pozycja = szkolenie |

Szczegóły runu: link do zamówienia albo listy uczestników szkolenia.

Mail dzienny **nie** jest wysyłany (na razie tylko panel).

## Tabele

Migracja: `database/migrations/2026_09_15_103600_create_ops_runs_tables.php`

- `ops_runs` — przebieg (typ, status, data, podsumowanie JSON)
- `ops_run_items` — pozycje (zamówienie / szkolenie, status, komunikat, payload)

Serwis: `App\Services\Ops\OpsRunRecorder`.

## KSeF w tle (tylko nowe wystawienia)

Po fazie `create` (zwykły numer FV) przeglądarka **nie czeka** na MF.

1. Job `SubmitFormOrderToKsefJob` — jedno `ksef/send` (bez 5-minutowego pollingu w HTTP).
2. Job `FetchFormOrderKsefNumberJob` — pojedynczy GET z iFirma, a następne próby
   są coraz rzadsze: domyślnie po 1, 2, 5, 10, 15, 30 i 60 minutach
   (`IFIRMA_KSEF_BACKGROUND_RETRY_DELAYS_SECONDS`). Razem: próba bezpośrednia
   i maksymalnie 7 odroczonych, przez około 2 godziny.
3. Mail do klienta **tylko po NumerKSeF** (`ksef_email_pending`), tak jak wcześniej.
4. Na karcie zamówienia: badge (w kolejce / oczekuje na MF / KSeF / błąd).
5. Po wystawieniu przeglądarka sprawdza lekki lokalny status najwyżej 3 razy:
   po 15 s, kolejnych 45 s i kolejnych 120 s, wyłącznie w widocznej karcie.
   Nie uruchamia pollingu po samym wejściu na oczekujące zamówienie i zatrzymuje
   się natychmiast po HTTP 429. Po przejściu dalej numer zapisze kolejka.
6. Każdy odroczony job ma w payloadzie `queuedAt` i `scheduledFor`. Raport
   automatu pokazuje godzinę następnej zaplanowanej próby.

**Świadomie poza zakresem:** cron dociągający KSeF dla całego filtra `filter_no_ksef` (stare FV). Ikona Odśwież przy numerze faktury nadal działa ręcznie.

Worker kolejki pneadm musi działać (cron `queue:work`). Joby **nie śpią** w workerze:
Laravel zapisuje czas dostępności zadania w kolejce `database`, a worker podejmuje
je dopiero we właściwej minucie.

## Przypomnienia o wygaśnięciu

Na `/courses/{id}/participants` karta automatu pokazuje ostatni przebieg (data, ile osób, link do raportu).

## Testy

```bash
sail test --filter=IfirmaFormOrderKsefSubmissionServiceTest
sail test --filter=IfirmaFormOrderKsefBackgroundServiceTest
sail test --filter=FormOrderKsefHelpersTest
```

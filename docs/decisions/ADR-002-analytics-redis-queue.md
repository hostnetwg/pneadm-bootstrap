# ADR-002: Redis Queue `analytics`

Data utworzenia/aktualizacji: 2026-06-24  
Status: zaakceptowane koncepcyjnie, do potwierdzenia przez właściciela

## Kontekst

Tracking eventowy będzie wykonywany w krytycznych miejscach:

- wejście z kampanii,
- opis szkolenia,
- formularz zamówienia,
- walidacja,
- zapis zamówienia,
- płatność,
- faktura.

Te miejsca nie mogą być spowalniane przez zapis analityki.

## Decyzja

Do zapisu eventów analitycznych używamy Redis queue:

```text
analytics
```

Docelowy przepływ:

```text
request
→ AnalyticsService
→ dispatch StoreAnalyticsEventJob on queue analytics
→ worker
→ pne_analytics
```

## Uzasadnienie

Redis queue:

- odciąża request użytkownika,
- ogranicza wpływ analityki na formularz,
- pozwala skalować worker niezależnie,
- pozwala chwilowo buforować eventy,
- pasuje do istniejącej infrastruktury Redis,
- ułatwia retry.

## Zasada Fail-Silent

Jeżeli tracking nie działa:

- nie blokować użytkownika,
- nie przerywać zamówienia,
- nie przerywać płatności,
- nie pokazywać błędu analityki,
- opcjonalnie zapisać techniczny warning bez danych osobowych.

## Brak Wpływu Na Proces Zamówienia

Zabronione:

- synchroniczny ciężki insert do `pne_analytics` w trakcie submitu,
- rzucenie wyjątku analityki do kontrolera zamówienia,
- rollback zamówienia z powodu błędu analityki,
- oczekiwanie na odpowiedź z `pne_analytics`.

Dozwolone:

- szybkie przygotowanie payloadu,
- dispatch joba,
- pominięcie eventu przy awarii,
- retry w workerze.

## Ryzyka

| Ryzyko | Zabezpieczenie |
---|---|
| Redis niedostępny | catch exception, pominąć event |
| worker nie działa | monitoring kolejki, alert techniczny |
| kolejka rośnie | tryby analityki, sampling, batchowanie |
| payload zawiera PII | sanitizer i whitelist pól |
| duplikaty eventów | `event_uuid` i unique index |
| hosting ogranicza worker | scheduler zgodny z obecną strategią kolejek |

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalne komendy workerów.
- Dopisać monitoring kolejki.
- Dopisać retry/timeout.
- Dopisać test fail-silent.
- Dopisać decyzję o schedulerze lub Supervisorze.

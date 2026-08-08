# iFirma API — kanoniczna dokumentacja

**Źródło prawdy (oficjalne docs iFirma):**  
**[https://api.ifirma.pl/](https://api.ifirma.pl/)**

Przed każdą zmianą w integracji z iFirma (wystawianie FV, lista, wpłaty, KSeF, sync statusu płatności) **najpierw sprawdź ten portal** — nie polegaj wyłącznie na pamięci / starym kodzie.

## Kluczowe strony

| Temat | URL |
|---|---|
| Start / historia zmian / limity | [https://api.ifirma.pl/](https://api.ifirma.pl/) |
| Lista faktur (`GET faktury.json`) | [https://api.ifirma.pl/lista-faktur/](https://api.ifirma.pl/lista-faktur/) |
| Rejestrowanie wpłat | [https://api.ifirma.pl/rejestrowanie-wplat-do-faktur/](https://api.ifirma.pl/rejestrowanie-wplat-do-faktur/) |
| Dodatkowy podmiot na fakturze | [https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/](https://api.ifirma.pl/dodatkowy-podmiot-na-fakturze/) |

## Ważne ograniczenia (stan wg docs)

- **Lista faktur:** parametr `dataOd` jest **wymagany**. Bez `dataDo` API zwraca dokumenty z okresu **30 dni** od `dataOd` — dlatego w kodzie zawsze ustawiamy też `dataDo`.
- **Brak endpointu „po numerze FV”:** przy braku `ifirma_invoice_id` szukamy na liście po `PelnyNumer` (opcjonalnie filtr kwoty / KSeF), **od miesiąca z numeru FV**, potem szersze okna; potem (preferowane) ładujemy szczegóły `GET fakturakraj/{id}`. Limit stron listy chroni przed nieskończoną pętlą — przy pudle komunikat wyjaśnia, czy lista się skończyła, czy ucięliśmy skan.
- **Limity:** 15 000 zapytań/dzień, 100/minutę (patrz strona główna API).

## Kod w tym repo

- Klient HTTP: `App\Services\IfirmaApiService`
- Sync statusu płatności / windykacja: `App\Services\IfirmaInvoicePaymentStatusService` + `docs/WINDYKACJA.md`
- KSeF / wystawianie: `docs/KSEF_FORM_ORDERS.md`

Ostatnia weryfikacja treści docs przy etapie syncu dwukrokowego: 2026-08-08.

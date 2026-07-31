# Windykacja Faktur — MVP

## Cel

Moduł `Księgowość → Windykacja` wspiera wewnętrzną obsługę nieopłaconych faktur i kontakt z kontrahentami. Nie zastępuje iFirma jako księgowego źródła prawdy.

## Źródła Prawdy

- Status opłacenia faktur odroczonych: iFirma.
- Płatności online: `online_payment_orders` i statusy bramek PayU/PayNow.
- Operacyjna historia windykacji: `debt_cases`, `debt_case_actions`, `debt_case_contacts`.

`adm.pnedu.pl` może przechowywać sprawę, notatki, segment klienta i kolejne działania, ale zamknięcie sprawy jako opłacone powinno być potwierdzone w iFirma albo oznaczone jako ręczna weryfikacja.

Identyfikatory faktury na `form_orders` (używane przy synchronizacji statusu z iFirma):

- `invoice_number` — klasyczny `PelnyNumer` (np. `43/7/2026`),
- `ksef_number` / `ksef_*` — numer i status KSeF,
- `ifirma_invoice_id` — wewnętrzny `Identyfikator` / FakturaId dokumentu w iFirma (preferowany klucz do `GET fakturakraj/{id}.json`).

## MVP

Pierwszy etap obejmuje:

- dashboard spraw windykacyjnych pod `accounting.collections.*`,
- tworzenie sprawy z `form_orders.id`,
- przy tworzeniu sprawy: wyszukiwarka numeru faktury / KSeF z przyciskami „Utwórz sprawę” / „Otwórz sprawę” / „Wstaw ID” (korzysta z `accounting.debtors.lookup`),
- na `/accounting/debtors`: numer zamówienia nad fakturą oraz skrót do utworzenia/otwarcia sprawy windykacyjnej,
- historię działań: notatka, e-mail, SMS, telefon, iFirma, obietnica płatności, sporne, wstrzymanie, zamknięcie,
- alternatywne kontakty,
- segmentację klienta (`standard`, `risk`, `vip`, `vip_with_overdue`, `manual_review`),
- ostrzeżenie VIP / lojalny klient,
- skróty z `/form-orders` i `/form-orders/{id}` do aktywnej sprawy,
- na karcie sprawy: przyciski **Poprzednia** / **Następna** (kolejność jak na liście — najnowsze pierwsze; Poprzednia = nowsza sprawa, Następna = starsza),
- w „Dane sprawy”: szkolenie z linkiem do karty kursu, datą oraz prowadzącym.

## Kto obsługuje sprawę

Każda czynność w Windykacji zapisuje zalogowanego użytkownika panelu `adm.pnedu.pl`:

- `debt_cases.created_by` — kto utworzył sprawę,
- `debt_cases.assigned_to_id` — aktualny opiekun (ostatni admin, który zapisał ustawienia, działanie lub kontakt),
- `debt_case_actions.user_id` — kto wykonał działanie / zmianę ustawień,
- `debt_case_contacts.created_by` — kto dodał kontakt.

Na liście spraw widać kolumnę „Opiekun”, a na karcie sprawy: „Utworzył”, „Opiekun” oraz autora przy każdym wpisie historii.

## VIP / Lojalny Klient

VIP nie oznacza braku windykacji. Oznacza delikatniejszą i bardziej personalną obsługę.

Rekomendowana kolejność działań:

1. telefon lub personalny e-mail,
2. ponowne sprawdzenie przelewu / wyciągu,
3. dopiero później formalny monit.

Serwis `DebtCustomerProfileService` liczy osobno:

- `risk_score` — aktywne zaległości i przeterminowanie,
- `relationship_score` — liczba i wartość historycznych zamówień oraz opłacone płatności online.

## Synchronizacja statusu płatności z iFirma (odczyt)

Endpoint: `POST /accounting/collections/{debtCase}/sync-ifirma`  
Serwis: `App\Services\IfirmaInvoicePaymentStatusService`

1. Jeśli `form_orders.ifirma_invoice_id` jest ustawione → `GET fakturakraj/{id}.json`.
2. W przeciwnym razie → `GET faktury.json` z zakresem dat obejmującym **miesiąc z numeru FV** (np. `239/6/2026` → czerwiec) oraz `order_date` / datę z sprawy; dopasowanie po `PelnyNumer` (przy znalezieniu uzupełnia `ifirma_invoice_id`).
3. Status wyliczany z `Zaplacono` / `Brutto`|`WartoscBrutto` / `TerminPlatnosci`: `oplacone`, `oplaconeCzesciowo`, `nieoplacone`, `przeterminowane`.
   (Lista faktur zwraca `Brutto`; szczegóły `fakturakraj/{id}` — `WartoscBrutto`.)
4. Zapis cache na `debt_cases` + wpis historii `ifirma_sync`.
5. **Nie** zamyka sprawy automatycznie — przy statusie „Opłacona” UI podpowiada ręczne zamknięcie.

Testy: `--filter=IfirmaInvoicePaymentStatusServiceTest`, `--filter=AccountingCollectionsTest`.

## Publiczny Formularz

W publicznym formularzu zamówienia nigdy nie pokazujemy informacji o zaległości, windykacji, ryzyku ani klasyfikacji klienta.

Przyszły etap może dyskretnie wymuszać płatność online przez ukrycie lub wyłączenie opcji odroczonej faktury, szczególnie dla osoby prywatnej z wysokim ryzykiem. Reguła musi być walidowana backendowo.

## Kolejne Etapy

- ~~synchronizacja statusów faktur z iFirma API~~ — **wdrożone (odczyt)**: przycisk „Odśwież status z iFirma” na karcie sprawy; cache w `debt_cases.ifirma_payment_status` / `ifirma_synced_at`; preferowany klucz `form_orders.ifirma_invoice_id`, fallback lista `faktury.json` po `PelnyNumer`; **bez** auto-zamykania sprawy i **bez** rejestracji wpłat,
- import CSV wyciągów bankowych,
- sugestie dopasowania po numerze faktury, KSeF, `form_orders.id`, NIP, nazwie i kwocie,
- ręczna akceptacja dopasowań,
- ewentualne rejestrowanie wpłat w iFirma dopiero po potwierdzeniu operatora.

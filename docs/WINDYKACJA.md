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

- ~~synchronizacja statusów faktur z iFirma API~~ — **wdrożone (odczyt)**: przycisk „Odśwież status z iFirma” na karcie sprawy; cache w `debt_cases.ifirma_payment_status` / `ifirma_synced_at`; preferowany klucz `form_orders.ifirma_invoice_id`, fallback lista `faktury.json` po `PelnyNumer`; **bez** auto-zamykania sprawy,
- ~~import CSV wyciągów bankowych~~ — **wdrożone (MVP mBank)**: `Księgowość → Import wyciągu` (`accounting.bank-imports.*`); tabele `bank_statement_imports` / `bank_transactions` / `bank_transaction_matches`; parser `MbankStatementParser`; sugestie FV / KSeF / `#ID` / NIP / **imię+nazwisko nabywcy (tylko FV bez NIP) + kwota** → Medium; ręczna akceptacja → lokalny link + `debt_case_actions.bank_match`; przy zgodnej kwocie opcjonalnie **rejestracja wpłaty w iFirma** + sync statusu,
- dopracowanie sugestii dopasowań (fuzzy nazwa, bulk),
- ~~ewentualne rejestrowanie wpłat w iFirma dopiero po potwierdzeniu operatora~~ — **wdrożone** (modal przy akceptacji importu: „Akceptuj + wpłata w iFirma” / „Tylko lokalnie”),
- automatyczne zamykanie spraw po matchu — świadomie poza MVP.

## Import wyciągu mBank (MVP)

- Menu: `Księgowość → Import wyciągu`.
- Format: CSV mBank (`lista_operacji_*.csv`), UTF-8 BOM, `;`, preambuła do `#Data operacji;...`.
- Tylko wpływy (`amount > 0`) idą do UI dopasowań; wydatki mogą być zapisane, ale nie są przeglądane w MVP.
- Filtry przeglądu: `Do przeglądu`, `Bez powiązania`, `High`, `Medium`, `Low`, `Zaakceptowane`, `Ignorowane`, `Wszystkie wpływy`.
- Deduplikacja: `bank_transactions.fingerprint` (data + kwota + opis znormalizowany).
- Wydajność: lookup FV/KSeF/NIP/spraw ładowany raz do pamięci + bulk insert; limit czasu requestu podniesiony do 600 s (duże CSV ~5k wierszy).
- Konflikty: jeśli w tytule jest **inny KSeF** niż na zamówieniu → max **Low** (`ksef_mismatch`); jeśli nadawca z wyciągu nie pasuje do nabywcy/odbiorcy → obniżenie High→Medium (`party_name_mismatch`) — typowy błędny numer FV w tytule.
- **Priorytet KSeF:** gdy w tytule jest nr KSeF i istnieje zamówienie z tym numerem → tylko ta sugestia (FV z tytułu może być błędna). Jeśli FV z tytułu ≠ FV na zamówieniu → **Medium** przy zgodnej kwocie (`invoice_number_mismatch`). Gdy KSeF z tytułu **nie ma** w DB → fallback na numer FV (jak wcześniej).
- Akceptacja: wymaga istniejącej sprawy **lub** `form_order_id` (wtedy tworzy sprawę jak w `collectionsStore`); wpis historii typu `bank_match`; sekcja „Wpłaty z wyciągu” na karcie sprawy.
- Ostrzeżenie przed akceptacją przy `amount_mismatch` (modal Bootstrap): jedno powiązanie przelewu z FV, odrzucenie innych sugestii, znikanie z kolejki — ryzyko przy jednym przelewie za kilka faktur; **bez** rejestracji w iFirma.
- Przy **zgodnej kwocie**: modal wyboru — **Akceptuj + wpłata w iFirma** (POST `faktury/wplaty/...` + odświeżenie statusu na sprawie) albo **Tylko lokalnie**.
- W podglądzie sugestii: **Sprawdź status z iFirma** (bez tworzenia sprawy, jeśli jeszcze jej nie ma). Gdy iFirma zwraca `Opłacona`, operator może wybrać **Zaakceptuj jako opłacone w iFirma** — lokalny `bank_match`, bez rejestracji kolejnej wpłaty.
- Ikona oka przy wierszu → modal Bootstrap z porównaniem **przelew z wyciągu** ↔ **zamówienie/sugestia** (FV z opisu, KSeF z tytułu, nabywca, NIP, e-mail, podstawa dopasowania).
- Pliku produkcyjnego z PII **nie** commitować; fixture testowa: `tests/fixtures/bank/mbank_sample.csv`.

Testy: `--filter=MbankStatementParserTest`, `--filter=PaymentTitleExtractorTest`, `--filter=BankTransactionMatcherTest`, `--filter=BankStatementImportTest`.

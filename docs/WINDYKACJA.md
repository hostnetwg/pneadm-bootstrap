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
  - domyślny filtr listy: **Niezamknięte** (`status=active` / `DebtCase::active()`); kafelek „Aktywne sprawy” ustawia ten filtr; opcja „Wszystkie” pokazuje też zamknięte,
- tworzenie sprawy z `form_orders.id`,
- przy tworzeniu sprawy: wyszukiwarka numeru faktury / KSeF z przyciskami „Utwórz sprawę” / „Otwórz sprawę” / „Wstaw ID” (korzysta z `accounting.debtors.lookup`),
- na `/accounting/debtors`: numer zamówienia nad fakturą oraz skrót do utworzenia/otwarcia sprawy windykacyjnej,
- historię działań: notatka, e-mail, SMS, telefon, iFirma, obietnica płatności, sporne, wstrzymanie, zamknięcie,
- alternatywne kontakty (dodawanie i usuwanie z karty sprawy),
- segmentację klienta (`standard`, `risk`, `vip`, `vip_with_overdue`, `manual_review`),
- ostrzeżenie VIP / lojalny klient,
- skróty z `/form-orders` i `/form-orders/{id}` do aktywnej sprawy,
- na karcie sprawy: przyciski **Poprzednia** / **Następna** (kolejność jak na liście — najnowsze pierwsze; Poprzednia = nowsza sprawa, Następna = starsza) oraz checkbox **tylko niezamknięte** (domyślnie zaznaczony; stan w `localStorage`),
- w „Dane sprawy”: szkolenie z linkiem do karty kursu, datą oraz prowadzącym; przy terminie płatności czerwone „X dni po terminie” tylko gdy status iFirma = `przeterminowane`; przy `oplacone` — szary napis tylko gdy znamy datę wpłaty z zaakceptowanego przelewu i była po terminie (bez daty wpłaty nie pokazujemy „po terminie”),

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

Powiązane zamówienia i VIP są liczone według jednej reguły „kto jest klientem”:

1. Jeśli zamówienie ma `recipient_nip`, klientem jest **odbiorca** i powiązujemy tylko po NIP odbiorcy. NIP nabywcy, nazwa/adres i e-maile są wtedy ignorowane, żeby organ prowadzący nie robił VIP-a wszystkim szkołom.
2. Jeśli odbiorca nie ma NIP, ale ma komplet: nazwa + adres + kod + miasto, klientem nadal jest **odbiorca**. Powiązanie idzie po znormalizowanym komplecie danych odbiorcy (`ul.`/`ulica`, wielkość liter, podstawowa interpunkcja/spacje) oraz pomocniczo po e-mailu zamawiającego.
3. Jeśli nie ma odbiorcy, ale jest `buyer_nip`, klientem jest **nabywca** i powiązujemy po NIP nabywcy.
4. Jeśli nie ma odbiorcy ani NIP nabywcy, klientem jest osoba/firma bez NIP i powiązujemy po `orderer_email`.

E-mail uczestnika nie bierze udziału w tej regule, bo uczestnik szkolenia często nie jest klientem decyzyjnym. Istniejące sprawy można przeliczyć komendą `sail artisan debt-cases:recalculate-profiles` (dry-run) oraz `sail artisan debt-cases:recalculate-profiles --apply` po akceptacji wyniku. Komenda nie zmienia `manual_vip`.

Ta sama reguła buduje historię powiązanych zamówień na `/accounting/debtors` (`accounting.debtors.lookup` → `DebtCustomerProfileService::relatedOrders`), na karcie sprawy oraz przy liczeniu VIP. Filtry checkboxów na debtors tylko zawężają widok po typie `link_reasons` zwróconym dla aktywnej strategii — nie łączą już OR-em NIP nabywcy / e-maili uczestnika z NIP odbiorcy.

## Synchronizacja statusu płatności z iFirma (odczyt)

Endpoint: `POST /accounting/collections/{debtCase}/sync-ifirma`  
Serwis: `App\Services\IfirmaInvoicePaymentStatusService`

1. Jeśli `form_orders.ifirma_invoice_id` jest ustawione → `GET fakturakraj/{id}.json`.
2. W przeciwnym razie → `GET faktury.json` z zakresem dat obejmującym **miesiąc z numeru FV** (np. `239/6/2026` → czerwiec) oraz `order_date` / datę z sprawy; dopasowanie po `PelnyNumer` (przy znalezieniu uzupełnia `ifirma_invoice_id`).
3. Status wyliczany z `Zaplacono` / `Brutto`|`WartoscBrutto` / `TerminPlatnosci`: `oplacone`, `oplaconeCzesciowo`, `nieoplacone`, `przeterminowane`.
   (Lista faktur zwraca `Brutto`; szczegóły `fakturakraj/{id}` — `WartoscBrutto`.)
4. Zapis cache na `debt_cases` + wpis historii `ifirma_sync`.
5. Na karcie sprawy **„Odśwież status z iFirma”** nadal **nie** zamyka sprawy automatycznie — tylko cache + podpowiedź UI.
6. **Auto-zamknięcie po akceptacji przelewu z wyciągu** (osobna ścieżka): gdy status iFirma = `oplacone` po **Akceptuj + wpłata w iFirma** albo po **Zaakceptuj jako opłacone w iFirma** / fladze `ifirma_already_paid` — sprawa dostaje `close` + `closed_at`, o ile nie jest już `closed` ani `disputed`. „Tylko lokalnie” bez potwierdzenia pełnej opłaty **nie** zamyka.

Testy: `--filter=IfirmaInvoicePaymentStatusServiceTest`, `--filter=AccountingCollectionsTest`.

## Publiczny Formularz

W publicznym formularzu zamówienia nigdy nie pokazujemy informacji o zaległości, windykacji, ryzyku ani klasyfikacji klienta.

Przyszły etap może dyskretnie wymuszać płatność online przez ukrycie lub wyłączenie opcji odroczonej faktury, szczególnie dla osoby prywatnej z wysokim ryzykiem. Reguła musi być walidowana backendowo.

## Kolejne Etapy

- ~~synchronizacja statusów faktur z iFirma API~~ — **wdrożone (odczyt)**: przycisk „Odśwież status z iFirma” na karcie sprawy; cache w `debt_cases.ifirma_payment_status` / `ifirma_synced_at`; preferowany klucz `form_orders.ifirma_invoice_id`, fallback lista `faktury.json` po `PelnyNumer`; **bez** auto-zamykania na samym syncu karty,
- ~~import CSV wyciągów bankowych~~ — **wdrożone (MVP mBank)**: `Księgowość → Import wyciągu` (`accounting.bank-imports.*`); tabele `bank_statement_imports` / `bank_transactions` / `bank_transaction_matches`; parser `MbankStatementParser`; sugestie FV / KSeF / `#ID` / NIP / **imię+nazwisko nabywcy (tylko FV bez NIP) + kwota** → Medium; ręczna akceptacja → lokalny link + `debt_case_actions.bank_match`; przy zgodnej kwocie opcjonalnie **rejestracja wpłaty w iFirma** + sync statusu,
- ~~ręczne powiązanie przelewu od strony sprawy~~ — **wdrożone**: na karcie sprawy sekcja „Wpłaty z wyciągu” ma wyszukiwarkę niepowiązanych wpływów (domyślnie po kwocie sprawy + fraza z opisu/nadawcy/konta) i modal Bootstrap „Powiąż lokalnie” / „+ wpłata iFirma”,
- ~~ręczne powiązanie od strony przelewu~~ — **wdrożone**: w modalu podglądu importu wyszukiwarka niezamkniętych spraw + powiązanie lokalne / + iFirma,
- dopracowanie sugestii dopasowań (fuzzy nazwa, bulk),
- ~~ewentualne rejestrowanie wpłat w iFirma dopiero po potwierdzeniu operatora~~ — **wdrożone** (modal przy akceptacji importu: „Akceptuj + wpłata w iFirma” / „Tylko lokalnie”),
- ~~automatyczne zamykanie spraw po matchu~~ — **wdrożone (wąski zakres)**: po akceptacji z wyciągu, gdy iFirma potwierdza pełną opłatę (`oplacone`); nie zamyka `disputed` / już `closed`; zwykłe „Tylko lokalnie” bez potwierdzenia opłaty nie zamyka.

## Import wyciągu mBank (MVP)

- Menu: `Księgowość → Import wyciągu`.
- Format: CSV mBank (`lista_operacji_*.csv`), UTF-8 BOM, `;`, preambuła do `#Data operacji;...`.
- Tylko wpływy (`amount > 0`) idą do UI dopasowań; wydatki mogą być zapisane, ale nie są przeglądane w MVP.
- Filtry przeglądu: `Do przeglądu`, `Bez powiązania`, `High`, `Medium`, `Low`, `Zaakceptowane`, `PayNow`, `Ignorowane`, `Wszystkie wpływy`.
- Przycisk **Ignoruj wypłaty PayNow** (modal Bootstrap): masowo oznacza wpływy rozpoznane pozytywnie jako rozliczenie bramki (`MELEMENTS` albo `WYPŁATA ŚRODKÓW` + `PON-…`). Trafiają do zakładki **PayNow** (powód `gateway_payout_paynow`), **nie** do ogólnego „Ignorowane”. **Nie** używa braku FV/KSeF — przelewy klientów bez numeru zostają w kolejce. Zaakceptowane nie są ruszane.
- Deduplikacja: `bank_transactions.fingerprint` (data + kwota + opis znormalizowany).
- Wydajność: lookup FV/KSeF/NIP/spraw ładowany raz do pamięci + bulk insert; limit czasu requestu podniesiony do 600 s (duże CSV ~5k wierszy).
- Konflikty: jeśli w tytule jest **inny KSeF** niż na zamówieniu → max **Low** (`ksef_mismatch`); jeśli nadawca z wyciągu nie pasuje do nabywcy/odbiorcy → obniżenie High→Medium (`party_name_mismatch`) — typowy błędny numer FV w tytule.
- **Priorytet KSeF:** gdy w tytule/opisie bankowym jest nr KSeF (również bez etykiety `KSeF`) i istnieje zamówienie z tym numerem → tylko ta sugestia (FV z tytułu może być błędna). Jeśli FV z tytułu ≠ FV na zamówieniu → **Medium** przy zgodnej kwocie (`invoice_number_mismatch`). Gdy KSeF z tytułu **nie ma** w DB → fallback na numer FV (jak wcześniej). Numer zamówienia z tytułu (`zamówienie nr 7431`, `zam. nr …`, `#4587`, `# 4587`, `order no …`) też daje sugestię.
- Akceptacja: wymaga istniejącej sprawy **lub** `form_order_id` (wtedy tworzy sprawę jak w `collectionsStore`); wpis historii typu `bank_match`; sekcja „Wpłaty z wyciągu” na karcie sprawy.
- Ręczne powiązanie od strony sprawy: sekcja „Wpłaty z wyciągu” jest bezpośrednio pod „Dane sprawy” / „Status operacyjny” i **domyślnie zwinięta** (belka + chevron); po rozwinięciu widać wyszukiwanie i zaakceptowane wpłaty. Kandydatów **nie** ładujemy przy wejściu. Wyszukiwanie AJAX: `GET accounting.collections.bank-transactions.search` (`bank_search` min. 2 znaki, opcjonalnie `bank_amount`, `bank_after_order`, `bank_unlinked_only`, `bank_search_exact`) zwraca JSON bez przeładowania strony; domyślnie w formularzu włączony filtr `operation_date >= order_date` oraz **tylko nieprzypisane** wpływy (`bank_unlinked_only=1`). Odznaczenie „Szukaj tylko w nieprzypisanych” przeszukuje wszystkie wpływy — już zaakceptowane/ignorowane są w wynikach bez przycisków powiązania. Checkbox **Szukaj dokładnie wpisanego numeru** (`bank_search_exact`) wyłącza dopasowanie fragmentu (np. `63/6/2026` ≠ `263/6/2026`); bez niego nadal działa wyszukiwanie po fragmencie (np. nazwa szkoły). Stany checkboxów wyszukiwarki (`bank_unlinked_only`, `bank_after_order`, `bank_search_exact`) są zapamiętywane w `localStorage` (`accounting_collections_bank_filters_v1`). Ikony lupy przy FV / KSeF wstawiają numer do pola wyszukiwania (czyszcząc poprzednią treść), zaznaczają dokładne dopasowanie, rozwijają sekcję i uruchamiają szukanie. Przy polu „Szukaj przelewu” ikona ✕ czyści treść pola (bez resetu kwoty/filtrów). `POST accounting.collections.bank-transactions.link` tworzy ręczny match (`manual_case_link`) i akceptuje go dla bieżącej sprawy.
- Ręczne powiązanie od strony przelewu: w modalu podglądu importu panel „Powiąż ręcznie” (`GET accounting.bank-imports.lookup-cases` — niezamknięte sprawy + zamówienia bez aktywnej sprawy; FV/KSeF/NIP/nazwa/adres/miasto/e-mail/ID, bez rozróżniania wielkości liter; opcjonalnie `exact=1` — dokładny numer FV/KSeF/NIP bez fragmentu, nazwy/adresy nadal po fragmencie; stan checkboxa w `localStorage`; przy polu wyszukiwania ikona ✕ czyści treść, wyniki i status) + `POST .../transactions/{tx}/link-case` (`debt_case_id` albo `form_order_id`; dla zamówienia tworzy sprawę). W wynikach tylko ikona oka (po lewej); przyciski **Zamówienie/Sprawa**, **Powiąż lokalnie**, **+ wpłata iFirma** pojawiają się w prawej kolumnie po podglądzie kandydata. Ikona oka przy wyniku (zamówienie lub sprawa z `form_order_id`) ładuje podgląd (`GET accounting.bank-imports.lookup-order-preview`); aktywne oko wyróżnione; **Wyczyść** przywraca oryginalną sugestię przelewu. Dla zaakceptowanego lokalnie dopasowania można ponowić samą rejestrację wpłaty przez `POST .../matches/{match}/register-ifirma-payment`.
- Ostrzeżenie przed akceptacją przy `amount_mismatch` (modal Bootstrap): jedno powiązanie przelewu z FV, odrzucenie innych sugestii, znikanie z kolejki — ryzyko przy jednym przelewie za kilka faktur; **bez** rejestracji w iFirma.
- Przy **zgodnej kwocie**: modal wyboru — **Akceptuj + wpłata w iFirma** (POST `faktury/wplaty/...` + odświeżenie statusu na sprawie) albo **Tylko lokalnie**.
- Przy fakturze krajowej, gdy przelew jest wcześniejszy niż data wystawienia FV, rejestracja wpłaty w iFirma idzie bez pola `Data` (API wymaga tylko `Kwota`), aby nie blokować opłacenia faktury wystawionej po otrzymaniu przelewu.
- W podglądzie sugestii: **Sprawdź status z iFirma** (bez tworzenia sprawy, jeśli jeszcze jej nie ma). Gdy iFirma zwraca `Opłacona`, operator może wybrać **Zaakceptuj jako opłacone w iFirma** — lokalny `bank_match`, bez rejestracji kolejnej wpłaty.
- **Auto-zamknięcie sprawy** (`DebtCaseAutoCloseService`): po **Akceptuj + wpłata w iFirma** gdy sync = `oplacone`, oraz po **Zaakceptuj jako opłacone w iFirma** (`ifirma_already_paid`). Pomija `disputed` i już `closed`. Powód: „Zamknięto automatycznie — FV opłacona w iFirma po akceptacji przelewu z wyciągu.”
- Ikona oka przy wierszu → modal Bootstrap z porównaniem **przelew z wyciągu** ↔ **zamówienie/sugestia** (FV z opisu, KSeF z tytułu, nabywca, NIP, e-mail, podstawa dopasowania).
- Pliku produkcyjnego z PII **nie** commitować; fixture testowa: `tests/fixtures/bank/mbank_sample.csv`.

Testy: `--filter=MbankStatementParserTest`, `--filter=PaymentTitleExtractorTest`, `--filter=BankTransactionMatcherTest`, `--filter=BankStatementImportTest`.

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
  - w menu Księgowość jedna pozycja **Windykacja**; **Import wyciągu**, **Lookup faktury** i **Ustawienia** są przyciskami u góry listy spraw (trasy bez osobnych pozycji w menu),
  - ustawienia ogólne: `/accounting/collections/settings` (`debt_collection_settings`) — m.in. telefon kontaktowy do stopki e-maili windykacyjnych,
  - domyślny filtr listy: **Niezamknięte** (`status=active` / `DebtCase::active()`); kafelek „Aktywne sprawy” ustawia ten filtr; opcja „Wszystkie” to `status=all` (puste `status=` też działa wstecznie); paginacja zachowuje `search` / `status` / `segment` / `sort` / `dir` przez `appends()`,
  - sortowanie nagłówków tabeli (ikony strzałek): **Sprawa** (`id`), **Zamówienie / faktura** (FV `nr/miesiąc/rok` → rok, potem miesiąc, potem numer), **Termin** (`due_date`); parametry `sort` + `dir`,
  - wyszukiwarka listy: FV/KSeF/nazwa/NIP/e-mail oraz numeryczne ID sprawy / zamówienia (`form_orders.id`),
  - kolumna Status na liście: kolorowe badge (`DebtCase::statusBadgeClass()` — Nowa niebieski, W toku cyan, Obietnica żółty, Sporne czerwony, Wstrzymane szary, Zamknięte zielony),
- tworzenie sprawy z `form_orders.id`,
  - **wymagana wystawiona FV** (`invoice_number` lub `ifirma_invoice_id`) — bez FV backend blokuje; na `/form-orders/{id}` przycisk jest nieaktywny,
  - **modal Bootstrap** przed utworzeniem (zamówienie, form-orders, lookup / lista windykacji — nie natychmiastowy POST),
  - **Usuń błędną sprawę** (soft delete) na karcie sprawy, gdy brak zaakceptowanego przelewu z wyciągu; ponowne utworzenie przywraca soft-deleted (`withTrashed` + restore); soft-deleted sprawy są też w `/trash` (filtr „Sprawy windykacyjne”) — przywrócenie lub trwałe usunięcie (cascade akcji/kontaktów; PDF kasowany z dysku),
- przy tworzeniu sprawy: wyszukiwarka numeru faktury / KSeF z przyciskami „Utwórz sprawę” / „Otwórz sprawę” / „Wstaw ID” (korzysta z `accounting.debtors.lookup`),
- na `/accounting/debtors`: numer zamówienia nad fakturą oraz skrót do utworzenia/otwarcia sprawy windykacyjnej,
- historię działań: notatka, e-mail, SMS, telefon, iFirma, obietnica płatności, sporne, wstrzymanie, zamknięcie,
- **wysyłka e-maila z karty sprawy**: przycisk „Wyślij przypomnienie” → modal (jak linki do prowadzącego) z szablonami przypomnienie/ponaglenie, edycją treści, **checkboxami odbiorców** (zamawiający / uczestnik / kontakty sprawy — domyślnie wszystkie zaznaczone; można odznaczyć; opcjonalnie dodatkowy e-mail), wysyłką właściwą i testową, opcjonalnym **linkiem do potwierdzenia zamówienia** (publiczny PDF na pnedu `/orders/{ident}/pdf`, ten sam co po złożeniu zamówienia; **domyślnie odznaczony**; brak `ident` = pomijamy), PDF FV: domyślnie **tylko ze sprawy** gdy wgrany, inaczej **tylko z iFirma** (wzajemnie nie oba naraz jako domyślne) + opcjonalny upload PDF; wpis w historii `email`; VIP / `do_not_auto_dun` = ostrzeżenie, bez blokady; SMS później,
- **PDF faktury na sprawie**: upload w sekcji „PDF faktury” (`debt_cases.invoice_pdf_*`, dysk `local`); podgląd w modalu iframe + otwarcie w nowej karcie; zastąpienie / usunięcie (modal Bootstrap); na liście spraw ikona PDF obok numeru FV (link do podglądu), gdy plik jest załączony; **przy zamknięciu sprawy** plik i metadane PDF są usuwane z dysku (`DebtCaseObserver`),
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

Na karcie sprawy (`/accounting/collections/{id}`) sekcja **Historia powiązanych zamówień** pokazuje w kolumnie **ID** (pod numerem zamówienia):
- badge sprawy: `ta sprawa` (bieżąca) albo link do innej aktywnej/zamkniętej sprawy (tooltip wyjaśnia znaczenie),
- badge’e powodów powiązania (`linkReasonsForRelatedOrder`; tooltip z opisem reguły i wartością),
oraz nad tabelą linię **Identyfikacja:** (`identitySummary`). Bez osobnych kolumn Powiązanie / Sprawa.

## Synchronizacja statusu płatności z iFirma (odczyt)

**Dokumentacja API iFirma (kanon):** [docs/IFIRMA_API.md](./IFIRMA_API.md) → [https://api.ifirma.pl/](https://api.ifirma.pl/)

Endpoint: `POST /accounting/collections/{debtCase}/sync-ifirma`  
Serwis: `App\Services\IfirmaInvoicePaymentStatusService`

1. Jeśli `form_orders.ifirma_invoice_id` jest ustawione → `GET fakturakraj/{id}.json`.
2. W przeciwnym razie (**dwukrok**, bo API listy wymaga `dataOd` i **nie filtruje po numerze FV** — [lista faktur](https://api.ifirma.pl/lista-faktur/)):
   1. `GET faktury.json` w kolejnych oknach dat od najciaśniejszego: **miesiąc z numeru FV** → normalny (±14/45 z datami zamówienia) → szeroki (±60/120); przy każdym oknie najpierw filtr `kwotaOd`/`kwotaDo` (gdy znamy kwotę), potem bez; typ krajowy, potem bez typu; dopasowanie po `PelnyNumer` lub numerze KSeF; limit stron listy podniesiony (żeby nie ucinać w środku miesiąca),
   2. po znalezieniu `FakturaId` → `GET fakturakraj/{id}.json` (pewniejsze `Zaplacono` / `WartoscBrutto`); gdy szczegóły padną — fallback do wiersza z listy,
   3. zapis `ifirma_invoice_id` na zamówieniu na kolejne syncy.
   Komunikat błędu rozróżnia „przeszukano całą listę” vs „osiągnięto limit stron” i podpowiada uzupełnienie ID iFirma.
3. Status wyliczany z `Zaplacono` / `Brutto`|`WartoscBrutto` / `TerminPlatnosci`: `oplacone`, `oplaconeCzesciowo`, `nieoplacone`, `przeterminowane`.
   (Lista faktur zwraca `Brutto`; szczegóły `fakturakraj/{id}` — `WartoscBrutto`.)
4. Zapis cache na `debt_cases` + wpis historii `ifirma_sync`.
5. Sync **zawsze nadpisuje** na sprawie `invoice_date` ← `DataWystawienia` oraz `due_date` ← `TerminPlatnosci` (gdy API je zwraca) — nie zostawia szacunku `order_date + delay`.
6. Te same daty zapisuje też na zamówieniu: `form_orders.invoice_issue_date` / `invoice_due_date` (oraz uzupełnia `ifirma_invoice_id` / `invoice_number` gdy brak).
7. Na karcie sprawy **„Odśwież status z iFirma”**: gdy sync zwróci `oplacone` i sprawa nie jest `closed`/`disputed` → **auto-zamknięcie** (`DebtCaseAutoCloseService`, powód: po odświeżeniu statusu). Flash: „… Sprawę zamknięto automatycznie.”
8. **Auto-zamknięcie po akceptacji przelewu z wyciągu** (osobna ścieżka): gdy status iFirma = `oplacone` po **Akceptuj + wpłata w iFirma** albo po **Zaakceptuj jako opłacone w iFirma** / fladze `ifirma_already_paid` — sprawa dostaje `close` + `closed_at`, o ile nie jest już `closed` ani `disputed`. „Tylko lokalnie” bez potwierdzenia pełnej opłaty **nie** zamyka.

Daty FV na zamówieniu są też zapisywane **przy wystawianiu** faktury w panelu (`DataWystawienia` / `TerminPlatnosci` z payloadu). Tworzenie sprawy preferuje `invoice_issue_date` / `invoice_due_date`; dopiero gdy brak — szacunek od daty zamówienia/wystawienia + `invoice_payment_delay`.

**Auto-sync przy utworzeniu sprawy:** po `collections.store` (lista windykacji, debtors, form-orders) oraz po utworzeniu sprawy przy akceptacji/ręcznym powiązaniu przelewu z bank-imports (`BankStatementImportService::createDebtCaseFromOrder`) wywoływany jest ten sam sync statusu iFirma (best-effort; przy błędzie API sprawa powstaje, flash `warning`). Przywrócenie soft-deleted sprawy też odświeża status.

Testy: `--filter=IfirmaInvoicePaymentStatusServiceTest`, `--filter=AccountingCollectionsTest`.

## Publiczny Formularz

W publicznym formularzu zamówienia nigdy nie pokazujemy informacji o zaległości, windykacji, ryzyku ani klasyfikacji klienta.

Przyszły etap może dyskretnie wymuszać płatność online przez ukrycie lub wyłączenie opcji odroczonej faktury, szczególnie dla osoby prywatnej z wysokim ryzykiem. Reguła musi być walidowana backendowo.

## Kolejne Etapy

- ~~synchronizacja statusów faktur z iFirma API~~ — **wdrożone (odczyt)**: przycisk „Odśwież status z iFirma” na karcie sprawy **oraz automatycznie przy tworzeniu sprawy**; cache w `debt_cases.ifirma_payment_status` / `ifirma_synced_at`; preferowany klucz `form_orders.ifirma_invoice_id`, fallback lista `faktury.json` po `PelnyNumer`; przy syncu karty gdy `oplacone` → auto-zamknięcie (jak wyciąg),
- ~~import CSV wyciągów bankowych~~ — **wdrożone (MVP mBank)**: `Księgowość → Import wyciągu` (`accounting.bank-imports.*`); tabele `bank_statement_imports` / `bank_transactions` / `bank_transaction_matches`; parser `MbankStatementParser`; sugestie FV / KSeF / `#ID` / NIP / **imię+nazwisko nabywcy (tylko FV bez NIP) + kwota** → Medium; ręczna akceptacja → lokalny link + `debt_case_actions.bank_match`; przy zgodnej kwocie opcjonalnie **rejestracja wpłaty w iFirma** + sync statusu,
- ~~ręczne powiązanie przelewu od strony sprawy~~ — **wdrożone**: na karcie sprawy sekcja „Wpłaty z wyciągu” ma wyszukiwarkę niepowiązanych wpływów (domyślnie po kwocie sprawy + fraza z opisu/nadawcy/konta) i modal Bootstrap „Powiąż lokalnie” / „+ wpłata iFirma”,
- ~~cofnięcie przypisania przelewu~~ — **wdrożone**: „Cofnij” na karcie sprawy + „Cofnij przypisanie” w imporcie; lokalnie match → `rejected`, historia `bank_unmatch`, zamknięta sprawa → `open`; best-effort usunięcie wpłaty w iFirma (`DELETE faktury/wplaty/...`, oficjalne API dokumentuje tylko POST — przy failu ostrzeżenie i ręczna korekta w iFirma),
- ~~ręczne powiązanie od strony przelewu~~ — **wdrożone**: w modalu podglądu importu wyszukiwarka niezamkniętych spraw + powiązanie lokalne / + iFirma,
- dopracowanie sugestii dopasowań (fuzzy nazwa, bulk),
- ~~ewentualne rejestrowanie wpłat w iFirma dopiero po potwierdzeniu operatora~~ — **wdrożone** (modal przy akceptacji importu: „Akceptuj + wpłata w iFirma” / „Tylko lokalnie”),
- ~~automatyczne zamykanie spraw po matchu~~ — **wdrożone**: po akceptacji z wyciągu **oraz** po „Odśwież status z iFirma” na karcie, gdy iFirma = `oplacone`; nie zamyka `disputed` / już `closed`; zwykłe „Tylko lokalnie” bez potwierdzenia opłaty nie zamyka,
- ~~magazyn PDF FV na sprawie (pobranie przy utworzeniu + checkbox „załącz FV ze sprawy” przy wysyłce)~~ — **częściowo wdrożone**: ręczny upload + podgląd na karcie sprawy + checkbox „Załącz PDF faktury ze sprawy” przy wysyłce; auto-pobranie z iFirma przy tworzeniu sprawy — później,
- SMS do dłużnika (ten sam flow co e-mail, inny kanał).

## Import wyciągu mBank (MVP)

- Menu: `Księgowość → Import wyciągu`.
- Lista importów: nagłówki kolumn mają tooltipy Bootstrap (ID, plik, okres, wpływy/wszystkie, sugestie, duplikaty, **przegląd**, kto, kiedy, akcje).
- **Przegląd w tabeli:** kolumna pokazuje, czy wpływy z importu mają już decyzję operatora — `Przejrzany` (każdy wpływ accepted/ignored), `Do przeglądu: N` albo `Brak wpływów`. To nie jest status parsowania pliku („Sparsowany”).
- **Luki dat (okresy wyciągów):** na liście importów alert z lukami między polami `#Za okres` ze wszystkich importów (nakładające się / stykające się okresy są łączone); zgłaszany jest też brak pokrycia od końca ostatniego okresu do dziś. Po nowym imporcie ostrzeżenie flash, jeśli luki istnieją. Serwis: `BankStatementCoverageService`.
- Format: CSV mBank (`lista_operacji_*.csv`), UTF-8 BOM, `;`, preambuła do `#Data operacji;...`.
- Tylko wpływy (`amount > 0`) idą do UI dopasowań; wydatki mogą być zapisane, ale nie są przeglądane w MVP.
- Filtry przeglądu: `Do przeglądu`, `Bez powiązania`, `High`, `Medium`, `Low`, `Zaakceptowane`, `PayNow`, `Ignorowane`, `Wszystkie wpływy`.
- **UX ładowania:** przyciski długich operacji (sync iFirma, import CSV, przeliczenie sugestii, akceptacje, powiązania) pokazują spinner Bootstrap (`data-loading-submit` / `window.PneButtonLoading`) — szczegóły w `docs/UI_MODALS.md`.
- Przycisk **Ignoruj wypłaty PayNow** (modal Bootstrap): masowo oznacza wpływy rozpoznane pozytywnie jako rozliczenie bramki (`MELEMENTS` albo `WYPŁATA ŚRODKÓW` + `PON-…`). Trafiają do zakładki **PayNow** (powód `gateway_payout_paynow`), **nie** do ogólnego „Ignorowane”. **Nie** używa braku FV/KSeF — przelewy klientów bez numeru zostają w kolejce. Zaakceptowane nie są ruszane.
- Deduplikacja: `bank_transactions.fingerprint` (data + kwota + opis znormalizowany).
- Wydajność: lookup FV/KSeF/NIP/spraw ładowany raz do pamięci + bulk insert; limit czasu requestu podniesiony do 600 s (duże CSV ~5k wierszy).
- Konflikty: jeśli w tytule jest **inny KSeF** niż na zamówieniu → max **Low** (`ksef_mismatch`); jeśli nadawca z wyciągu nie pasuje do nabywcy/odbiorcy → obniżenie High→Medium (`party_name_mismatch`) — typowy błędny numer FV w tytule.
- **Priorytet KSeF:** gdy w tytule/opisie bankowym jest nr KSeF (również bez etykiety `KSeF`) i istnieje zamówienie z tym numerem → tylko ta sugestia (FV z tytułu może być błędna). Jeśli FV z tytułu ≠ FV na zamówieniu → **Medium** przy zgodnej kwocie (`invoice_number_mismatch`). Gdy KSeF z tytułu **nie ma** w DB → fallback na numer FV (jak wcześniej). Gdy FV z tytułu występuje w notatkach zamówienia (`invoice_number_in_notes`, np. anulowana FV) → sugestia po aktualnym zamówieniu z poprawną FV. Numer zamówienia z tytułu (`zamówienie nr 7431`, `zam. nr …`, `#4587`, `# 4587`, `order no …`) też daje sugestię.
- Akceptacja: wymaga istniejącej sprawy **lub** `form_order_id` (wtedy tworzy sprawę jak w `collectionsStore`); wpis historii typu `bank_match`; sekcja „Wpłaty z wyciągu” na karcie sprawy.
- **Podział jednego przelewu na kilka FV:** kolumna `bank_transaction_matches.allocated_amount`; wiele `accepted` na jednym wpływie, o ile suma alokacji ≤ kwota przelewu (±0,01). Alokacja = min(kwota FV, wolne). Częściowe pokrycie dozwolone (reszta może zostać otwarta). Matcher: gdy w tytule ≥2 numery FV i suma kwot zamówień ≈ przelew → High + `multi_invoice_sum_match` + `amount_match` (bez `amount_mismatch`; `amount_match` przy podziale = zgodność **alokacji** z kwotą FV, nie pełnego przelewu). UI badge: `effectiveConfidence()` podnosi ręczne Low→High przy `split_allocation`+`amount_match`. Podświetlenia w podglądzie: przy podziale **zielona tylko kwota FV** (nie pełna kwota przelewu, np. 730). UI: **lista wszystkich FV pakietu** w wierszu i w podglądzie (FV · kwota · zam. · sprawa), suma = przelew, **Akceptuj pakiet** (`POST .../transactions/{tx}/accept-package`), przy częściowym pokryciu **Dodaj do podziału** / cofnięcie per sprawa. Przy pakiecie modal: **Tylko lokalnie** albo **Pakiet + wpłaty w iFirma**. Po akceptacji: w podglądzie **klikalne numery FV** w „Przypisane części” przełączają prawą kolumnę + **Zarejestruj wpłatę iFirma** dla tej alokacji; na liście „Zaakceptowane” przycisk **Wpłata iFirma** (przy podziale z dopiskiem FV) obok Cofnij; na karcie sprawy (**Wpłaty z wyciągu**) **Wpłata iFirma** + kwota = alokacja (z dopiskiem „z przelewu …”). Route: `POST accounting.collections.bank-matches.register-ifirma`. Kolejka „Do przeglądu” obejmuje też częściowo przypisane wpływy z wolną kwotą.

**Checklist ręczny (pakiet, np. import `#8`):**
1. Przelicz sugestie jeśli trzeba.
2. Znajdź przelew z 2 FV w tytule i sumą = kwota → widać listę obu FV + „Akceptuj pakiet”.
3. Podgląd (oko): ta sama lista + przycisk pakietu.
4. Akceptuj pakiet lokalnie → 2 alokacje, wolne = 0; albo „+ wpłaty w iFirma” → statusy/sprawy.
5. Po lokalnej akceptacji: klik FV w „Przypisane części” / przycisk **Wpłata iFirma** (przy podziale z dopiskiem FV) na liście / **Wpłata iFirma** na karcie sprawy → rejestracja per FV.
6. Cofnij jedną sprawę → wolna kwota wraca; da się dodać ponownie.
- Ręczne powiązanie od strony sprawy: sekcja „Wpłaty z wyciągu” jest bezpośrednio pod „Dane sprawy” / „Status operacyjny” i **domyślnie zwinięta** (belka + chevron); po rozwinięciu widać wyszukiwanie i zaakceptowane wpłaty. Kandydatów **nie** ładujemy przy wejściu. Wyszukiwanie AJAX: `GET accounting.collections.bank-transactions.search` (`bank_search` min. 2 znaki, opcjonalnie `bank_amount`, `bank_after_order`, `bank_unlinked_only`, `bank_search_exact`) zwraca JSON bez przeładowania strony; domyślnie w formularzu włączony filtr `operation_date >= order_date` oraz **tylko nieprzypisane** wpływy (`bank_unlinked_only=1`, także częściowo wolne). Odznaczenie „Szukaj tylko w nieprzypisanych” przeszukuje wszystkie wpływy — zignorowane / już powiązane z **tą** sprawą / bez wolnej kwoty są bez przycisków powiązania. Checkbox **Szukaj dokładnie wpisanego numeru** (`bank_search_exact`) wyłącza dopasowanie fragmentu (np. `63/6/2026` ≠ `263/6/2026`); bez niego nadal działa wyszukiwanie po fragmencie (np. nazwa szkoły). Stany checkboxów wyszukiwarki (`bank_unlinked_only`, `bank_after_order`, `bank_search_exact`) są zapamiętywane w `localStorage` (`accounting_collections_bank_filters_v1`). Ikony lupy przy FV / KSeF wstawiają numer do pola wyszukiwania (czyszcząc poprzednią treść), zaznaczają dokładne dopasowanie, rozwijają sekcję i uruchamiają szukanie. Przy polu „Szukaj przelewu” ikona ✕ czyści treść pola (bez resetu kwoty/filtrów). `POST accounting.collections.bank-transactions.link` tworzy ręczny match (`manual_case_link`) i akceptuje go dla bieżącej sprawy. Przy zaakceptowanych wpłatach: **Cofnij** → `POST accounting.collections.bank-matches.unlink` (modal Bootstrap).
- Ręczne powiązanie od strony przelewu: w modalu podglądu importu panel „Powiąż ręcznie” (`GET accounting.bank-imports.lookup-cases` — niezamknięte sprawy + zamówienia bez aktywnej sprawy; FV/KSeF/NIP/nazwa/adres/miasto/e-mail/notatki zamówienia (`notes`, `invoice_notes`, m.in. numer anulowanej FV)/ID, bez rozróżniania wielkości liter; opcjonalnie `exact=1` — dokładny numer FV/KSeF/NIP bez fragmentu, nazwy/adresy/notatki nadal po fragmencie (dla numeru FV w notatkach — dopasowanie granic tokena); stan checkboxa w `localStorage`; przy polu wyszukiwania ikona ✕ czyści treść, wyniki i status) + `POST .../transactions/{tx}/link-case` (`debt_case_id` albo `form_order_id`; dla zamówienia tworzy sprawę). W wynikach tylko ikona oka (po lewej); przyciski **Zamówienie/Sprawa**, **Powiąż lokalnie**, **+ wpłata iFirma**, **Sprawdź status z iFirma** pojawiają się w prawej kolumnie po podglądzie kandydata. Status iFirma dla kandydata bez matcha: `POST accounting.bank-imports.lookup-ifirma-status` (`form_order_id` / `debt_case_id`). Ikona oka przy wyniku (zamówienie lub sprawa z `form_order_id`) ładuje podgląd (`GET accounting.bank-imports.lookup-order-preview`); aktywne oko wyróżnione; **Wyczyść** przywraca oryginalną sugestię przelewu. Dla zaakceptowanego lokalnie dopasowania można ponowić samą rejestrację wpłaty przez `POST .../matches/{match}/register-ifirma-payment`. Cofnięcie: **Cofnij przypisanie** → `POST .../matches/{match}/unlink` (lista + podgląd).
- **Cofnięcie przypisania** (`BankTransactionUnlinkService`): match `accepted` → `rejected` (przelew znów wolny); historia `bank_unmatch`; zamknięta sprawa → `open`; best-effort usunięcie wpłaty w iFirma + sync; przy niepowodzeniu API — flash warning z prośbą o ręczną korektę w iFirma.
- Przy **zgodnej kwocie**: modal wyboru — **Akceptuj + wpłata w iFirma** (POST `faktury/wplaty/...` + odświeżenie statusu na sprawie) albo **Tylko lokalnie**.
- Przy fakturze krajowej, gdy przelew jest wcześniejszy niż data wystawienia FV, rejestracja wpłaty w iFirma idzie bez pola `Data` (API wymaga tylko `Kwota`), aby nie blokować opłacenia faktury wystawionej po otrzymaniu przelewu.
- W podglądzie sugestii / kandydata ręcznego: **Sprawdź status z iFirma** (bez tworzenia sprawy, jeśli jeszcze jej nie ma). Panel pokazuje też datę wystawienia FV, liczbę dni po terminie (gdy nieopłacona i po `TerminPlatnosci`) oraz istniejącą sprawę windykacyjną z linkiem. Przy numerze FV w prawej kolumnie widać datę wystawienia z zamówienia (`invoice_issue_date`), gdy jest. Gdy iFirma zwraca `Opłacona`, operator może wybrać **Zaakceptuj jako opłacone w iFirma** — lokalny `bank_match`, bez rejestracji kolejnej wpłaty.
- **Auto-zamknięcie sprawy** (`DebtCaseAutoCloseService`): po **Akceptuj + wpłata w iFirma** gdy sync = `oplacone`, po **Zaakceptuj jako opłacone w iFirma** (`ifirma_already_paid`), oraz po **Odśwież status z iFirma** na karcie gdy wynik = `oplacone`. Pomija `disputed` i już `closed`. Powody: akceptacja wyciągu vs odświeżenie statusu (osobne stałe `CLOSURE_REASON` / `CLOSURE_REASON_IFIRMA_SYNC`).
- Ikona oka przy wierszu → modal Bootstrap z porównaniem **przelew z wyciągu** ↔ **zamówienie/sugestia** (FV z opisu, KSeF z tytułu, nabywca, NIP, e-mail, podstawa dopasowania).
- **Usuwanie importu:** na liście / w podglądzie przycisk **Usuń** (modal), gdy brak zaakceptowanych powiązań na przelewach tego importu. Pusty import (same duplikaty) zawsze wolno usunąć. Ponowne wgranie tego samego pliku **nie** tworzy już pustego rekordu — komunikat z odesłaniem do wcześniejszego importu.
- Pliku produkcyjnego z PII **nie** commitować; fixture testowa: `tests/fixtures/bank/mbank_sample.csv`.

Testy: `--filter=MbankStatementParserTest`, `--filter=PaymentTitleExtractorTest`, `--filter=BankTransactionMatcherTest`, `--filter=BankStatementImportTest`, `--filter=BankTransactionUnlinkTest`, `--filter=BankStatementCoverageServiceTest`.

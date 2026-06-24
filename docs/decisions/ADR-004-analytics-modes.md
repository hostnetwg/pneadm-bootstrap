# ADR-004: Tryby Analityki I Sampling

Data utworzenia/aktualizacji: 2026-06-24  
Status: zaakceptowane koncepcyjnie, do potwierdzenia przez właściciela

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

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalne miejsce konfiguracji trybów.
- Dopisać UI w `adm.pnedu.pl`.
- Dopisać zasady alertów przy `off`.
- Dopisać realne progi samplingowe po testach ruchu.

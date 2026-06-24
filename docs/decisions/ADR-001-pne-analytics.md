# ADR-001: Osobna Baza `pne_analytics`

Data utworzenia/aktualizacji: 2026-06-24  
Status: zaakceptowane koncepcyjnie, do potwierdzenia przez właściciela

## Kontekst

Ekosystem PNEdu składa się z dwóch aplikacji Laravel:

- `pnedu.pl`,
- `adm.pnedu.pl`.

Dane biznesowe znajdują się głównie w `pneadm`. Portal `pnedu.pl` posiada własną bazę `pnedu`, ale korzysta też z danych biznesowych w `pneadm`.

Planowana analityka eventowa będzie generować duży wolumen danych:

- eventy kampanii,
- eventy formularza,
- eventy płatności,
- eventy testów A/B,
- porzucenia,
- agregaty dzienne,
- snapshoty AI-safe.

## Decyzja

Tworzymy osobną bazę danych:

```text
pne_analytics
```

Baza ta będzie przeznaczona wyłącznie do analityki, agregatów, testów A/B, eksportów AI-safe i przyszłego AI-doradcy.

## Uzasadnienie

Osobna baza:

- oddziela dane operacyjne od analitycznych,
- zmniejsza ryzyko spowolnienia zamówień,
- ułatwia retencję raw eventów,
- ułatwia backup i archiwizację,
- porządkuje odpowiedzialność danych,
- ogranicza ryzyko przypadkowego zapisania danych osobowych w raportach,
- przygotowuje system pod przyszłe AI.

## Alternatywy

### Zapisywanie W `pneadm`

Plusy:

- prostsza konfiguracja,
- jeden connection,
- łatwiejszy dostęp do danych biznesowych.

Minusy:

- większe ryzyko obciążenia bazy operacyjnej,
- mieszanie danych transakcyjnych z analityką,
- trudniejsza retencja,
- większy chaos w migracjach,
- większe ryzyko RODO.

### Pliki Tekstowe / Logi

Plusy:

- szybki start,
- brak migracji.

Minusy:

- trudne raportowanie,
- trudne agregaty,
- trudne czyszczenie,
- trudne debugowanie,
- wysokie ryzyko danych osobowych w logach.

### Zewnętrzna Analityka

Plusy:

- gotowy dashboard,
- mniej kodu.

Minusy:

- mniejsza kontrola nad danymi,
- ryzyko RODO,
- ograniczona integracja z zamówieniami, fakturami i kampaniami,
- koszt,
- zależność od dostawcy.

## Konsekwencje

Pozytywne:

- czystsza architektura,
- lepsza skalowalność,
- łatwiejsze eksporty AI-safe,
- możliwość ograniczenia retencji raw eventów.

Negatywne:

- więcej konfiguracji,
- osobne migracje,
- osobne backupy,
- konieczność synchronizacji identyfikatorów z `pneadm`.

## Zasady

- Nie stosować twardych FK do `pneadm`.
- Zapisywać identyfikatory i snapshoty bez danych osobowych.
- Nie zapisywać PII.
- Zapisywać eventy asynchronicznie.

## Do Aktualizacji Po Wdrożeniu

- Dopisać finalną nazwę connection.
- Dopisać finalny sposób tworzenia bazy na produkcji.
- Dopisać procedurę backupu i retencji.
- Zmienić status na `zaakceptowane` po potwierdzeniu właściciela.

# PNE Operations — wizja

Status: kierunek biznesowy opisany przez Waldemara 2026-10-17. To nie jest opis działającego systemu. Stan kodu: [CURRENT.md](./CURRENT.md).

## Problem

PNE rośnie. Powtarzalne czynności — webinar, przygotowanie szkolenia, obsługa po szkoleniu, klienci, korekty faktur, zapytania, szkolenia zamknięte dla rad pedagogicznych — są dziś głównie w pamięci Waldemara i Łukasza. Trenerów jest wielu. Nowy pracownik nie ma jednego miejsca, z którego da się przejąć sprawę.

## Cel

`adm.pnedu.pl` ma stopniowo stać się wewnętrznym systemem operacyjnym. Dla każdej sprawy da się odpowiedzieć:

1. Co się teraz dzieje?
2. Po czyjej stronie jest następny krok?
3. Co trzeba zrobić?
4. Do kiedy?
5. Jak to zrobić (instrukcja, SOP, materiał)?
6. Co wydarzyło się wcześniej?

Docelowy łańcuch:

```text
sprawa / obiekt biznesowy
  → proces (później)
  → etapy i zadania
  → osoba odpowiedzialna
  → termin
  → instrukcja
  → wykonanie
  → historia
```

Firma ma dać się zastąpić w operacjach: odejście jednej osoby nie zabiera wiedzy o otwartych sprawach.

## Zasada rozwoju

System ma być prosty (DEC-OPS-011). Najpierw realny problem, potem najmniejsze rozwiązanie, potem codzienne użycie, potem obserwacja. Kolejna funkcja albo abstrakcja dochodzi dopiero z praktyki Waldemara, Łukasza i trenerów. Nie budujemy narzędzia pod potrzeby, których jeszcze nie ma.

## Pierwszy proces: szkolenie zamknięte

Dyrektor szkoły pyta o szkolenie. PNE ustala potrzeby, pyta trenera (czy chce, za ile, kiedy), wraca do szkoły. Wymiana może iść kilka razy. Dopiero po uzgodnieniu powstaje konkretne szkolenie.

Na etapie negocjacji **nie ma jeszcze szkolenia**. Centralnym miejscem ma być karta sprawy, nie skrzynka pocztowa i nie rekord `courses`.

Po potwierdzeniu człowiek tworzy albo wiąże `courses`. System nie robi tego sam.

## Portal trenera

Kierunek już zapisany w [INSTRUCTOR_PORTAL.md](../INSTRUCTOR_PORTAL.md): ograniczone konto na pnedu.pl, bez ADM.

Późniejsze funkcje trenera:

- propozycje nowych szkoleń,
- kalendarz własnych szkoleń,
- materiały do własnego szkolenia,
- wyniki ankiet własnych szkoleń,
- odpowiedź na sprawę szkolenia zamkniętego: zainteresowanie, wynagrodzenie, terminy, komentarz.

Odpowiedź wraca do tej samej sprawy po stronie PNE. Trener nie dostaje karty sprawy. Dostaje brief przygotowany przez PNE (DEC-OPS-007) i widzi tylko własne szkolenia.

## Procesy są kierunkiem, nie projektem na teraz

Sprawa i proces to dwa pojęcia. Sprawa szkolenia zamkniętego jest pierwszym konkretnym obiektem. Windykacja i późniejszy webinar zostają własnymi obiektami. Wspólna warstwa, jeśli powstanie po praktyce, tylko wskazuje istniejący obiekt (`subject_type` + `subject_id`). Nie jest przesądzonym silnikiem do zbudowania z góry.

Pierwszy przebieg do nauczenia się w pracy: obsługa szkolenia zamkniętego, na początku ręcznie, na karcie sprawy. Organizacja webinaru otwartego może być drugim przypadkiem, gdy pierwszy już działa. Jej kształt ma wyniknąć z tego doświadczenia, nie z modelu wymyślonego wcześniej.

Krytyczne decyzje (cena, wybór trenera, potwierdzenie szkoły, utworzenie szkolenia) zostają przy człowieku.

## Czego świadomie nie robimy na początku

- Wielkiego silnika procesów „na zapas”.
- Jednej tabeli na wszystkie byty firmy i pola `type` „na później”.
- Dawania trenerom panelu ADM albo pełnej karty sprawy.
- Zastępowania Growth OS albo wciągania operacji firmy do Growth OS.
- Automatycznego tworzenia `courses` z zapytania.
- Automatycznego zakładania konta trenera z samego adresu e-mail albo z flagi przy koncie uczestnika.

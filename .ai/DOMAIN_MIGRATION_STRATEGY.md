# Strategia migracji: pnedu.pl -> nowoczesna-edukacja.pl

Ten dokument utrwala aktualny kontekst biznesowy i techniczny migracji z platformy opartej o Publigo (`nowoczesna-edukacja.pl`) do własnego systemu Laravel (`pnedu.pl`), tak aby kolejne osoby i agenci AI mogły kontynuowac prace bez utraty kontekstu.

## Kontekst i powod migracji

- Dotychczasowa platforma (`nowoczesna-edukacja.pl` na Publigo) ogranicza automatyzacje i rozwoj.
- Glowna dzialalnosc firmy to szkolenia online na zywo, ktore slabiej pasuja do modelu Publigo.
- Wlasna platforma (`pnedu.pl`) daje pelna kontrole nad kodem, procesami i integracjami.

## Stan obecny (maj 2026)

- Szkolenia online na zywo (z pozniejszym dostepem do nagran) sa juz realizowane na `pnedu.pl`.
- Na `nowoczesna-edukacja.pl` wciaz dzialaja historyczne kursy online (nagrania) z dozywotnim dostepem.
- Nie mozna jeszcze calkowicie zamknac Publigo, bo czesc klientow musi zachowac dostep do zakupionych tresci.

## Cel docelowy

1. Rozszerzyc `pnedu.pl` o brakujace funkcje:
   - kursy online nagraniowe,
   - produkty cyfrowe,
   - uslugi,
   - (opcjonalnie w przyszlosci) produkty fizyczne.
2. Przeniesc konta i dostepy klientow z Publigo do nowej platformy.
3. Wylaczyc instancje Publigo po zakonczonej migracji.
4. Ustalic jedna domene kanoniczna dla calego serwisu.

## Rekomendacja domenowa i SEO

Rekomendowany model docelowy:

- jedna domena glowna (kanoniczna), preferencyjnie `nowoczesna-edukacja.pl` ze wzgledu na historie marki,
- druga domena (`pnedu.pl`) jako domena pomocnicza z globalnym przekierowaniem `301` na domene glowna,
- brak dlugotrwalego utrzymywania dwoch rownoleglych, indeksowanych wersji serwisu.

Dlaczego:

- mocniejsze i mniej rozproszone sygnaly SEO,
- mniej duplikacji tresci,
- mniejsze ryzyko zamieszania wsrod klientow.

## Fazy realizacji

### Faza 1: Domkniecie funkcjonalnosci w nowym systemie

- Implementacja brakujacych modulow (kursy nagraniowe, produkty cyfrowe, uslugi).
- Weryfikacja procesow:
  - platnosci,
  - dostepy i role,
  - fakturowanie,
  - maile transakcyjne,
  - obsluga posprzedazowa.

### Faza 2: Migracja danych i dostepow

- Mapowanie danych: uzytkownik, historia zamowien, uprawnienia, statusy.
- Osobna polityka dla dozywotnich dostepow (krytyczne biznesowo i prawnie).
- Obsluga przypadkow specjalnych:
  - duplikaty email,
  - zwroty/reklamacje,
  - niepelne profile.

### Faza 3: Przygotowanie techniczne SEO przed cutoverem

- Inwentaryzacja URL-i starego serwisu.
- Mapowanie `1:1` stary URL -> nowy URL.
- Przygotowanie przekierowan `301` na poziomie domeny i konkretnych stron.
- Aktualizacja:
  - `sitemap.xml` (dynamicznie przez kontroler SEO),
  - linkowania wewnetrznego,
  - Search Console,
  - analityki (GA/GTM).

### Faza 4: Przelaczenie domeny (cutover)

- Aktywacja domeny kanonicznej.
- Wlaczenie globalnych przekierowan `301` z domeny pomocniczej.
- Testy krytycznej sciezki:
  - logowanie,
  - checkout,
  - reset hasla,
  - dostep do kursow i materialow,
  - maile.

### Faza 5: Stabilizacja po migracji (4-12 tygodni)

- Monitoring bledow `404/500`.
- Monitoring indeksacji i widocznosci SEO.
- Dodawanie brakujacych przekierowan `301`.
- Komunikacja z klientami (mailing + FAQ + bannery informacyjne).

## Najwazniejsze ryzyka i zabezpieczenia

- Utrata ruchu SEO:
  - pelna mapa przekierowan `301` per-URL (nie tylko strona glowna),
  - monitoring Search Console po wdrozeniu.
- Problemy z dostepami historycznymi:
  - migracja testowa na kopii danych,
  - plan rollback i recznej korekty uprawnien.
- Chaos komunikacyjny:
  - jednoznaczna komunikacja jednej domeny docelowej we wszystkich kanalach.

## Decyzje otwarte (do domkniecia)

1. Ostateczna domena kanoniczna (rekomendacja: `nowoczesna-edukacja.pl`).
2. Termin cutoveru uzalezniony od gotowosci brakujacych modulow.
3. Kryteria "go-live readiness" (lista kontrolna i ownerzy).

## Jak korzystac z tego dokumentu w kolejnych rozmowach z AI

- Na starcie rozmowy podawaj sciezke:
  - `.ai/DOMAIN_MIGRATION_STRATEGY.md`
- Dla nowych zadan dopisuj:
  - co juz jest zrobione,
  - co jest aktualnie blokada,
  - jaki jest nastepny kamien milowy.
- Traktuj ten plik jako "living document" i aktualizuj po kazdym wiekszym etapie.

## Ostatnia aktualizacja

- Data: 2026-05-09
- Status: strategia bazowa uzgodniona, realizacja etapowa w toku

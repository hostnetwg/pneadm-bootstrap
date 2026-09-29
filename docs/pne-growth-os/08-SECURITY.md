# PNE Growth OS — bezpieczeństwo i kompatybilność

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1.1
Status: wymagania obowiązkowe; zabezpieczenia Etapu 0.1 wdrożone lokalnie

## Zabezpieczenia wdrożone w Etapie 0.1

- Flaga `PNE_GROWTH_OS_ENABLED` ma domyślną wartość `false`.
- Parser konfiguracji akceptuje wyłącznie prawidłową wartość boolean; brak lub wartość nieprawidłowa daje `false`.
- Kod aplikacji korzysta z `config('growth_os.enabled')`, nie z bezpośredniego `env()`.
- Middleware wymaga dokładnie wartości `true`; każdy inny typ lub wartość kończy się HTTP 404.
- Trasa pozostaje wewnątrz istniejącej grupy `auth` i `check.user.status`.
- Autoryzacja tymczasowo używa istniejącej roli `super_admin`; nie dodano permission ani zmian danych.
- Menu stosuje te same dwa warunki co dostęp: flaga `true` i `super_admin`.
- Pulpit nie czyta bazy danych i nie wykonuje side effectu.
- Nie dodano migracji, kolejki, crona, integracji ani zmian w `pnedu.pl`.

## Niezmiennik

Jeżeli Growth OS zostanie wyłączony, istniejące `adm.pnedu.pl` i `pnedu.pl` działają tak jak wcześniej.

To kryterium jest ważniejsze niż szybkość implementacji nowego modułu.

## Klasy ryzyka

### Krytyczne

- zamówienia i formularze,
- płatności i webhooki,
- faktury, iFirma i KSeF,
- dostęp do szkoleń i produktów,
- certyfikaty,
- konta, hasła i role,
- masowa komunikacja,
- ceny i reklamy.

Growth OS nie może dodawać wymaganej synchronicznej zależności do tych procesów.

### Wysokie

- publikacja treści w imieniu eksperta,
- przetwarzanie transkrypcji i PII przez AI,
- profilowanie klientów,
- scalanie osób i szkół,
- automatyczne rekomendacje ofert,
- integracje OAuth z uprawnieniem publikacji.

### Umiarkowane

- drafty,
- klasyfikacja tematów,
- wewnętrzne rekomendacje,
- biblioteka metadanych,
- read-only raporty.

## Mechanizmy ochronne

### Feature flag

- twarda flaga środowiskowa — wdrożona,
- domyślnie `false` — wdrożone,
- wyłącza dostęp do trasy i menu; joby i integracje jeszcze nie istnieją,
- stan flagi widoczny administratorowi,
- brak zależności starego kodu od włączonej flagi.

### Uprawnienia

Rozdzielić co najmniej:

- podgląd,
- tworzenie/edycję,
- zatwierdzanie,
- wykonywanie/publikowanie,
- konfigurację integracji.

Samo posiadanie roli `admin` nie powinno automatycznie pozwalać na masową wysyłkę lub zmianę integracji.

### Audyt

Zapisywać:

- kto utworzył i zmienił draft,
- kto zatwierdził,
- kto uruchomił wykonanie,
- jaki był podgląd zatwierdzonej wersji,
- wynik i czas operacji,
- anulowanie i retry.

Audyt nie może ujawniać sekretów ani pełnych payloadów z PII.

### Idempotency

Wymagana dla:

- mailingów,
- publikacji,
- uploadów,
- zleceń płatnych API,
- przetwarzania webhooków,
- wznowienia jobów po awarii.

### Transakcje i side effect

- lokalny zapis stanu w transakcji,
- zewnętrzny side effect poza transakcją DB,
- jawny rekord operacji przed wywołaniem dostawcy,
- aktualizacja wyniku po odpowiedzi,
- stan „nieznany” przy timeout zamiast ślepego ponowienia.

## Migracje

Każda przyszła migracja Growth:

- powstaje w projekcie odpowiadającym bazie,
- jest addytywna,
- nie usuwa ani nie przepisuje danych produkcyjnych,
- unika długich locków,
- ma plan rollbacku,
- ma backup i preview dla backfillu,
- nie używa `migrate:fresh`, `refresh`, `reset` ani `db:wipe`.

Masowy backfill jest osobną operacją z dry-run, limitami, logiem postępu i możliwością wznowienia.

## Ochrona danych i RODO

- minimalizacja danych,
- rozdzielenie danych operacyjnych i analitycznych,
- brak PII w `pne_analytics`,
- brak PII w zewnętrznym AI bez osobnej decyzji,
- jawna retencja,
- prawo do korekty i usunięcia tam, gdzie ma zastosowanie,
- ocena prawna/DPIA przed Customer Graph, profilowaniem lub publicznym asystentem.

Samo użycie identyfikatora technicznego nie gwarantuje anonimowości, jeśli można łatwo połączyć go z konkretną osobą.

## Treści i brand safety

- treść ekspercka wymaga approval,
- system zapisuje źródła i wersję,
- brak automatycznego przypisywania opinii ekspertowi,
- materiały prawne i regulacyjne wymagają weryfikacji człowieka,
- usunięcie draftu nie usuwa historii zatwierdzonej publikacji.

## Bezpieczeństwo integracji

- najniższe możliwe scope OAuth/API,
- osobne sekrety per środowisko,
- timeouty i circuit breaker/wyłączenie adaptera,
- walidacja webhooków,
- maskowanie danych w logach,
- limity requestów i kosztów,
- rotacja kluczy,
- runbook incydentu.

## Backup

Przed migracją lub masową zmianą:

1. sprawdzić aktualność backupu,
2. wskazać bazę i tabele,
3. oszacować rozmiar i czas,
4. przygotować rollback,
5. wykonać dry-run,
6. uzyskać potwierdzenie dla danych produkcyjnych.

Kanon: [DATA_SAFETY.md](../DATA_SAFETY.md) i [MYSQL_BACKUP.md](../deploy/MYSQL_BACKUP.md).

## Testy wymagane dla każdego etapu

- flaga wyłączona: stare trasy i procesy działają,
- brak uprawnienia: brak dostępu,
- podwójny submit: jeden side effect,
- retry joba: brak duplikatu,
- błąd integracji: zapisany stan, brak awarii starego procesu,
- timeout: brak niekontrolowanego ponowienia,
- testy regresji dotkniętych istniejących modułów,
- test rollbacku konfiguracji,
- ręczny smoke w UI.

## Plan rollbacku

Minimalny rollback:

1. wyłączyć flagę Growth OS,
2. zatrzymać dedykowane crony i nowe wykonania,
3. pozostawić dane bez kasowania,
4. wycofać kod według runbooka,
5. zweryfikować logowanie, checkout, płatność, panel uczestnika i ADM,
6. osobno ocenić operacje już wykonane u zewnętrznych dostawców.

Rollback publikacji lub mailingu jest procesem biznesowym, nie migracją bazy. Wysłanego mailingu nie można cofnąć.

## Zakazy

- sekret w repozytorium lub bazie draftów,
- natywny `confirm()`/`alert()`/`prompt()`,
- publikacja przez AI bez approval,
- bezpośredni zapis AI do cen, płatności lub faktur,
- masowe scalenie osób/szkół bez preview,
- usuwanie danych produkcyjnych w automatycznym rollbacku,
- szeroki refactoring istniejących modułów w ramach funkcji Growth.

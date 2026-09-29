# PNE Growth OS — changelog dokumentacji

Ten changelog dotyczy warstwy i dokumentacji PNE Growth OS. Nie zastępuje głównych `CHANGELOG.md` aplikacji `pneadm` i `pnedu`.

## 2026-09-29 — Etap 0.1: bezpieczny szkielet

### Added

- fail-closed feature flag `PNE_GROWTH_OS_ENABLED`,
- chroniona trasa `GET /growth`,
- tymczasowa autoryzacja przez istniejącą rolę `super_admin`,
- pozycja menu widoczna tylko dla uprawnionego użytkownika przy włączonej fladze,
- demonstracyjny pulpit korzystający z istniejącego layoutu ADM i Bootstrap,
- testy feature flag, autoryzacji, routingu oraz menu,
- runbook wdrożenia i rollbacku.

### Changed

- dokumentacja architektury, UX, bezpieczeństwa, roadmapy i decyzji opisuje rzeczywisty stan Etapu 0.1.

### Migration

- brak.

### Risk

- dostęp jest tymczasowo ograniczony do `super_admin`; granularne permission pozostaje decyzją przyszłego etapu,
- ekran zawiera wyłącznie dane demonstracyjne.

### Rollback

- ustawić `PNE_GROWTH_OS_ENABLED=false` i odświeżyć cache konfiguracji,
- w razie potrzeby wycofać kod; brak tabel i danych do usunięcia.

## 2026-09-29 — dokumentacja v0.1

### Added

- wizja i cele biznesowe,
- analiza obecnych aplikacji, baz, modeli i integracji,
- propozycja addytywnej architektury modułu w `adm.pnedu.pl`,
- logiczny model domenowy bez tabel i migracji,
- zasady UX, AI, integracji i human approval,
- zasady bezpieczeństwa i backward compatibility,
- roadmapa etapowa,
- rejestr decyzji i pytań otwartych.

### Changed

- brak zmian w kodzie i zachowaniu systemu.

### Migration

- brak.

### Risk

- brak ryzyka wykonawczego; dokument opisuje rekomendacje, których nie należy traktować jako wdrożonych funkcji.

### Rollback

- usunięcie dokumentacji; nie ma danych ani zmian runtime do cofania.

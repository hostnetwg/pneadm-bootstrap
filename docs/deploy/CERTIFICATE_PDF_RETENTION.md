# Retencja plików PDF zaświadczeń

Data: 2026-11-01 · Decyzje Waldemara: 30 dni, uzupełnienie `issue_date`, czyszczenie osieroconych plików · Changelog: ADM 1.5

## Po co

`storage/app/public/certificates` miało na produkcji ok. **15 GB** (ok. 9,6 tys. PDF-ów, ok. 1,4 MB każdy), czyli prawie całe `storage/app`. Pliki da się odtworzyć z danych w bazie. Zapis w bazie (`certificates`: numer, daty, statystyki pobrań) jest źródłem prawdy, a PDF to tylko cache. Użytkownik i tak może wygenerować go ponownie przed pobraniem, więc nie ma potrzeby trzymać go na serwerze w nieskończoność.

## Co robi mechanizm

Komenda `certificates:cleanup-pdfs` ([`CleanupCertificatePdfsCommand`](../../app/Console/Commands/CleanupCertificatePdfsCommand.php), logika w [`CertificatePdfRetentionService`](../../app/Services/Certificate/CertificatePdfRetentionService.php)):

1. Wybiera zaświadczenia z `file_path` i `generated_at` starszym niż **30 dni**.
2. Kasuje plik PDF z dysku `public`.
3. Zeruje `file_path` (rekord, numer, daty i statystyki pobrań zostają). To samo robi ręczny przycisk „Usuń PDF”.
4. **Przed skasowaniem zamraża datę wystawienia:** jeśli zaświadczenie nie ma `issue_date`, a kurs nie ma własnej daty (`courses.issue_date_certyficates` albo `online_courses.certificate_issue_date`), wpisuje `issue_date = data generated_at`. Bez tego odtworzony PDF dostałby **dzisiejszą datę** (`CertificateGeneratorService::resolveEffectiveCompletionDate`).

Co dzieje się po usunięciu:

| Kto | Efekt |
|-----|-------|
| Uczestnik na pnedu.pl (pobranie) | `pneadm` generuje PDF od nowa (kilka sekund), zapisuje plik i `file_path`; licznik 30 dni startuje od nowa |
| Admin w panelu, lista uczestników | ikona PDF znika (jest tylko przy `file_path`); **link z numerem zaświadczenia** generuje plik od nowa |
| Admin, bezpośrednie `download-pdf` | komunikat „Brak pliku PDF…”; użyj linku z numerem |
| Maile z linkiem do certyfikatu | bez zmian (linki, nie załączniki) |

## Zabezpieczenia

- Kasowane są **tylko** ścieżki `certificates/<liczba>/*.pdf` i `certificates/online-<liczba>/*.pdf`. Katalogi `certificates/logos/` i `certificates/backgrounds/` (zasoby szablonów) są nietykalne. Ścieżki spoza wzorca są pomijane (np. z `..`).
- `--days` musi wynosić co najmniej 7 (ochrona przed `--days=0`).
- Zaplanowane uruchomienie jest **domyślnie wyłączone** (`CERTIFICATE_PDF_CLEANUP_ENABLED=false`). Samo wdrożenie niczego nie kasuje.
- Wiek liczymy z `generated_at`, nie z pobrań. Regeneracja zeruje licznik.
- Nocny backup obejmuje **tylko bazę**, nie pliki. Skasowanych PDF-ów nie da się przywrócić z backupu, tylko wygenerować od nowa.

## Opcje komendy

```text
certificates:cleanup-pdfs [--dry-run] [--days=30] [--limit=N] [--orphans]
```

| Opcja | Znaczenie |
|-------|-----------|
| `--dry-run` | tylko raport (liczba plików, MB, ile `issue_date` zostanie zapisanych); nic nie zmienia |
| `--days=N` | wiek w dniach; domyślnie `CERTIFICATE_PDF_RETENTION_DAYS` (30); minimum 7 |
| `--limit=N` | najwyżej N zaświadczeń w jednym uruchomieniu (na pierwszy, próbny przebieg) |
| `--orphans` | zamiast głównego przebiegu: usuwa **osierocone** PDF-y, czyli pliki w `certificates/<id>/` bez pasującego `file_path` w bazie i starsze niż `--days` (po mtime). Są to np. pozostałości po usuniętym kursie. Świeże pliki zostają, bo mogą być dopiero co zapisane |

## Konfiguracja (`config/certificate_pdf_retention.php`)

| Zmienna `.env` | Domyślnie | Znaczenie |
|----------------|-----------|-----------|
| `CERTIFICATE_PDF_CLEANUP_ENABLED` | `false` | czy scheduler uruchamia czyszczenie codziennie |
| `CERTIFICATE_PDF_RETENTION_DAYS` | `30` | wiek pliku w dniach |
| `CERTIFICATE_PDF_CLEANUP_TIME` | `03:15` | godzina (strefa `Europe/Warsaw`) |

Harmonogram jest w `routes/console.php`. Korzysta z istniejącego crona `schedule:run` ([`../QUEUE_SEOHOST.md`](../QUEUE_SEOHOST.md)).

## Migracja

`2026_11_01_120000_backfill_certificates_issue_date_from_generated_at` (baza `pneadm`) to **jednorazowe uzupełnienie danych**, bez zmiany schematu. Dla zaświadczeń z plikiem, bez `issue_date` i bez daty w kursie wpisuje `issue_date = DATE(generated_at)`. Nie nadpisuje istniejących wartości. Nie da się jej odwrócić (`down()` nic nie robi).

Na lokalnej kopii produkcji z 2026-11-01 uzupełniła 6889 z 6892 zaświadczeń z plikiem bez daty. Pozostałe 3 mają datę w kursie. Data to dzień **ostatniego** wygenerowania pliku (`generated_at` zmienia się przy regeneracji), co jest najlepszym dostępnym przybliżeniem daty na PDF-ie.

## Wdrożenie na produkcji (SeoHost), krok po kroku

Na produkcji **nie używamy `sail`**. Ścieżki: [`PRODUCTION_PATHS.md`](./PRODUCTION_PATHS.md). Wykonuje Waldemar w terminalu SSH albo DirectAdmin.

```bash
# 1. Kod i migracja (tylko pneadm)
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache

# 2. Próba na sucho (nic nie zmienia)
/opt/alt/php82/usr/bin/php artisan certificates:cleanup-pdfs --dry-run
# oczekiwane: ok. 9 tys. plików i ok. 13-15 GB; "issue_date do zapisania" ma być 0 (zrobiła to migracja)

# 3. Próbny prawdziwy przebieg na 20 zaświadczeniach
/opt/alt/php82/usr/bin/php artisan certificates:cleanup-pdfs --limit=20
# Sprawdź w panelu: lista uczestników kursu, klik w numer zaświadczenia (czy PDF się generuje, czy data jest ta sama).

# 4. Pełny przebieg
/opt/alt/php82/usr/bin/php artisan certificates:cleanup-pdfs
du -sh ~/domains/adm.pnedu.pl/pneadm/storage/app/public/certificates

# 5. Osierocone pliki: najpierw na sucho, potem naprawdę
/opt/alt/php82/usr/bin/php artisan certificates:cleanup-pdfs --orphans --dry-run
/opt/alt/php82/usr/bin/php artisan certificates:cleanup-pdfs --orphans

# 6. Automat: dopisz w .env na prodzie CERTIFICATE_PDF_CLEANUP_ENABLED=true i odśwież konfigurację
/opt/alt/php82/usr/bin/php artisan config:cache
```

Logi uruchomień: `storage/logs/laravel.log` (wpisy `certificates:cleanup-pdfs`).

## Testy

`tests/Feature/CertificatePdfRetentionTest.php` (11 testów, dysk `Storage::fake`, baza `testing`): `sail artisan test --filter=CertificatePdfRetentionTest`. Pokrywa: kasowanie starych i zostawianie świeżych, zamrażanie `issue_date` (i brak nadpisania własnej oraz daty z kursu), `--dry-run`, ścieżki poza wzorcem, `--limit`, odmowę `--days<7`, osierocone pliki (z pominięciem `logos/` i `backgrounds/`), migrację.

## Powiązane

- Dev nie pobiera certyfikatów nigdy: [`DEV_SYNC.md`](./DEV_SYNC.md)
- Kolejka i cron: [`../QUEUE_SEOHOST.md`](../QUEUE_SEOHOST.md)
- Wersjonowanie: [`../CHANGELOG_VERSIONING.md`](../CHANGELOG_VERSIONING.md)

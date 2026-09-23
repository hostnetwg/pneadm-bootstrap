# Deploy: dopisanie do nagrania po szkoleniu

Data: 2026-09-23  
Projekty: `pneadm` (migracja) + `pnedu` (formularz).  
Kanon: [RECORDING_ENROLLMENT.md](../RECORDING_ENROLLMENT.md). Ścieżki: [PRODUCTION_PATHS.md](./PRODUCTION_PATHS.md).

## Cel

Na edycji szkolenia przełącznik **Dopisanie do nagrania**. Publiczny link `{PNEDU}/dostep-do-szkolenia/{token}` dopisuje uczestnika. Nowy adres od razu dostaje konto pnedu.pl z hasłem z formularza. Istniejące konto loguje się dotychczasowym hasłem.

## Migracja

Tylko baza `pneadm`, tabela `courses`:

`2026_09_23_170000_add_recording_enrollment_to_courses_table.php`

Kolumny: `recording_enrollment_open`, `recording_enrollment_ends_at`, `recording_enrollment_token` (unikalny).

Na pnedu nie ma migracji.

## Po deployu

### Panel

```bash
cd /home/srv66127/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan queue:restart
```

### Front

```bash
cd /home/srv66127/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
```

`PNEADM_API_TOKEN` musi być taki sam w obu `.env`. Formularz woła API panelu tym tokenem.

## Smoke

1. Edycja szkolenia → włącz **Dopisanie do nagrania** → skopiuj link.
2. Otwórz link: widać hasło do nagrania i tekst „Ustaw hasło dostępu do nagrania”.
3. Wyłącz przełącznik albo użyj szkolenia testowego. Nie wysyłaj formularza na prawdziwy adres uczestnika, jeśli nie chcesz założyć mu konta.

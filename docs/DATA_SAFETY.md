# Bezpieczeństwo danych (bazy)

Data: 2026-09-19

## Incydent 19.09.2026 (dev)

Lokalna baza `pneadm` została wyczyszczona komendą:

```bash
sail artisan migrate:fresh --env=testing
```

**Dlaczego to nie poszło na bazę `testing`:**

| Mechanizm | Czy ustawia `DB_DATABASE=testing` |
|-----------|-----------------------------------|
| `sail test` / PHPUnit (`phpunit.xml`) | **tak** |
| `sail artisan … --env=testing` | **nie**, dopóki nie ma pliku `.env.testing` |

`--env=testing` każe Laravelowi wczytać `.env.testing`. Tego pliku nie było, więc Artisan wziął `.env` (`DB_DATABASE=pneadm`) i `migrate:fresh` zrzucił tabele robocze. Potem odtworzono zrzut z prod (19.09.2026 11:46).

To **nie** był `git pull`, migracja `migrate --force` ani skrypt nocnego dumpa.

## Czy to samo grozi na produkcji po wgraniu kodu?

**Nie przy normalnym deployu.** Na SeoHost jest:

```bash
/opt/alt/php82/usr/bin/php artisan migrate --force
```

`migrate` tylko **dodaje** brakujące migracje. Nie kasuje tabel. Skrypt `prod-mysql-nightly-backup.sh` robi `mysqldump` (zapis), nie `DROP`.

Ryzyko na prod pojawia się dopiero gdy ktoś **ręcznie** w SSH wklei `migrate:fresh` / `db:wipe` (albo analog z docs/cheatsheet). Po tej zmianie aplikacja **blokuje** te komendy, gdy nazwa bazy nie jest `testing`.

## Twarda blokada w kodzie

`App\Support\DestructiveDatabaseGuard` (pneadm i pnedu) przy starcie Artisan:

**Zablokowane:** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`  
**Gdy baza docelowa ≠** `testing` (np. `pneadm`, `srv66127_pneduadm`, `pnedu`).

**Dozwolone:** `sail test` (PHPUnit → `testing`), `sail artisan migrate:fresh --env=testing` przy `.env.testing`, zwykłe `migrate`.

Świadomy wyjątek **tylko lokalnie**, nigdy na prod:

```env
ALLOW_DESTRUCTIVE_DB=true
```

Testy **nie** wywołują prawdziwego `artisan migrate:fresh` na nazwie `pneadm` — Laravel w evencie podaje klasę komendy (`FreshCommand`), nie alias. Strażnik rozpoznaje obie formy.

## Zasady dla operatora i AI

| Wolno | Nie wolno |
|-------|-----------|
| `sail test` | `sail artisan migrate:fresh` na `.env` z `pneadm` / `pnedu` |
| `sail artisan migrate` | `migrate:fresh --env=testing` **bez** `.env.testing` |
| prod: `php artisan migrate --force` | prod: jakakolwiek komenda z `fresh` / `wipe` / `reset` |
| nocny `mysqldump` | trzymać dump w `public_html` |

Lokalny `.env.testing` (`DB_DATABASE=testing`) jest w `.gitignore`. Musi istnieć na stacji deweloperskiej.

## Deploy (prod)

1. Kopia: Backup Manager SeoHost **albo** świeży plik z `~/backups/mysql` (gdy cron już działa).
2. `git pull`
3. **Tylko** `migrate --force` gdy są nowe pliki migracji — **nigdy** `migrate:fresh`.
4. `optimize:clear` / cache jak w [PRODUCTION_PATHS.md](./deploy/PRODUCTION_PATHS.md).

Kopie: [MYSQL_BACKUP.md](./deploy/MYSQL_BACKUP.md). Testy: [TESTING.md](./TESTING.md).

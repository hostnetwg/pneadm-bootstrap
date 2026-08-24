# Ścieżki produkcyjne (SeoHost)

Kanoniczne ścieżki aplikacji na serwerze produkcyjnym. Używaj ich w runbookach deploy i w komendach zamiast placeholderów typu `/ścieżka/do/…`.

| Aplikacja | Domena | Ścieżka (względem home `~`) | Pełna (login `srv66127`) |
|-----------|--------|-----------------------------|---------------------------|
| **pneadm** (panel) | [adm.pnedu.pl](https://adm.pnedu.pl) | `~/domains/adm.pnedu.pl/pneadm` | `/home/srv66127/domains/adm.pnedu.pl/pneadm` |
| **pnedu** (front) | [pnedu.pl](https://pnedu.pl) | `~/domains/pnedu.pl/app` | `/home/srv66127/domains/pnedu.pl/app` |

Skrót (z katalogu domowego po SSH):

```text
domains/adm.pnedu.pl/pneadm   → adm.pnedu.pl
domains/pnedu.pl/app          → pnedu.pl
```

PHP na prod (hosting współdzielony):

```text
/opt/alt/php82/usr/bin/php
```

## Laravel Sail — tylko dev (WSL/Docker)

**Na produkcji nie używamy `sail`.** Sail uruchamia komendy w kontenerze Docker — działa wyłącznie lokalnie (WSL2 + Laravel Sail).

| Środowisko | Artisan / Composer / testy | Przykład |
|------------|----------------------------|----------|
| **Dev (lokalnie)** | `./vendor/bin/sail` lub alias `sail` | `sail artisan migrate` |
| **Prod (SeoHost)** | `/opt/alt/php82/usr/bin/php artisan …` | `/opt/alt/php82/usr/bin/php artisan migrate --force` |

Na prod **nigdy:** `sail artisan …`, `sail composer …`, `sail npm …`.

Runbooki deploy, cron i kolejka: zawsze pełna ścieżka PHP powyżej (lub `php artisan …` jeśli `php` w PATH wskazuje na 8.2).

## Typowy deploy

```bash
# Panel
cd ~/domains/adm.pnedu.pl/pneadm
git pull origin main
/opt/alt/php82/usr/bin/php artisan migrate --force   # gdy są migracje
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache

# Front
cd ~/domains/pnedu.pl/app
git pull origin main
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan view:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
```

## Powiązane

- Kolejka / cron: [`PRODUCTION_QUEUE_OPS.md`](./PRODUCTION_QUEUE_OPS.md)
- Forma komunikacji (docs po etapie): [`../AI_HUMAN_COMMUNICATION.md`](../AI_HUMAN_COMMUNICATION.md)

Zapis: 2026-08-10 (Waldemar).

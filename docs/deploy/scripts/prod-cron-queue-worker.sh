#!/usr/bin/env bash
# Worker kolejki Laravel dla crona prod (SeoHost) — z logowaniem flock.
#
# Użycie w DirectAdmin (co minutę):
#   * * * * * /bin/bash /home/srv66127/domains/pnedu.pl/app/docs/deploy/scripts/prod-cron-queue-worker.sh /home/srv66127/domains/pnedu.pl/app /tmp/pnedu-queue.lock >> /home/srv66127/domains/pnedu.pl/app/storage/logs/cron-queue.log 2>&1
#   * * * * * /bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-cron-queue-worker.sh /home/srv66127/domains/adm.pnedu.pl/pneadm /tmp/pneadm-queue.lock >> /home/srv66127/domains/adm.pnedu.pl/pneadm/storage/logs/cron-queue.log 2>&1
#
# Argumenty: <katalog_aplikacji> <plik_lock_flock>
# Zmienne: PHP_BIN (domyślnie /opt/alt/php82/usr/bin/php), QUEUE_MAX_TIME (55)

set -euo pipefail

APP_DIR="${1:?Brak argumentu: katalog aplikacji}"
LOCK_FILE="${2:?Brak argumentu: plik lock flock}"
PHP_BIN="${PHP_BIN:-/opt/alt/php82/usr/bin/php}"
QUEUE_MAX_TIME="${QUEUE_MAX_TIME:-55}"

if [[ ! -d "$APP_DIR" || ! -f "$APP_DIR/artisan" ]]; then
  echo "$(TZ=Europe/Warsaw date '+%Y-%m-%d %H:%M:%S %Z') ERROR brak artisan w $APP_DIR"
  exit 1
fi

cd "$APP_DIR"

STAMP="$(TZ=Europe/Warsaw date '+%Y-%m-%d %H:%M:%S %Z')"
WORKER_CMD="$PHP_BIN artisan queue:work database --queue=default,analytics --stop-when-empty --max-time=${QUEUE_MAX_TIME} --sleep=1 --tries=2"

if /usr/bin/flock -n "$LOCK_FILE" -c "$WORKER_CMD"; then
  echo "$STAMP START+END OK lock=$LOCK_FILE max_time=${QUEUE_MAX_TIME}s"
else
  echo "$STAMP SKIP flock busy (lock=$LOCK_FILE) — cron nie uruchomił workera; sprawdź: ps aux | grep queue:work"
  exit 0
fi

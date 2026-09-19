#!/usr/bin/env bash
# Nocny mysqldump baz PNE na SeoHost (poza public_html, poza Laravel).
#
# Cron DirectAdmin (sprawdź strefę czasu panelu; zwykle Europe/Warsaw):
#   30 1 * * * /bin/bash /home/srv66127/domains/adm.pnedu.pl/pneadm/docs/deploy/scripts/prod-mysql-nightly-backup.sh >> /home/srv66127/backups/mysql/backup.log 2>&1
#
# Zmienne opcjonalne:
#   BACKUP_ROOT   (domyślnie $HOME/backups/mysql)
#   RETAIN_DAYS   (domyślnie 14)
#   PNEADM_ENV    (domyślnie ~/domains/adm.pnedu.pl/pneadm/.env)
#   PNEDU_ENV     (domyślnie ~/domains/pnedu.pl/app/.env)
#   MYSQLDUMP_BIN (domyślnie mysqldump z PATH)

set -euo pipefail

STAMP_HUMAN="$(TZ=Europe/Warsaw date '+%Y-%m-%d %H:%M:%S %Z')"
STAMP_FILE="$(TZ=Europe/Warsaw date '+%Y-%m-%d')"
BACKUP_ROOT="${BACKUP_ROOT:-${HOME}/backups/mysql}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
PNEADM_ENV="${PNEADM_ENV:-${HOME}/domains/adm.pnedu.pl/pneadm/.env}"
PNEDU_ENV="${PNEDU_ENV:-${HOME}/domains/pnedu.pl/app/.env}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"
LOCK_FILE="${LOCK_FILE:-/tmp/pne-mysql-nightly-backup.lock}"

log() {
  echo "${STAMP_HUMAN} $*"
}

env_get() {
  local file="$1" key="$2" line
  [[ -f "$file" ]] || return 0
  line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
  [[ -n "$line" ]] || return 0
  line="${line#*=}"
  line="${line%$'\r'}"
  line="${line#\"}"
  line="${line%\"}"
  line="${line#\'}"
  line="${line%\'}"
  printf '%s' "$line"
}

dump_database() {
  local label="$1" env_file="$2" db_key="$3" user_key="$4" pass_key="$5" host_key="$6" port_key="$7"
  local db user pass host port cnf dest

  db="$(env_get "$env_file" "$db_key")"
  user="$(env_get "$env_file" "$user_key")"
  pass="$(env_get "$env_file" "$pass_key")"
  host="$(env_get "$env_file" "$host_key")"
  port="$(env_get "$env_file" "$port_key")"

  if [[ -z "$db" || -z "$user" ]]; then
    log "SKIP ${label}: brak ${db_key} albo ${user_key} w ${env_file}"
    return 0
  fi

  host="${host:-localhost}"
  if [[ "$host" == "mysql" ]]; then
    host="localhost"
  fi
  port="${port:-3306}"

  dest="${BACKUP_ROOT}/${STAMP_FILE}_${label}_${db}.sql.gz"
  cnf="$(mktemp "${BACKUP_ROOT}/.my.cnf.XXXXXX")"
  chmod 600 "$cnf"
  cat > "$cnf" <<EOF
[client]
host=${host}
port=${port}
user=${user}
password=${pass}
EOF

  log "START ${label} db=${db} -> ${dest}"
  if ! "${MYSQLDUMP_BIN}" --defaults-extra-file="$cnf" \
    --single-transaction --quick --routines --triggers --hex-blob \
    --default-character-set=utf8mb4 --no-tablespaces \
    "$db" | gzip -c > "$dest"
  then
    rm -f "$cnf" "$dest"
    log "FAIL ${label} db=${db}"
    return 1
  fi

  rm -f "$cnf"
  chmod 600 "$dest"
  log "OK ${label} $(du -h "$dest" | awk '{print $1}')"
}

if [[ ! -x "$(command -v "$MYSQLDUMP_BIN" || true)" && ! -x "$MYSQLDUMP_BIN" ]]; then
  log "ERROR brak mysqldump (${MYSQLDUMP_BIN})"
  exit 1
fi

mkdir -p "$BACKUP_ROOT"
chmod 700 "$BACKUP_ROOT"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  log "SKIP flock busy (${LOCK_FILE})"
  exit 0
fi

failed=0
dump_database "pneadm" "$PNEADM_ENV" DB_DATABASE DB_USERNAME DB_PASSWORD DB_HOST DB_PORT || failed=1
dump_database "certgen" "$PNEADM_ENV" DB_SECOND_DATABASE DB_SECOND_USERNAME DB_SECOND_PASSWORD DB_SECOND_HOST DB_SECOND_PORT || failed=1
dump_database "analytics" "$PNEADM_ENV" DB_ANALYTICS_DATABASE DB_ANALYTICS_USERNAME DB_ANALYTICS_PASSWORD DB_ANALYTICS_HOST DB_ANALYTICS_PORT || failed=1
dump_database "pnedu" "$PNEDU_ENV" DB_DATABASE DB_USERNAME DB_PASSWORD DB_HOST DB_PORT || failed=1

if command -v find >/dev/null 2>&1; then
  find "$BACKUP_ROOT" -type f -name '*.sql.gz' -mtime +"${RETAIN_DAYS}" -delete
  log "RETENTION usunięto pliki starsze niż ${RETAIN_DAYS} dni"
fi

if [[ "$failed" -ne 0 ]]; then
  log "DONE z błędami"
  exit 1
fi

log "DONE OK"
exit 0

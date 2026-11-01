#!/usr/bin/env bash
# dev-pull.sh — synchronizacja PROD -> DEV (tylko w dół; z produkcji wyłącznie ODCZYT).
#
# Uruchamiaj lokalnie w WSL (nie na SeoHost). Na prod łączy się wyłącznie kluczem z
# ograniczeniem "forced command" (docs/deploy/scripts/prod-devsync-readonly.sh).
#
# Tryby (można łączyć):
#   (brak)            = --code
#   --code            git pull --ff-only obu aplikacji (tylko czyste drzewo), composer/npm gdy
#                     zmienił się lockfile, raport różnic względem produkcji, lokalne migrate
#   --db              najnowszy KOMPLETNY zestaw 4 zrzutów z produkcji -> import do lokalnego
#                     MySQL (pneadm, certgen, pne_analytics, pnedu), potem migrate
#   --files           rsync storage/app (pneadm + pnedu), bez storage/app/public/certificates
#   --all             --code --db --files
# Opcje:
#   --delete-files       lustro: kasuje lokalne pliki, których nie ma na prod (domyślnie NIE)
#   --dry-run            tylko raport, nic nie zmienia
#   --yes                bez pytań o potwierdzenie (automatyzacja)
#   --scratch            TEST importu: wczytuje zrzuty do baz *_scratch i usuwa je (bez migrate)
#   -h, --help
#
# Konfiguracja (poza repo): ~/.config/pne/dev-pull.env  (wzór: dev-pull.env.example)

set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

CONFIG_FILE="${DEV_PULL_CONFIG:-$HOME/.config/pne/dev-pull.env}"
if [[ -f "$CONFIG_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$CONFIG_FILE"
fi

SSH_HOST="${SSH_HOST:-h30.seohost.pl}"
SSH_PORT="${SSH_PORT:-}"
SSH_USER="${SSH_USER:-srv66127}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/pne_devsync_ed25519}"
PNEADM_DIR="${PNEADM_DIR:-$REPO_DIR}"
PNEDU_DIR="${PNEDU_DIR:-$(dirname "$REPO_DIR")/pnedu}"
DEV_SYNC_HOME="${DEV_SYNC_HOME:-$HOME/pne-dev-sync}"
MYSQL_CONTAINER="${MYSQL_CONTAINER:-pneadm-mysql}"
KEEP_SNAPSHOTS="${KEEP_SNAPSHOTS:-3}"
MAX_DUMP_AGE_HOURS="${MAX_DUMP_AGE_HOURS:-36}"
ALLOW_LIVE_PAYMENTS="${ALLOW_LIVE_PAYMENTS:-0}"

DO_CODE=0; DO_DB=0; DO_FILES=0
DELETE_FILES=0; DRY=0; ASSUME_YES=0; SCRATCH=0

# ---------- pomocnicze ----------
c_red=$'\033[31m'; c_yel=$'\033[33m'; c_grn=$'\033[32m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
[[ -t 1 ]] || { c_red=''; c_yel=''; c_grn=''; c_dim=''; c_off=''; }

say()  { printf '%s\n' "$*"; }
step() { printf '\n%s== %s%s\n' "$c_grn" "$*" "$c_off"; }
warn() { printf '%sUWAGA:%s %s\n' "$c_yel" "$c_off" "$*" >&2; }
die()  { printf '%sBŁĄD:%s %s\n' "$c_red" "$c_off" "$*" >&2; exit 1; }
info() { printf '%s%s%s\n' "$c_dim" "$*" "$c_off"; }

usage() { sed -n '2,29p' "$0" | sed 's/^# \{0,1\}//'; }

LOG_FILE=""
log() { [[ -n "$LOG_FILE" ]] && printf '%s %s\n' "$(date '+%F %T')" "$*" >> "$LOG_FILE" 2>/dev/null || true; }

env_get() { # plik klucz  (bez source; bez wypisywania sekretów)
  local file="$1" key="$2" line
  [[ -f "$file" ]] || return 0
  line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
  [[ -n "$line" ]] || return 0
  line="${line#*=}"; line="${line%$'\r'}"
  line="${line#\"}"; line="${line%\"}"; line="${line#\'}"; line="${line%\'}"
  printf '%s' "$line"
}

confirm() { # pytanie
  (( ASSUME_YES )) && return 0
  local ans
  read -r -p "$1 Wpisz TAK aby kontynuować: " ans || true
  [[ "$ans" == "TAK" ]]
}

# ---------- argumenty ----------
for arg in "$@"; do
  case "$arg" in
    --code) DO_CODE=1 ;;
    --db) DO_DB=1 ;;
    --files) DO_FILES=1 ;;
    --all) DO_CODE=1; DO_DB=1; DO_FILES=1 ;;
    --delete-files) DELETE_FILES=1 ;;
    --dry-run) DRY=1 ;;
    --yes) ASSUME_YES=1 ;;
    --scratch) SCRATCH=1 ;;
    -h|--help) usage; exit 0 ;;
    *) die "Nieznana opcja: $arg (użyj --help)" ;;
  esac
done
(( DO_CODE + DO_DB + DO_FILES == 0 )) && DO_CODE=1

# ---------- blokada i katalogi ----------
mkdir -p "$DEV_SYNC_HOME/dumps" "$DEV_SYNC_HOME/snapshots"
chmod 700 "$DEV_SYNC_HOME" "$DEV_SYNC_HOME/dumps" "$DEV_SYNC_HOME/snapshots"
LOG_FILE="$DEV_SYNC_HOME/dev-pull.log"
exec 9>"$DEV_SYNC_HOME/.lock"
flock -n 9 || die "Inna instancja dev-pull już działa."
log "START args=$* host=$(hostname)"

[[ -n "$SSH_PORT" ]] || die "Brak SSH_PORT. Skopiuj scripts/dev-sync/dev-pull.env.example do $CONFIG_FILE i uzupełnij."
[[ -f "$SSH_KEY" ]] || die "Brak klucza SSH: $SSH_KEY"
[[ -d "$PNEADM_DIR/.git" ]] || die "PNEADM_DIR nie jest repozytorium: $PNEADM_DIR"
[[ -d "$PNEDU_DIR/.git" ]] || die "PNEDU_DIR nie jest repozytorium: $PNEDU_DIR"

# Agent SSH: jeśli nie ma w środowisku, a istnieje gniazdo pne-agent, użyj go.
if [[ -z "${SSH_AUTH_SOCK:-}" && -S "$HOME/.ssh/pne-agent.sock" ]]; then
  export SSH_AUTH_SOCK="$HOME/.ssh/pne-agent.sock"
fi

CM_PATH="$DEV_SYNC_HOME/cm-%C"
SSH_BASE=(ssh -p "$SSH_PORT" -i "$SSH_KEY" -o IdentitiesOnly=yes -o BatchMode=yes
  -o StrictHostKeyChecking=yes -o ConnectTimeout=20 -o ServerAliveInterval=15
  -o ControlMaster=auto -o "ControlPath=$CM_PATH" -o ControlPersist=120)
RSYNC_RSH="ssh -p $SSH_PORT -i $SSH_KEY -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20 -o ServerAliveInterval=15 -o ControlMaster=auto -o ControlPath=$CM_PATH -o ControlPersist=120"

cleanup() {
  "${SSH_BASE[@]}" -O exit "${SSH_USER}@${SSH_HOST}" >/dev/null 2>&1 || true
  if (( SCRATCH )) && [[ -n "${MYSQL_PWD:-}" ]]; then   # po teście sprzątamy bazy *_scratch
    local l
    for l in pneadm certgen analytics pnedu; do
      mysql_root -e "DROP DATABASE IF EXISTS \`$(local_db_for "$l")_scratch\`" >/dev/null 2>&1 || true
    done
  fi
  [[ -n "${PART_FILE:-}" ]] && rm -f "$PART_FILE" 2>/dev/null || true
}
trap cleanup EXIT

remote() { # polecenie klienta dla skryptu serwerowego; ponowienia tylko przy błędzie połączenia (255)
  local try rc=0
  for try in 1 2 3; do
    if "${SSH_BASE[@]}" "${SSH_USER}@${SSH_HOST}" "$@"; then return 0; else rc=$?; fi
    [[ $rc -eq 255 ]] || return "$rc"
    if (( try < 3 )); then
      warn "SSH: błąd połączenia (próba $try/3), czekam $((try * 20)) s"
      sleep $((try * 20))
    fi
  done
  return "$rc"
}

# ---------- sprawdzenie kontenerów i .env ----------
sail_in() { # katalog + argumenty sail
  local dir="$1"; shift
  ( cd "$dir" && ./vendor/bin/sail "$@" )
}

need_containers() {
  local n
  for n in "$MYSQL_CONTAINER" pneadm-bootstrap-app pnedu-app; do
    docker ps --format '{{.Names}}' | grep -qx "$n" || die "Kontener '$n' nie działa. Uruchom: sail up -d (w pneadm i pnedu)."
  done
}

guard_env() { # --db: twarde blokady bezpieczeństwa lokalnego .env
  local dir f v host mailer mailhost live=()
  for dir in "$PNEADM_DIR" "$PNEDU_DIR"; do
    f="$dir/.env"
    [[ -f "$f" ]] || die "Brak $f"
    [[ "$(env_get "$f" APP_ENV)" == "local" ]] || die "$f: APP_ENV nie jest 'local' — odmawiam importu bazy."
    host="$(env_get "$f" DB_HOST)"
    case "$host" in mysql|127.0.0.1|localhost) ;; *) die "$f: DB_HOST='$host' nie wygląda na lokalny kontener — odmawiam." ;; esac
    mailer="$(env_get "$f" MAIL_MAILER)"; mailhost="$(env_get "$f" MAIL_HOST)"
    case "$mailer" in
      log|array) ;;
      smtp) case "$mailhost" in mailpit|localhost|127.0.0.1|host.docker.internal) ;;
              *) die "$f: MAIL_HOST='$mailhost' to nie Mailpit — prawdziwe maile do klientów! Odmawiam." ;; esac ;;
      *) die "$f: MAIL_MAILER='$mailer' — ustaw log albo smtp->mailpit. Odmawiam." ;;
    esac
    for k in PAYU_SANDBOX PAYNOW_SANDBOX; do
      v="$(env_get "$f" "$k")"
      case "${v,,}" in false|0|no) live+=("$(basename "$dir"):$k=$v") ;; esac
    done
  done
  if (( ${#live[@]} )); then
    if [[ "$ALLOW_LIVE_PAYMENTS" == "1" ]]; then
      warn "PŁATNOŚCI NA ŻYWO włączone świadomie (ALLOW_LIVE_PAYMENTS=1): ${live[*]}"
    else
      die "Lokalne płatności są w trybie PRODUKCYJNYM (${live[*]}). Po imporcie prawdziwych zamówień grozi to prawdziwą płatnością. Ustaw *_SANDBOX=true w lokalnym .env (albo ALLOW_LIVE_PAYMENTS=1 w $CONFIG_FILE, jeśli to świadome)."
    fi
  fi
  # Integracje zewnętrzne: tylko ostrzeżenie (nie wypisuje wartości)
  local ext=() k2
  for dir in "$PNEADM_DIR" "$PNEDU_DIR"; do
    for k2 in CLICKMEETING_API_TOKEN SENDY_API_KEY PUBLIGO_API_KEY IFIRMA_API_KEY IFIRMA_KSEF_TOKEN; do
      [[ -n "$(env_get "$dir/.env" "$k2")" ]] && ext+=("$(basename "$dir"):$k2")
    done
  done
  if (( ${#ext[@]} )); then
    warn "Lokalny .env ma klucze zewnętrznych usług (${ext[*]}). Z danymi z produkcji akcje w panelu mogą trafić do prawdziwych kont (ClickMeeting, Sendy, Publigo, iFirma)."
  fi
}

# ---------- MYSQL ----------
mysql_root() { docker exec -i -e MYSQL_PWD "$MYSQL_CONTAINER" mysql -uroot "$@"; }
mysqldump_root() { docker exec -i -e MYSQL_PWD "$MYSQL_CONTAINER" mysqldump -uroot "$@"; }

init_mysql() {
  MYSQL_PWD="$(env_get "$PNEADM_DIR/.env" DB_PASSWORD)"
  export MYSQL_PWD
  [[ -n "$MYSQL_PWD" ]] || die "Brak DB_PASSWORD w $PNEADM_DIR/.env"
  mysql_root -N -B -e 'SELECT 1' >/dev/null 2>&1 || die "Nie mogę połączyć się z MySQL jako root w kontenerze $MYSQL_CONTAINER."
  DB_PNEADM="$(env_get "$PNEADM_DIR/.env" DB_DATABASE)";           DB_PNEADM="${DB_PNEADM:-pneadm}"
  DB_CERTGEN="$(env_get "$PNEADM_DIR/.env" DB_SECOND_DATABASE)";   DB_CERTGEN="${DB_CERTGEN:-certgen}"
  DB_ANALYTICS="$(env_get "$PNEADM_DIR/.env" DB_ANALYTICS_DATABASE)"; DB_ANALYTICS="${DB_ANALYTICS:-pne_analytics}"
  DB_PNEDU="$(env_get "$PNEDU_DIR/.env" DB_DATABASE)";             DB_PNEDU="${DB_PNEDU:-pnedu}"
}

local_db_for() {
  case "$1" in
    pneadm) echo "$DB_PNEADM" ;; certgen) echo "$DB_CERTGEN" ;;
    analytics) echo "$DB_ANALYTICS" ;; pnedu) echo "$DB_PNEDU" ;;
  esac
}

# ---------- RAPORT ----------
report_heads() {
  step "Wersje kodu: lokalnie vs produkcja"
  local out label sha date dir lsha
  out="$(remote heads)" || die "Nie udało się pobrać wersji z produkcji (polecenie 'heads')."
  while IFS=$'\t' read -r label sha date; do
    case "$label" in pneadm) dir="$PNEADM_DIR" ;; pnedu) dir="$PNEDU_DIR" ;; *) continue ;; esac
    lsha="$(git -C "$dir" rev-parse --short HEAD)"
    local rel="inne gałęzie/wersje" ahead behind
    if git -C "$dir" cat-file -e "${sha}^{commit}" 2>/dev/null; then
      ahead="$(git -C "$dir" rev-list --count "${sha}..HEAD")"
      behind="$(git -C "$dir" rev-list --count "HEAD..${sha}")"
      rel="lokalnie +${ahead} commit(ów) przed produkcją, ${behind} za"
    else
      rel="commit produkcji ${sha} nieznany lokalnie (zrób git fetch)"
    fi
    printf '  %-7s prod=%s (%s)  lokalnie=%s  -> %s\n' "$label" "$sha" "${date%%T*}" "$lsha" "$rel"
  done <<<"$out"
}

report_env_keys() {
  step "Nowe klucze w .env.example, których brakuje w lokalnym .env"
  local dir ex envf missing
  for dir in "$PNEADM_DIR" "$PNEDU_DIR"; do
    ex="$dir/.env.example"; envf="$dir/.env"
    [[ -f "$ex" && -f "$envf" ]] || continue
    missing="$(comm -23 \
      <(grep -E '^[A-Z][A-Z0-9_]*=' "$ex" | cut -d= -f1 | sort -u) \
      <(grep -E '^[A-Z][A-Z0-9_]*=' "$envf" | cut -d= -f1 | sort -u) || true)"
    if [[ -n "$missing" ]]; then
      local cnt; cnt="$(wc -l <<<"$missing")"
      printf '  %s: %s kluczy, np.: %s\n' "$(basename "$dir")" "$cnt" "$(head -n 6 <<<"$missing" | tr '\n' ' ')"
      info "     (opcjonalne; domyślne wartości są w config/*.php. Pełna lista: comm -23 <(cut -d= -f1 .env.example|sort) <(cut -d= -f1 .env|sort))"
    else
      printf '  %s: brak różnic\n' "$(basename "$dir")"
    fi
  done
}

# ---------- KOD ----------
sync_repo() { # katalog
  local dir="$1" name branch up dirty ahead behind old new changed
  name="$(basename "$dir")"
  branch="$(git -C "$dir" rev-parse --abbrev-ref HEAD)"
  say "-- $name (gałąź: $branch)"
  git -C "$dir" fetch --quiet origin || { warn "$name: git fetch nie powiódł się"; return 0; }
  up="origin/$branch"
  if ! git -C "$dir" rev-parse --verify --quiet "$up" >/dev/null; then
    warn "$name: brak $up — pomijam pull"; return 0
  fi
  dirty="$(git -C "$dir" status --porcelain --untracked-files=no)"
  ahead="$(git -C "$dir" rev-list --count "${up}..HEAD")"
  behind="$(git -C "$dir" rev-list --count "HEAD..${up}")"
  say "   lokalnie +${ahead} / -${behind} względem $up"
  if [[ -n "$dirty" ]]; then
    warn "$name: niezacommitowane zmiany — NIE robię pull. Zacommituj/odłóż je najpierw:"
    git -C "$dir" status --short --untracked-files=no | sed 's/^/     /'
    return 0
  fi
  if (( behind == 0 )); then say "   aktualne"; return 0; fi
  if (( ahead > 0 )); then warn "$name: historia rozjechana (ahead>0 i behind>0) — pull ręcznie."; return 0; fi
  if (( DRY )); then say "   [dry-run] zrobiłbym git pull --ff-only (${behind} commit(ów))"; return 0; fi
  old="$(git -C "$dir" rev-parse HEAD)"
  git -C "$dir" pull --ff-only --quiet origin "$branch"
  new="$(git -C "$dir" rev-parse HEAD)"
  say "   zaktualizowano do $(git -C "$dir" rev-parse --short HEAD)"
  changed="$(git -C "$dir" diff --name-only "$old" "$new")"
  if grep -qx 'composer.lock' <<<"$changed"; then
    say "   composer.lock się zmienił -> sail composer install"
    sail_in "$dir" composer install --no-interaction
  fi
  if grep -qx 'package-lock.json' <<<"$changed"; then
    say "   package-lock.json się zmienił -> sail npm install"
    sail_in "$dir" npm install
  fi
}

do_migrate() {
  step "Lokalne migracje (migrate dodaje brakujące; nic nie kasuje)"
  local dir
  for dir in "$PNEADM_DIR" "$PNEDU_DIR"; do   # pneadm najpierw: pnedu czyta bazę pneadm
    say "-- $(basename "$dir"): oczekujące migracje:"
    sail_in "$dir" artisan migrate:status --pending --no-interaction 2>&1 | sed 's/^/     /' | head -40 || true
    if (( DRY )); then say "   [dry-run] pominięto migrate"; continue; fi
    sail_in "$dir" artisan migrate --no-interaction 2>&1 | sed 's/^/     /'
  done
}

do_code() {
  step "Kod z repozytorium"
  need_containers
  sync_repo "$PNEADM_DIR"
  sync_repo "$PNEDU_DIR"
}

# ---------- BAZY ----------
LISTING=""
pick_date() {
  local d l ok
  while read -r d; do
    ok=1
    for l in pneadm certgen analytics pnedu; do
      grep -qE "^${d}_${l}_" <<<"$LISTING" || ok=0
    done
    if (( ok )); then echo "$d"; return 0; fi
  done < <(cut -f1 <<<"$LISTING" | grep -oE '^[0-9]{4}-[0-9]{2}-[0-9]{2}' | sort -ru)
  return 1
}

dump_field() { # data etykieta kolumna(1 nazwa,2 rozmiar,3 mtime)
  awk -F'\t' -v p="^$1_$2_" -v c="$3" '$1 ~ p { print $c; exit }' <<<"$LISTING"
}

migration_diff() { # zrzut katalog
  local dump="$1" dir="$2" applied pending
  applied="$(gzip -dc "$dump" \
    | awk '/^INSERT INTO `migrations`/ {f=1} f {print} f && /\);$/ {exit}' \
    | grep -oE "'[0-9]{4}_[0-9]{2}_[0-9]{2}_[A-Za-z0-9_]+'" | tr -d "'" | sort -u || true)"
  pending="$(comm -13 <(printf '%s\n' "$applied") \
    <(find "$dir/database/migrations" -maxdepth 1 -name '*.php' -printf '%f\n' 2>/dev/null | sed 's/\.php$//' | sort -u) || true)"
  if [[ -z "$applied" ]]; then
    warn "$(basename "$dir"): nie wykryto tabeli migrations w zrzucie"
  elif [[ -z "$pending" ]]; then
    say "  $(basename "$dir"): repo nie ma migracji, których produkcja jeszcze nie wykonała"
  else
    say "  $(basename "$dir"): migracje z repo, których NIE MA jeszcze na produkcji (wykonają się lokalnie):"
    sed 's/^/       /' <<<"$pending"
  fi
}

download_dump() { # nazwa rozmiar
  local name="$1" size="$2" dest="$DEV_SYNC_HOME/dumps/$1" try rc
  if [[ -f "$dest" && "$(stat -c %s "$dest")" == "$size" ]] && gzip -t "$dest" 2>/dev/null; then
    info "   $name — już pobrany ($(numfmt --to=iec "$size"))"; return 0
  fi
  PART_FILE="$dest.part"
  for try in 1 2 3; do
    rc=0
    "${SSH_BASE[@]}" "${SSH_USER}@${SSH_HOST}" "cat $name" > "$PART_FILE" || rc=$?
    if (( rc == 0 )) && [[ "$(stat -c %s "$PART_FILE")" == "$size" ]] && gzip -t "$PART_FILE" 2>/dev/null; then
      mv "$PART_FILE" "$dest"; chmod 600 "$dest"; PART_FILE=""
      say "   $name — pobrany ($(numfmt --to=iec "$size"))"; return 0
    fi
    warn "pobieranie $name nieudane (rc=$rc), próba $try/3"; sleep $((try * 20))
  done
  die "Nie udało się pobrać $name"
}

snapshot_local() { # etykieta baza
  local label="$1" db="$2" ts="$3" dest
  if [[ "$(mysql_root -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='${db}'")" != "1" ]]; then
    info "   snapshot $db: baza nie istnieje, pomijam"; return 0
  fi
  dest="$DEV_SYNC_HOME/snapshots/${ts}_${label}_${db}.sql.gz"
  mysqldump_root --single-transaction --quick --routines --triggers --hex-blob --default-character-set=utf8mb4 --no-tablespaces "$db" | gzip -c > "$dest"
  chmod 600 "$dest"
  say "   snapshot lokalnej bazy $db -> $(basename "$dest") ($(du -h "$dest" | cut -f1))"
}

rotate_snapshots() {
  local stamps
  stamps="$(find "$DEV_SYNC_HOME/snapshots" -maxdepth 1 -name '*.sql.gz' -printf '%f\n' | cut -d_ -f1-2 | sort -ru | tail -n +$((KEEP_SNAPSHOTS + 1)))"
  [[ -z "$stamps" ]] && return 0
  while read -r s; do
    [[ -n "$s" ]] && find "$DEV_SYNC_HOME/snapshots" -maxdepth 1 -name "${s}_*.sql.gz" -delete
  done <<<"$stamps"
}

import_dump() { # plik docelowa_baza
  local file="$1" db="$2" cs col
  cs="$(mysql_root -N -B -e "SELECT default_character_set_name FROM information_schema.schemata WHERE schema_name='${db%_scratch}'" 2>/dev/null || true)"
  col="$(mysql_root -N -B -e "SELECT default_collation_name FROM information_schema.schemata WHERE schema_name='${db%_scratch}'" 2>/dev/null || true)"
  cs="${cs:-utf8mb4}"; col="${col:-utf8mb4_unicode_ci}"
  mysql_root -e "DROP DATABASE IF EXISTS \`${db}\`; CREATE DATABASE \`${db}\` CHARACTER SET ${cs} COLLATE ${col};"
  # Bez DEFINER (konto prod nie istnieje lokalnie) i BEZ danych kolejki/sesji/cache:
  # prawdziwe zadania z produkcji nigdy nie trafiają do lokalnej bazy.
  # Zrzut ma INSERT-y wielolinijkowe ("INSERT INTO `t` VALUES" + wiersze do linii kończącej się ");"),
  # ale obsługujemy też jednolinijkowe.
  gzip -dc "$file" \
    | sed -E 's/DEFINER=`[^`]*`@`[^`]*`//g' \
    | sed -E -e '/^INSERT INTO `(jobs|job_batches|failed_jobs|sessions|cache|cache_locks)` VALUES .*\);$/d' \
             -e '/^INSERT INTO `(jobs|job_batches|failed_jobs|sessions|cache|cache_locks)` VALUES$/,/\);$/d' \
    | mysql_root --default-character-set=utf8mb4 --init-command="SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'" "$db"
}

queue_tables_empty_check() { # baza
  local db="$1" t n
  for t in jobs job_batches failed_jobs sessions cache cache_locks; do
    if [[ "$(mysql_root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}' AND table_name='${t}'")" == "1" ]]; then
      n="$(mysql_root -N -B -e "SELECT COUNT(*) FROM \`${db}\`.\`${t}\`")"
      (( n == 0 )) || die "Po imporcie ${db}.${t} nie jest pusta ($n wierszy) — przerywam."
    fi
  done
}

do_db() {
  step "Bazy z produkcji -> lokalny MySQL"
  need_containers
  init_mysql
  local out
  out="$(remote list)" || die "Nie udało się pobrać listy zrzutów (polecenie 'list')."
  LISTING="$out"
  local date label name size mtime now age_h
  date="$(pick_date)" || die "Brak kompletnego zestawu 4 zrzutów (pneadm, certgen, analytics, pnedu) na produkcji."
  now="$(date +%s)"
  mtime="$(dump_field "$date" pneadm 3)"
  age_h=$(( (now - mtime) / 3600 ))
  say "Zestaw zrzutów z dnia $date (pneadm sprzed ok. ${age_h} h)"
  (( age_h > MAX_DUMP_AGE_HOURS )) && warn "Zrzut starszy niż ${MAX_DUMP_AGE_HOURS} h — sprawdź nocny cron backupu na SeoHost."

  declare -A F S
  for label in pneadm certgen analytics pnedu; do
    F[$label]="$(dump_field "$date" "$label" 1)"; S[$label]="$(dump_field "$date" "$label" 2)"
    printf '   %-9s %-55s -> lokalna baza: %s (%s)\n' "$label" "${F[$label]}" "$(local_db_for "$label")" "$(numfmt --to=iec "${S[$label]}")"
  done

  if (( DRY )); then
    say "[dry-run] pominięto pobieranie, import i migracje."
    return 0
  fi

  if (( ! SCRATCH )); then guard_env; fi

  df_free="$(df -Pk "$DEV_SYNC_HOME" | awk 'NR==2 {print $4}')"
  (( df_free > 2*1024*1024 )) || die "Za mało miejsca na dysku (< 2 GB)."

  step "Pobieranie zrzutów (tylko odczyt z produkcji)"
  for label in pneadm certgen analytics pnedu; do download_dump "${F[$label]}" "${S[$label]}"; done

  step "Różnice migracji: repo vs produkcja"
  migration_diff "$DEV_SYNC_HOME/dumps/${F[pneadm]}" "$PNEADM_DIR"
  migration_diff "$DEV_SYNC_HOME/dumps/${F[pnedu]}" "$PNEDU_DIR"

  if (( SCRATCH )); then
    step "TEST importu do baz *_scratch (Twoje właściwe bazy nie są ruszane)"
    for label in pneadm certgen analytics pnedu; do
      db="$(local_db_for "$label")_scratch"
      say "   import ${F[$label]} -> $db"
      import_dump "$DEV_SYNC_HOME/dumps/${F[$label]}" "$db"
      say "      tabel: $(mysql_root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}'")"
      queue_tables_empty_check "$db"
      mysql_root -e "DROP DATABASE \`${db}\`"
    done
    say "TEST importu zakończony powodzeniem, bazy *_scratch usunięte."
    return 0
  fi

  say
  say "Import ZASTĄPI lokalne bazy: $DB_PNEADM, $DB_CERTGEN, $DB_ANALYTICS, $DB_PNEDU (kontener $MYSQL_CONTAINER)."
  say "Przed tym powstanie snapshot obecnych baz w $DEV_SYNC_HOME/snapshots (ostatnie $KEEP_SNAPSHOTS zestawy)."
  say "Dane utworzone tylko lokalnie (nieprzeniesione na produkcję) znikną z bieżącej bazy."
  confirm "" || die "Przerwano przez użytkownika."

  local ts
  ts="$(date +%Y%m%d_%H%M%S)"
  step "Snapshot lokalnych baz"
  for label in pneadm certgen analytics pnedu; do snapshot_local "$label" "$(local_db_for "$label")" "$ts"; done
  rotate_snapshots

  step "Import"
  for label in pneadm certgen analytics pnedu; do
    db="$(local_db_for "$label")"
    say "   ${F[$label]} -> $db"
    import_dump "$DEV_SYNC_HOME/dumps/${F[$label]}" "$db"
  done
  for label in pneadm pnedu; do queue_tables_empty_check "$(local_db_for "$label")"; done
  say "   kolejka/sesje/cache: puste (dane z produkcji celowo pominięte)"
  log "DB import date=$date snapshot=$ts"

  do_migrate
  for dir in "$PNEADM_DIR" "$PNEDU_DIR"; do sail_in "$dir" artisan optimize:clear --no-interaction >/dev/null 2>&1 || true; done
  say "Gotowe: lokalna baza = produkcja z $date + lokalne migracje."
  info "Cofnięcie: gunzip -c $DEV_SYNC_HOME/snapshots/${ts}_<etykieta>_<baza>.sql.gz | docker exec -i -e MYSQL_PWD $MYSQL_CONTAINER mysql -uroot <baza>"
}

# ---------- PLIKI ----------
rsync_pull() { # etykieta ścieżka_zdalna katalog_lokalny [excludes...]
  local label="$1" remote_path="$2" dest="$3"; shift 3
  local opts=(-rlt --partial --chmod=D775,F664 --stats -e "$RSYNC_RSH")
  (( DRY )) && opts+=(-n)
  (( DELETE_FILES )) && opts+=(--delete)
  local ex
  for ex in "$@"; do opts+=("--exclude=$ex"); done
  mkdir -p "$dest"
  local try rc
  for try in 1 2 3; do
    set +e
    rsync "${opts[@]}" "${SSH_USER}@${SSH_HOST}:${remote_path}" "$dest/" 2>&1 \
      | grep -vE '^(Number of (created|deleted)|Literal data|Matched data|File list|Total (bytes|transferred)|sent |total size|Number of regular)' \
      | sed 's/^/   /'
    rc="${PIPESTATUS[0]}"
    set -e
    (( rc == 0 )) && return 0
    case "$rc" in 255|12|30|35) warn "rsync $label: rc=$rc, próba $try/3"; sleep $((try * 20)) ;; *) die "rsync $label zakończył się kodem $rc" ;; esac
  done
  die "rsync $label nie powiódł się"
}

do_files() {
  step "Pliki: storage/app z produkcji (tylko pobieranie)"
  # Certyfikaty (wygenerowane PDF-y) NIGDY nie są pobierane na deva (decyzja Waldemara
  # 2026-11-01): można je wygenerować ponownie z danych w bazie.
  local ex=("/public/certificates/")
  info "   (pomijam storage/app/public/certificates — celowo, zawsze)"
  (( DELETE_FILES )) && warn "--delete-files: lokalne pliki nieobecne na produkcji zostaną skasowane."
  say "-- pneadm"
  rsync_pull pneadm "domains/adm.pnedu.pl/pneadm/storage/app/" "$PNEADM_DIR/storage/app" "${ex[@]}"
  say "-- pnedu"
  rsync_pull pnedu "domains/pnedu.pl/app/storage/app/" "$PNEDU_DIR/storage/app"
  log "FILES done dry=$DRY delete=$DELETE_FILES"
}

# ---------- GŁÓWNY PRZEBIEG ----------
step "Połączenie z produkcją (tylko odczyt)"
pong="$(remote ping 2>&1)" || die "Brak połączenia z produkcją: $pong
   Sprawdź: (1) klucz odblokowany w agencie: ssh-add -t 3600 $SSH_KEY  (2) SSH_PORT w $CONFIG_FILE  (3) odczekaj kilka minut, jeśli serwer chwilowo odrzuca połączenia."
[[ "$pong" == "OK pne-devsync" ]] || die "Nieoczekiwana odpowiedź serwera: $pong"
say "OK ($SSH_USER@$SSH_HOST:$SSH_PORT)"

report_heads
report_env_keys

if (( DO_CODE )); then
  do_code
  (( DO_DB )) || do_migrate   # przy --db migrate wykona się po imporcie
fi
if (( DO_DB )); then do_db; fi
if (( DO_FILES )); then do_files; fi

step "Koniec"
log "END"
say "Gotowe."

#!/usr/bin/env bash
# Diagnostyka kolejki produkcyjnej (pnedu + pneadm) — worker, backlog, dopływ eventów.
#
# Użycie (SSH):
#   bash docs/deploy/scripts/prod-queue-healthcheck.sh
#   bash docs/deploy/scripts/prod-queue-healthcheck.sh --restart
#   bash docs/deploy/scripts/prod-queue-healthcheck.sh --strict
#
# Zmienne środowiskowe (opcjonalnie):
#   PNEADM_DIR, PNEDU_DIR, PHP_BIN
#   QUEUE_JOBS_WARN=50   — ostrzeżenie gdy analytics jobs >= ta wartość
#   QUEUE_JOBS_FAIL=500  — błąd gdy analytics jobs >= ta wartość
#
# Domyślnie read-only. --restart wywołuje queue:restart na obu aplikacjach.
# --strict: brak eventów w 15 min w godzinach 07–22 (Europe/Warsaw) = exit 1.
#
# Runbook: docs/deploy/PRODUCTION_QUEUE_OPS.md

set -euo pipefail

PNEADM_DIR="${PNEADM_DIR:-$HOME/domains/adm.pnedu.pl/pneadm}"
PNEDU_DIR="${PNEDU_DIR:-$HOME/domains/pnedu.pl/app}"
PHP_BIN="${PHP_BIN:-/opt/alt/php82/usr/bin/php}"
QUEUE_JOBS_WARN="${QUEUE_JOBS_WARN:-50}"
QUEUE_JOBS_FAIL="${QUEUE_JOBS_FAIL:-500}"

DO_RESTART=0
STRICT=0

for arg in "$@"; do
  case "$arg" in
    --restart) DO_RESTART=1 ;;
    --strict) STRICT=1 ;;
    -h|--help)
      sed -n '1,20p' "$0" | tail -n +2
      exit 0
      ;;
    *)
      echo "Nieznany argument: $arg (użyj --help)" >&2
      exit 2
      ;;
  esac
done

NOW="$(TZ=Europe/Warsaw date '+%F %T %Z')"
HOUR="$(TZ=Europe/Warsaw date +%H)"
BUSINESS_HOURS=0
if [[ "$HOUR" -ge 7 && "$HOUR" -lt 22 ]]; then
  BUSINESS_HOURS=1
fi

FAILURES=0
WARNINGS=0

section() {
  echo ""
  echo "================================================================================"
  echo "=== $1"
  echo "================================================================================"
}

warn() {
  echo ">>> WARN: $1" >&2
  WARNINGS=$((WARNINGS + 1))
}

fail() {
  echo ">>> FAIL: $1" >&2
  FAILURES=$((FAILURES + 1))
}

read_env() {
  local key="$1"
  local file="$2"
  if [[ ! -f "$file" ]]; then
    echo ""
    return 0
  fi
  grep -E "^${key}=" "$file" | head -1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
}

artisan_tinker() {
  local dir="$1"
  local php_code="$2"
  (cd "$dir" && "$PHP_BIN" artisan tinker --execute="$php_code" 2>/dev/null) || echo "?"
}

if [[ ! -d "$PNEADM_DIR" || ! -f "$PNEADM_DIR/.env" ]]; then
  echo "BŁĄD: brak pneadm: $PNEADM_DIR (.env)" >&2
  exit 2
fi

if [[ ! -d "$PNEDU_DIR" || ! -f "$PNEDU_DIR/.env" ]]; then
  echo "BŁĄD: brak pnedu: $PNEDU_DIR (.env)" >&2
  exit 2
fi

section "META — $NOW"
echo "pneadm: $PNEADM_DIR ($(cd "$PNEADM_DIR" && git rev-parse --short HEAD 2>/dev/null || echo '?'))"
echo "pnedu:  $PNEDU_DIR ($(cd "$PNEDU_DIR" && git rev-parse --short HEAD 2>/dev/null || echo '?'))"
echo "PHP:    $PHP_BIN"
echo "strict: $STRICT | business_hours (07–22 PL): $BUSINESS_HOURS"

section "1) Procesy queue:work"
WORKERS="$(ps aux 2>/dev/null | grep "[q]ueue:work" || true)"
if [[ -z "$WORKERS" ]]; then
  fail "Brak działających procesów queue:work — eventy analityki nie trafią do analytics_events."
else
  echo "$WORKERS"
fi

PNEDU_WORKERS="$(echo "$WORKERS" | grep -c "pnedu.pl" || true)"
PNEADM_WORKERS="$(echo "$WORKERS" | grep -c "adm.pnedu.pl\|pneadm" || true)"

if [[ "$PNEDU_WORKERS" -eq 0 ]]; then
  fail "Brak workera przypisanego do pnedu.pl — kluczowy dla „Aktywni teraz” i trackingu."
fi

if [[ "$PNEADM_WORKERS" -eq 0 ]]; then
  warn "Brak workera pneadm (PDF, joby z adm) — sprawdź cron adm.pnedu.pl."
fi

section "2) Backlog kolejki analytics (pnedu — tabela jobs)"
JOBS_ANALYTICS="$(artisan_tinker "$PNEDU_DIR" "echo (int) DB::table('jobs')->where('queue','analytics')->count();")"
JOBS_TOTAL="$(artisan_tinker "$PNEDU_DIR" "echo (int) DB::table('jobs')->count();")"
FAILED_JOBS="$(artisan_tinker "$PNEDU_DIR" "echo (int) DB::table('failed_jobs')->count();")"

echo "jobs (analytics): $JOBS_ANALYTICS"
echo "jobs (total):     $JOBS_TOTAL"
echo "failed_jobs:      $FAILED_JOBS"

if [[ "$JOBS_ANALYTICS" =~ ^[0-9]+$ ]]; then
  if [[ "$JOBS_ANALYTICS" -ge "$QUEUE_JOBS_FAIL" ]]; then
    fail "Backlog analytics >= $QUEUE_JOBS_FAIL — worker prawdopodobnie nie nadąża lub jest zatrzymany."
  elif [[ "$JOBS_ANALYTICS" -ge "$QUEUE_JOBS_WARN" ]]; then
    warn "Backlog analytics >= $QUEUE_JOBS_WARN — monitoruj queue-worker.log."
  fi
else
  warn "Nie udało się odczytać liczby jobów analytics (tinker)."
fi

section "3) Dopływ eventów (analytics_events, ostatnie 15 min)"
EVENTS_15M="$(artisan_tinker "$PNEADM_DIR" "echo (int) DB::connection('analytics')->table('analytics_events')->where('occurred_at','>=',now()->subMinutes(15))->count();")"
LAST_EVENT="$(artisan_tinker "$PNEADM_DIR" "echo DB::connection('analytics')->table('analytics_events')->max('occurred_at') ?? 'brak';")"

echo "events last 15 min: $EVENTS_15M"
echo "last event at:      $LAST_EVENT"

if [[ "$EVENTS_15M" =~ ^[0-9]+$ ]]; then
  if [[ "$EVENTS_15M" -eq 0 && "$JOBS_ANALYTICS" =~ ^[0-9]+$ && "$JOBS_ANALYTICS" -gt 10 ]]; then
    fail "0 eventów w 15 min przy rosnącym backlogu — worker nie zapisuje do bazy."
  elif [[ "$EVENTS_15M" -eq 0 && "$BUSINESS_HOURS" -eq 1 ]]; then
    if [[ "$STRICT" -eq 1 ]]; then
      fail "0 eventów w godzinach szczytu (07–22 PL) — sprawdź tracking lub worker pnedu."
    else
      warn "0 eventów w 15 min w dzień — jeśli był ruch na pnedu.pl, uruchom z --strict lub --restart."
    fi
  fi
else
  warn "Nie udało się odczytać liczby eventów (tinker / połączenie analytics)."
fi

section "4) Konfiguracja .env (skrót)"
echo "pnedu ANALYTICS_ENABLED=$(read_env ANALYTICS_ENABLED "$PNEDU_DIR/.env")"
echo "pnedu ANALYTICS_QUEUE_CONNECTION=$(read_env ANALYTICS_QUEUE_CONNECTION "$PNEDU_DIR/.env")"
echo "pnedu QUEUE_CONNECTION=$(read_env QUEUE_CONNECTION "$PNEDU_DIR/.env")"
echo "pneadm QUEUE_CONNECTION=$(read_env QUEUE_CONNECTION "$PNEADM_DIR/.env")"

section "5) Logi queue-worker (tail)"
for label_dir in "pnedu:$PNEDU_DIR" "pneadm:$PNEADM_DIR"; do
  label="${label_dir%%:*}"
  dir="${label_dir#*:}"
  log="$dir/storage/logs/queue-worker.log"
  echo "--- $label: $log"
  if [[ -f "$log" ]]; then
    tail -n 15 "$log" 2>/dev/null || true
  else
    echo "(brak pliku — cron może logować gdzie indziej lub jeszcze nie było uruchomienia)"
  fi
done

section "6) Pliki flock (informacyjnie)"
for pattern in /tmp/pneadm*.lock /tmp/pnedu*.lock "$PNEDU_DIR/storage/locks/"* "$PNEADM_DIR/storage/locks/"*; do
  [[ -e "$pattern" ]] || continue
  ls -la "$pattern" 2>/dev/null || true
done

if [[ "$DO_RESTART" -eq 1 ]]; then
  section "7) queue:restart (obie aplikacje)"
  (cd "$PNEDU_DIR" && "$PHP_BIN" artisan queue:restart)
  echo "pnedu: queue:restart OK"
  (cd "$PNEADM_DIR" && "$PHP_BIN" artisan queue:restart)
  echo "pneadm: queue:restart OK"
  echo "Poczekaj ~1 min (następny tick crona) i uruchom skrypt ponownie bez --restart."
fi

section "PODSUMOWANIE"
echo "warnings: $WARNINGS | failures: $FAILURES"
if [[ "$FAILURES" -gt 0 ]]; then
  echo "WERDYKT: PROBLEM — zob. docs/deploy/PRODUCTION_QUEUE_OPS.md"
  echo "Szybka próba naprawy: bash $0 --restart"
  exit 1
fi

if [[ "$WARNINGS" -gt 0 ]]; then
  echo "WERDYKT: OK z ostrzeżeniami"
  exit 0
fi

echo "WERDYKT: OK"
exit 0

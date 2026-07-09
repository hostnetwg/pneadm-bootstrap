#!/usr/bin/env bash
# Weryfikacja post-deploy B4+ / 2F — produkcja pneadm + pnedu
# Użycie (SSH):
#   bash prod-b4-2f-post-deploy-verify.sh
# lub wklej całą zawartość do terminala.
#
# READ-ONLY (SQL + healthcheck + logi). Opcjonalny rebuild na końcu — zakomentowany.

set -euo pipefail

PNEADM_DIR="${PNEADM_DIR:-$HOME/domains/adm.pnedu.pl/pneadm}"
PNEDU_DIR="${PNEDU_DIR:-$HOME/domains/pnedu.pl/app}"
PHP_BIN="${PHP_BIN:-/opt/alt/php82/usr/bin/php}"

if [[ ! -d "$PNEADM_DIR" ]]; then
  echo "BŁĄD: brak katalogu pneadm: $PNEADM_DIR" >&2
  echo "Ustaw: export PNEADM_DIR=/ścieżka/do/pneadm" >&2
  exit 1
fi

if [[ ! -f "$PNEADM_DIR/.env" ]]; then
  echo "BŁĄD: brak $PNEADM_DIR/.env" >&2
  exit 1
fi
TODAY="$(TZ=Europe/Warsaw date +%F)"
NOW="$(TZ=Europe/Warsaw date '+%F %T %Z')"

section() {
  echo ""
  echo "================================================================================"
  echo "=== $1"
  echo "================================================================================"
}

read_env() {
  local key="$1"
  local file="$2"
  if [[ ! -f "$file" ]]; then
    echo "BRAK PLIKU: $file" >&2
    return 1
  fi
  grep -E "^${key}=" "$file" | head -1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
}

run_mysql_analytics() {
  local sql="$1"
  local host port db user pass
  host="$(read_env DB_ANALYTICS_HOST "$PNEADM_DIR/.env")"
  port="$(read_env DB_ANALYTICS_PORT "$PNEADM_DIR/.env")"
  db="$(read_env DB_ANALYTICS_DATABASE "$PNEADM_DIR/.env")"
  user="$(read_env DB_ANALYTICS_USERNAME "$PNEADM_DIR/.env")"
  pass="$(read_env DB_ANALYTICS_PASSWORD "$PNEADM_DIR/.env")"
  host="${host:-localhost}"
  port="${port:-3306}"
  MYSQL_PWD="$pass" mysql -h"$host" -P"$port" -u"$user" "$db" -e "$sql"
}

section "META — $NOW | pneadm=$PNEADM_DIR | pnedu=$PNEDU_DIR"
echo "Git pneadm: $(cd "$PNEADM_DIR" && git rev-parse --short HEAD 2>/dev/null || echo '?')"
echo "Git pnedu:  $(cd "$PNEDU_DIR" && git rev-parse --short HEAD 2>/dev/null || echo '?')"

section "1) Pierwszy event v2 (analytics_events)"
run_mysql_analytics "
SELECT
  id,
  event_name,
  occurred_at,
  order_form_session_id,
  course_id,
  JSON_UNQUOTE(JSON_EXTRACT(metadata, '\$.tracking_schema_version')) AS schema_v
FROM analytics_events
WHERE JSON_EXTRACT(metadata, '\$.tracking_schema_version') = 2
   OR event_name IN (
        'form_visible', 'form_first_interaction', 'form_section_viewed',
        'form_section_started', 'form_section_completed', 'form_field_changed',
        'form_submit_clicked', 'client_validation_failed', 'form_last_activity',
        'gus_lookup_clicked', 'gus_lookup_started', 'gus_lookup_success',
        'gus_lookup_error', 'gus_data_applied'
      )
ORDER BY occurred_at ASC, id ASC
LIMIT 1;
"

section "1b) Zakres eventów v2 (podsumowanie)"
run_mysql_analytics "
SELECT
  COUNT(*) AS events_v2,
  MIN(occurred_at) AS first_v2,
  MAX(occurred_at) AS last_v2
FROM analytics_events
WHERE JSON_EXTRACT(metadata, '\$.tracking_schema_version') = 2
   OR event_name IN ('form_visible', 'form_first_interaction', 'gus_lookup_clicked');
"

section "2) Migracje B4+ (pne_analytics.migrations)"
run_mysql_analytics "
SELECT migration, batch
FROM migrations
WHERE migration LIKE '%2026_07_09%'
ORDER BY migration;
"

section "3) Cron / scheduler — wpis w kodzie (routes/console.php)"
grep -n "aggregate-order-forms" "$PNEADM_DIR/routes/console.php" || echo "Nie znaleziono wpisu schedule"

section "4) order_form_attributions po wdrożeniu 2F (>= 2026-07-09)"
run_mysql_analytics "
SELECT COUNT(*) AS cnt_since_2026_07_09
FROM order_form_attributions
WHERE created_at >= '2026-07-09 00:00:00';

SELECT MIN(created_at) AS first_row, MAX(created_at) AS last_row, COUNT(*) AS total_rows
FROM order_form_attributions;
"

section "5) Rozkład traffic_channel (>= 2026-07-09)"
run_mysql_analytics "
SELECT traffic_channel, COUNT(*) AS cnt
FROM order_form_attributions
WHERE created_at >= '2026-07-09 00:00:00'
GROUP BY traffic_channel
ORDER BY cnt DESC;
"

section "6) Agregaty B4 — zakres dat i liczba wierszy"
run_mysql_analytics "
SELECT 'course_channel' AS tbl, COUNT(*) AS rows_cnt, MIN(stat_date) AS min_d, MAX(stat_date) AS max_d
FROM analytics_daily_course_channel_funnels
UNION ALL
SELECT 'channel', COUNT(*), MIN(stat_date), MAX(stat_date) FROM analytics_daily_channel_funnels
UNION ALL
SELECT 'campaign', COUNT(*), MIN(stat_date), MAX(stat_date) FROM analytics_daily_campaign_funnels
UNION ALL
SELECT 'data_quality', COUNT(*), MIN(stat_date), MAX(stat_date) FROM analytics_daily_data_quality
UNION ALL
SELECT 'gus_channel', COUNT(*), MIN(stat_date), MAX(stat_date) FROM analytics_daily_gus_channel_funnels;
"

section "7) Jakość danych — statusy (ostatnie 20 dni)"
run_mysql_analytics "
SELECT stat_date, sessions_total, tracking_data_quality_score,
       tracking_data_quality_status, tracking_data_quality_flags
FROM analytics_daily_data_quality
WHERE stat_date >= DATE_SUB('$TODAY', INTERVAL 20 DAY)
ORDER BY stat_date DESC;
"

section "8) Dzień wdrożeniowy 2026-07-09 (warmup / deploy)"
run_mysql_analytics "
SELECT stat_date, tracking_data_quality_status, tracking_data_quality_flags,
       sessions_total, attribution_coverage_rate, schema_v2_event_rate
FROM analytics_daily_data_quality
WHERE stat_date IN ('2026-07-07', '2026-07-08', '2026-07-09')
ORDER BY stat_date;
"

section "9) tracking_schema_version + unique index (course_channel_funnels)"
run_mysql_analytics "
SHOW COLUMNS FROM analytics_daily_course_channel_funnels LIKE 'tracking_schema_version';
SHOW INDEX FROM analytics_daily_course_channel_funnels WHERE Key_name = 'course_channel_funnels_uq';
SELECT tracking_schema_version, COUNT(*) AS rows_cnt
FROM analytics_daily_course_channel_funnels
GROUP BY tracking_schema_version;
"

section "10) Healthcheck B4+ (ostatnie 3 dni z lag=2 → nie obejmuje dziś)"
cd "$PNEADM_DIR"
"$PHP_BIN" artisan analytics:order-form-funnel-healthcheck --days=3

section "10b) Healthcheck — dni historyczne przed atrybucją 2F"
"$PHP_BIN" artisan analytics:order-form-funnel-healthcheck --from=2026-06-25 --to=2026-07-08

section "10c) Healthcheck — dzień wdrożeniowy 2F (jeśli są agregaty)"
"$PHP_BIN" artisan analytics:order-form-funnel-healthcheck --from=2026-07-09 --to=2026-07-09 || true

section "11) Logi — cron B4 (jeśli istnieje)"
LOG_B4="$PNEADM_DIR/storage/logs/analytics-order-forms.log"
if [[ -f "$LOG_B4" ]]; then
  echo "--- tail -30 $LOG_B4"
  tail -30 "$LOG_B4"
else
  echo "Brak pliku: $LOG_B4 (normalne przed pierwszym runem z przekierowaniem logu)"
fi

grep_logs() {
  local dir="$1"
  local pattern="$2"
  local label="$3"
  echo "--- $label"
  if [[ ! -d "$dir/storage/logs" ]]; then
    echo "Brak katalogu: $dir/storage/logs"
    return 0
  fi
  ls -la "$dir/storage/logs" 2>/dev/null | tail -15 || true
  # single laravel.log lub daily laravel-YYYY-MM-DD.log
  local found=0
  for f in "$dir/storage/logs/laravel.log" "$dir/storage/logs"/laravel-*.log; do
    [[ -f "$f" ]] || continue
    found=1
    echo ">>> $f"
    grep -iE "$pattern" "$f" 2>/dev/null | tail -25 || true
  done
  if [[ "$found" -eq 0 ]]; then
    echo "Brak laravel.log / laravel-*.log (LOG_LEVEL może być wyłączony lub logi w innym miejscu)"
  fi
}

section "12) Logi — Laravel ERROR (pneadm)"
grep_logs "$PNEADM_DIR" "ERROR|CRITICAL|tracking_schema_version|aggregate-order-forms" "pneadm"

section "13) Logi — Laravel ERROR (pnedu)"
grep_logs "$PNEDU_DIR" "ERROR|CRITICAL|analytics|GusLookup|client-events" "pnedu"

section "14) Dopływ eventów v2 — ostatnia godzina (SQL)"
run_mysql_analytics "
SELECT COUNT(*) AS form_views_last_60m
FROM analytics_events
WHERE occurred_at >= UTC_TIMESTAMP() - INTERVAL 60 MINUTE
  AND event_name IN ('order_form_viewed', 'form_visible');

SELECT COUNT(*) AS v2_events_last_60m
FROM analytics_events
WHERE occurred_at >= UTC_TIMESTAMP() - INTERVAL 60 MINUTE
  AND (
    JSON_EXTRACT(metadata, '\$.tracking_schema_version') = 2
    OR event_name IN ('form_visible', 'form_first_interaction', 'gus_lookup_clicked')
  );
"

section "GOTOWE"
echo "Wklej cały output do ChatGPT / dokumentacji deploy."
echo ""
echo "Opcjonalnie (NIE uruchamiaj w tym skrypcie automatycznie):"
echo "  cd $PNEADM_DIR"
echo "  $PHP_BIN artisan analytics:aggregate-order-forms --date=$TODAY --rebuild"
echo "  $PHP_BIN artisan analytics:order-form-funnel-healthcheck --from=$TODAY --to=$TODAY"

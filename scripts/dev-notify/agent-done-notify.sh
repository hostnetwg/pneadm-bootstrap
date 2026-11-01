#!/bin/bash
# Hook Cursora (zdarzenie "stop"): powiadomienie, gdy agent skończy zadanie.
#   1. dźwięk na Windowsie (WSL2 -> powershell.exe), jeśli jest dostępny,
#   2. push na telefon przez ntfy, jeśli jest skonfigurowany temat.
# Instalacja na komputerze: scripts/dev-notify/install.sh. Opis: docs/deploy/DEV_NOTIFICATIONS.md
#
# Prywatność: wiadomość zawiera tylko status, nazwę komputera i nazwę projektu.
# NIGDY treści rozmowy, kodu, ścieżek, e-maili ani danych z baz.
#
# Konfiguracja (poza repo): ~/.config/cursor-notify/ntfy.env
#   NTFY_TOPIC=...            (wymagane do push; brak = tylko dźwięk)
#   NTFY_SERVER=https://ntfy.sh   (opcjonalnie)
#   NTFY_TOKEN=...            (opcjonalnie, dla tematu chronionego tokenem)
#   NOTIFY_SOUND=0            (opcjonalnie, wyłącza dźwięk)

INPUT="$(cat 2>/dev/null)"   # JSON od Cursora (stdin)
CONF="${CURSOR_NOTIFY_CONF:-$HOME/.config/cursor-notify/ntfy.env}"

NTFY_SERVER="https://ntfy.sh"
NOTIFY_SOUND=1
# shellcheck disable=SC1090
[ -f "$CONF" ] && . "$CONF"

# --- status i nazwa projektu z JSON-a (python3, bez jq); w razie braku wartości domyślne ---
STATUS="completed"; PROJECT=""
if command -v python3 >/dev/null 2>&1; then
  PARSED="$(printf '%s' "$INPUT" | python3 -c '
import json, os, sys
try:
    d = json.load(sys.stdin)
except Exception:
    d = {}
roots = d.get("workspace_roots") or []
proj = os.path.basename(str(roots[0]).rstrip("/")) if roots else ""
print((d.get("status") or "completed") + "|" + proj)
' 2>/dev/null)"
  STATUS="${PARSED%%|*}"; PROJECT="${PARSED#*|}"
fi
[ -n "$PROJECT" ] || PROJECT="$(basename "$PWD")"
HOST="$(hostname -s 2>/dev/null || echo komputer)"

case "$STATUS" in
  completed) TITLE="Cursor: gotowe";    MSG="Zadanie zakonczone ($HOST, $PROJECT)."; TAGS="white_check_mark"; PRIO="default" ;;
  aborted)   TITLE="Cursor: przerwane"; MSG="Zadanie przerwane ($HOST, $PROJECT).";  TAGS="warning";            PRIO="default" ;;
  *)         TITLE="Cursor: blad";      MSG="Zadanie zakonczone bledem ($HOST, $PROJECT)."; TAGS="rotating_light"; PRIO="high" ;;
esac

# --- 1. dźwięk (Windows przez WSL) ---
PS=/mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe
if [ "$NOTIFY_SOUND" != "0" ] && [ -x "$PS" ]; then
  nohup "$PS" -NoProfile -NonInteractive -WindowStyle Hidden -Command \
    "(New-Object System.Media.SoundPlayer 'C:\Windows\Media\tada.wav').PlaySync()" >/dev/null 2>&1 &
fi

# --- 2. push na telefon (ntfy) ---
if [ -n "${NTFY_TOPIC:-}" ] && command -v curl >/dev/null 2>&1; then
  AUTH=()
  [ -n "${NTFY_TOKEN:-}" ] && AUTH=(-H "Authorization: Bearer ${NTFY_TOKEN}")
  nohup curl -sS -m 8 "${AUTH[@]}" \
    -H "Title: $TITLE" -H "Tags: $TAGS" -H "Priority: $PRIO" \
    -d "$MSG" "${NTFY_SERVER%/}/${NTFY_TOPIC}" >/dev/null 2>&1 &
fi

echo '{}'
exit 0

#!/bin/bash
# Instaluje powiadomienia "agent skończył zadanie" (dźwięk + ntfy) dla Cursora na TYM komputerze.
# Użycie:  scripts/dev-notify/install.sh [NAZWA_TEMATU_NTFY] [--test]
# Bezpieczne do ponownego uruchomienia. Nie nadpisuje cudzych hooków w ~/.cursor/hooks.json.
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOOK_DIR="$HOME/.cursor/hooks"
HOOKS_JSON="$HOME/.cursor/hooks.json"
CONF_DIR="$HOME/.config/cursor-notify"
CONF="$CONF_DIR/ntfy.env"
TOPIC=""; DO_TEST=0

for a in "$@"; do
  case "$a" in
    --test) DO_TEST=1 ;;
    -h|--help) sed -n '2,5p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *) TOPIC="$a" ;;
  esac
done

command -v python3 >/dev/null || { echo "Brak python3 (potrzebny do scalenia hooks.json)." >&2; exit 1; }
command -v curl >/dev/null || { echo "Brak curl." >&2; exit 1; }

mkdir -p "$HOOK_DIR" "$CONF_DIR"
install -m 755 "$SRC_DIR/agent-done-notify.sh" "$HOOK_DIR/agent-done-notify.sh"
echo "OK: skrypt hooka -> $HOOK_DIR/agent-done-notify.sh"

# Konfiguracja tematu (poza repo, uprawnienia 600). Nie nadpisujemy istniejącej bez podania tematu.
if [ -n "$TOPIC" ]; then
  umask 077
  { echo "NTFY_SERVER=https://ntfy.sh"; echo "NTFY_TOPIC=$TOPIC"; echo "NOTIFY_SOUND=1"; } > "$CONF"
  chmod 600 "$CONF"
  echo "OK: zapisano konfigurację ntfy -> $CONF (temat ustawiony)"
elif [ -f "$CONF" ]; then
  echo "OK: konfiguracja ntfy już istnieje -> $CONF (bez zmian)"
else
  echo "UWAGA: brak tematu ntfy. Dźwięk będzie działał, push na telefon nie." >&2
  echo "       Uruchom ponownie: $0 NAZWA_TEMATU" >&2
fi

# Scal wpis "stop" do ~/.cursor/hooks.json (bez ruszania innych hooków).
python3 - "$HOOKS_JSON" <<'PY'
import json, os, sys
path = sys.argv[1]
cmd = "./hooks/agent-done-notify.sh"
data = {}
if os.path.exists(path):
    with open(path) as f:
        data = json.load(f)
data.setdefault("version", 1)
stops = data.setdefault("hooks", {}).setdefault("stop", [])
stops[:] = [h for h in stops if "agent-done-sound.sh" not in h.get("command", "")]  # zastąp starą wersję
if not any(h.get("command") == cmd for h in stops):
    stops.append({"command": cmd, "timeout": 10})
with open(path, "w") as f:
    json.dump(data, f, indent=2, ensure_ascii=False)
    f.write("\n")
print("OK: hook 'stop' wpisany do", path)
PY
rm -f "$HOOK_DIR/agent-done-sound.sh"   # poprzednia, tylko dźwiękowa wersja

if (( DO_TEST )); then
  echo "Test: wysyłam powiadomienie próbne (dźwięk + ntfy)…"
  echo '{"status":"completed"}' | "$HOOK_DIR/agent-done-notify.sh" >/dev/null
  echo "Gotowe. Sprawdź dźwięk i telefon."
fi
echo "Zrestartuj okno Cursora, jeśli hooki nie zaczną działać od razu."

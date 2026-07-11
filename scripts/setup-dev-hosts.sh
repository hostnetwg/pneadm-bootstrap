#!/usr/bin/env bash
# Naprawa dev: hosts + usunięcie starego certyfikatu Caddy (powoduje wiszące HTTPS w Chrome).
set -euo pipefail

HOSTS_LINE="127.0.0.1 adm.localhost edu.localhost"
WIN_HOSTS="/mnt/c/Windows/System32/drivers/etc/hosts"

echo "==> Wpisy hosts..."
add_hosts_line() {
    local file="$1"
    if grep -q "adm.localhost" "$file" 2>/dev/null; then
        echo "    OK: adm.localhost w $file"
    else
        printf '\n# PNE dev\n%s\n' "$HOSTS_LINE" >> "$file"
        echo "    Dodano do $file"
    fi
}

[[ -f "$WIN_HOSTS" ]] && add_hosts_line "$WIN_HOSTS"

echo "==> Usuwam certyfikat Caddy (jeśli był instalowany)..."
if command -v powershell.exe >/dev/null 2>&1; then
    powershell.exe -Command "
        Get-ChildItem Cert:\CurrentUser\Root | Where-Object { \$_.Subject -like '*Caddy Local Authority*' } | Remove-Item
        Get-ChildItem Cert:\LocalMachine\Root -ErrorAction SilentlyContinue | Where-Object { \$_.Subject -like '*Caddy Local Authority*' } | Remove-Item
        Write-Host '    Certyfikat Caddy usunięty (jeśli istniał)'
    " 2>/dev/null || true
    powershell.exe -Command "ipconfig /flushdns" >/dev/null 2>&1 || true
fi

echo "==> Czyszczę public/hot (stary HTTPS Vite)..."
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
rm -f "$ROOT/public/hot"

echo ""
echo "Gotowe. Zamknij kartę Chrome i otwórz:"
echo "  http://adm.localhost:8083/"
echo "(musi być http://, nie https://)"

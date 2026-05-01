#!/usr/bin/env bash
#
# Builduje assety przez Vite (sail npm run build) i wgrywa public/build/
# na serwer produkcyjny rsync-iem. Konieczne, bo seohost.pl nie ma
# zainstalowanego Node/npm, a `public/build` jest w .gitignore.
#
# Konfiguracja:
#   1) Skopiuj scripts/deploy-assets.env.example -> scripts/deploy-assets.env
#      i wpisz docelowy host + ścieżkę.
#   2) Upewnij się, że masz dostęp SSH bez hasła (klucz wgrany na serwer).
#
# Użycie:
#   bash scripts/deploy-assets.sh           # build + upload
#   bash scripts/deploy-assets.sh --dry-run # tylko symulacja rsynca
#   bash scripts/deploy-assets.sh --skip-build  # pominięcie sail npm run build

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$SCRIPT_DIR/deploy-assets.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "[deploy-assets] Brak pliku konfiguracyjnego: $ENV_FILE"
    echo "[deploy-assets] Skopiuj $SCRIPT_DIR/deploy-assets.env.example i uzupełnij."
    exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

: "${REMOTE_SSH:?REMOTE_SSH (np. user@host) musi być ustawione w deploy-assets.env}"
: "${REMOTE_PATH:?REMOTE_PATH (pełna ścieżka do public/build na prodzie) musi być ustawione}"

DRY_RUN=""
SKIP_BUILD=0
for arg in "$@"; do
    case "$arg" in
        --dry-run)   DRY_RUN="--dry-run" ;;
        --skip-build) SKIP_BUILD=1 ;;
        -h|--help)
            sed -n '1,30p' "$0"; exit 0 ;;
        *) echo "[deploy-assets] Nieznany argument: $arg"; exit 2 ;;
    esac
done

cd "$PROJECT_DIR"

if [[ "$SKIP_BUILD" -eq 0 ]]; then
    echo "[deploy-assets] Buduję assety (sail npm run build)..."
    sail npm run build
else
    echo "[deploy-assets] Pomijam build (--skip-build)."
fi

if [[ ! -f public/build/manifest.json ]]; then
    echo "[deploy-assets] Brak public/build/manifest.json — build się nie powiódł?"
    exit 3
fi

echo "[deploy-assets] Wgrywam public/build/ → ${REMOTE_SSH}:${REMOTE_PATH}"
rsync -avz --delete $DRY_RUN \
    public/build/ \
    "${REMOTE_SSH}:${REMOTE_PATH%/}/"

if [[ -n "$DRY_RUN" ]]; then
    echo "[deploy-assets] (dry-run) nic nie zostało zmienione na serwerze."
else
    echo "[deploy-assets] Gotowe. Odśwież stronę w przeglądarce (Ctrl+Shift+R)."
fi

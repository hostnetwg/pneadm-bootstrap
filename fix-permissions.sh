#!/bin/bash
# Skrypt do naprawy uprawnień plików w projekcie Laravel
# Użycie: ./fix-permissions.sh [lub sudo ./fix-permissions.sh]

echo "=========================================="
echo "Naprawianie uprawnień plików w projekcie"
echo "=========================================="
echo ""

# Ustaw właściciela na aktualnego użytkownika
OWNER=$(whoami)
echo "Właściciel: $OWNER"
echo ""

# Katalogi które muszą być zapisywalne
DIRS=(
    "storage"
    "storage/app"
    "storage/framework"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
    "database/migrations"
)

# Ustaw uprawnienia dla katalogów
for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Naprawianie uprawnień dla: $dir"
        chmod -R 775 "$dir" 2>/dev/null || sudo chmod -R 775 "$dir"
        chown -R "$OWNER:$OWNER" "$dir" 2>/dev/null || sudo chown -R "$OWNER:$OWNER" "$dir"
    fi
done

# Napraw uprawnienia dla plików migracji
if [ -d "database/migrations" ]; then
    echo "Naprawianie uprawnień dla plików migracji..."
    # Próbuj bez sudo najpierw
    if find database/migrations -type f -name "*.php" -exec chmod 664 {} \; 2>/dev/null; then
        find database/migrations -type f -name "*.php" -exec chown "$OWNER:$OWNER" {} \; 2>/dev/null
        echo "✓ Uprawnienia naprawione bez sudo"
    else
        echo "Wymagane uprawnienia sudo..."
        sudo find database/migrations -type f -name "*.php" -exec chmod 664 {} \;
        sudo find database/migrations -type f -name "*.php" -exec chown "$OWNER:$OWNER" {} \;
        echo "✓ Uprawnienia naprawione z sudo"
    fi
fi

echo ""
echo "=========================================="
echo "Uprawnienia naprawione!"
echo "=========================================="
echo ""
echo "Jeśli nadal masz problemy:"
echo "  1. Uruchom: sudo ./fix-permissions.sh"
echo "  2. Sprawdź dokumentację: cat FIX_PERMISSIONS.md"


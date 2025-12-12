#!/bin/bash
# Szybka naprawa uprawnień dla aktualnego pliku migracji
# Użycie: sudo ./quick-fix-permissions.sh

FILE="database/migrations/2025_12_12_221458_add_performance_indexes_to_participants_and_courses_tables.php"
OWNER=$(whoami)

echo "Naprawianie uprawnień dla: $FILE"
echo "Właściciel: $OWNER"
echo ""

if [ -f "$FILE" ]; then
    sudo chown "$OWNER:$OWNER" "$FILE"
    sudo chmod 664 "$FILE"
    echo "✓ Uprawnienia naprawione!"
    echo ""
    ls -la "$FILE"
else
    echo "✗ Plik nie istnieje: $FILE"
    exit 1
fi


#!/bin/bash

# Skrypt do eksportu tabeli form_orders z wymuszeniem UTC
# Rozwiązuje problem przesunięcia dat o 2 godziny podczas importu na produkcji

echo "🚀 Export tabeli form_orders z UTC timezone..."

# Export przez sail mysqldump z dodatkowym SET TIME_ZONE
./vendor/bin/sail exec mysql mysqldump \
    --user=sail \
    --password=password \
    --no-create-info \
    --skip-tz-utc \
    --compact \
    --complete-insert \
    pneadm form_orders > form_orders_export.sql

# Dodaj SET TIME_ZONE na początku pliku
echo "SET time_zone = '+00:00';" > form_orders_export_with_tz.sql
cat form_orders_export.sql >> form_orders_export_with_tz.sql

echo "✅ Export zakończony!"
echo "📄 Plik: form_orders_export_with_tz.sql"
echo ""
echo "📋 Import na produkcji:"
echo "   1. Otwórz phpMyAdmin"
echo "   2. Wybierz bazę danych"
echo "   3. Przejdź do zakładki SQL"
echo "   4. Wklej zawartość pliku form_orders_export_with_tz.sql"
echo "   5. Kliknij 'Wykonaj'"
echo ""
echo "⚠️  WAŻNE: Przed importem wykonaj na produkcji:"
echo "   TRUNCATE TABLE form_orders;"

# Cleanup
rm form_orders_export.sql






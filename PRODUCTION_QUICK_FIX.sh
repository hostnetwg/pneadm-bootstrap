#!/bin/bash
# Quick fix dla problemu z composer.lock na produkcji
# Użycie: skopiuj komendy i wykonaj na serwerze produkcyjnym

echo "🔧 Naprawa konfiguracji pakietu pne/certificate-generator na produkcji"
echo ""

# 1. Przejdź do katalogu projektu
cd ~/domains/adm.pnedu.pl/public_html/pneadm-bootstrap || exit 1

# 2. Zrób backup composer.json
cp composer.json composer.json.backup

# 3. Edytuj composer.json - zmień path na vcs
echo "📝 Edytowanie composer.json..."
sed -i 's|"type": "path"|"type": "vcs"|' composer.json
sed -i 's|"/var/www/pne-certificate-generator"|"git@github.com:hostnetwg/pne-certificate-generator.git"|' composer.json

echo "✅ composer.json zaktualizowany"
echo ""

# 4. Sprawdź zmiany
echo "📋 Sprawdź zmiany w composer.json:"
grep -A 3 "repositories" composer.json | head -5
echo ""

# 5. Zaktualizuj pakiet
echo "📦 Aktualizowanie pakietu..."
composer update pne/certificate-generator --no-dev --optimize-autoloader

# 6. Wyczyść cache Laravel
echo ""
echo "🧹 Czyszczenie cache Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "✅ Gotowe! Pakiet powinien być teraz zainstalowany z GitHub."
echo ""
echo "Sprawdź instalację:"
echo "  ls -la vendor/pne/certificate-generator/"











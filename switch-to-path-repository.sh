#!/bin/bash

# 🔄 Skrypt do zmiany pakietu z GitHub (VCS) na Path Repository na produkcji
# Użycie: ./switch-to-path-repository.sh

echo "🔄 Zmiana pakietu pne-certificate-generator na Path Repository"
echo "================================================================"
echo ""

# Sprawdź czy jesteśmy w katalogu projektu
if [ ! -f "composer.json" ]; then
    echo "❌ BŁĄD: Nie jesteś w katalogu projektu Laravel!"
    echo "   Uruchom skrypt z katalogu pneadm-bootstrap lub pnedu"
    exit 1
fi

# Ścieżka do pakietu na produkcji
PACKAGE_PATH="/var/www/shared-packages/pne-certificate-generator"

echo "📋 Sprawdzanie konfiguracji..."
echo ""

# Sprawdź czy pakiet istnieje na serwerze
if [ ! -d "$PACKAGE_PATH" ]; then
    echo "⚠️  UWAGA: Katalog $PACKAGE_PATH nie istnieje!"
    echo ""
    echo "Najpierw musisz:"
    echo "1. Utworzyć katalog: mkdir -p /var/www/shared-packages"
    echo "2. Skopiować pakiet (z GitHub lub lokalnie)"
    echo ""
    echo "Czy chcesz kontynuować mimo to? (y/n)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        echo "Anulowano."
        exit 1
    fi
fi

# Sprawdź obecną konfigurację
CURRENT_REPO=$(grep -A 2 '"repositories"' composer.json | grep '"type"' | head -1 | sed 's/.*"type": *"\([^"]*\)".*/\1/')

if [ "$CURRENT_REPO" = "path" ]; then
    CURRENT_URL=$(grep -A 2 '"repositories"' composer.json | grep '"url"' | head -1 | sed 's/.*"url": *"\([^"]*\)".*/\1/')
    if [ "$CURRENT_URL" = "$PACKAGE_PATH" ]; then
        echo "✅ Pakiet jest już skonfigurowany jako Path Repository z właściwą ścieżką!"
        echo "   Ścieżka: $PACKAGE_PATH"
        exit 0
    fi
fi

echo "📝 Obecna konfiguracja:"
echo "   Typ: $CURRENT_REPO"
if [ "$CURRENT_REPO" = "path" ]; then
    echo "   URL: $CURRENT_URL"
fi
echo ""

echo "🔄 Zmieniamy na Path Repository..."
echo "   Nowa ścieżka: $PACKAGE_PATH"
echo ""

# Utwórz kopię zapasową
cp composer.json composer.json.backup
echo "✅ Utworzono kopię zapasową: composer.json.backup"
echo ""

# Zmień konfigurację w composer.json
if [ "$CURRENT_REPO" = "vcs" ]; then
    # Zmień z VCS na path
    sed -i 's|"type": "vcs"|"type": "path"|' composer.json
    sed -i "s|\"url\": \"git@github.com:hostnetwg/pne-certificate-generator.git\"|\"url\": \"$PACKAGE_PATH\"|" composer.json
elif [ "$CURRENT_REPO" = "path" ]; then
    # Zmień tylko URL
    sed -i "s|\"url\": \".*pne-certificate-generator.*\"|\"url\": \"$PACKAGE_PATH\"|" composer.json
else
    echo "❌ Nieznany typ repozytorium: $CURRENT_REPO"
    echo "   Ręcznie edytuj composer.json"
    exit 1
fi

echo "✅ Zaktualizowano composer.json"
echo ""

# Zaktualizuj pakiet
echo "📦 Aktualizuję pakiet..."
composer update pne/certificate-generator --no-interaction

if [ $? -eq 0 ]; then
    echo "✅ Pakiet zaktualizowany pomyślnie!"
else
    echo "❌ Błąd podczas aktualizacji pakietu"
    echo "   Przywracam kopię zapasową..."
    mv composer.json.backup composer.json
    exit 1
fi

echo ""
echo "🧹 Czyszczę cache Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear

echo ""
echo "✅ Gotowe!"
echo ""
echo "📋 Następne kroki:"
echo "1. Sprawdź czy pakiet jest zainstalowany:"
echo "   ls -la vendor/pne/certificate-generator/"
echo ""
echo "2. Sprawdź uprawnienia pakietu:"
echo "   ls -la $PACKAGE_PATH/storage/"
echo ""
echo "3. Przetestuj zapisywanie grafiki w edytorze szablonów"
echo ""
echo "4. Jeśli wszystko działa, usuń kopię zapasową:"
echo "   rm composer.json.backup"












#!/bin/bash

# Skrypt do szybkiego importu kopii produkcyjnej bazy danych do lokalnej bazy developerskiej
# 10-50x szybszy niż import przez phpMyAdmin!
#
# Użycie:
#   ./import-db.sh <plik.sql> <baza> [--fresh]
#   ./import-db.sh ~/Downloads/pneadm_prod.sql pneadm
#   ./import-db.sh ~/Downloads/certgen.sql certgen --fresh   # usuwa bazę przed importem
#
# Opcja --fresh: usuwa bazę i tworzy od nowa przed importem (rozwiązuje "Table already exists")

set -e

# Kolory dla outputu
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parsowanie argumentów
FRESH_MODE=false
ARGS=()
for arg in "$@"; do
    if [ "$arg" = "--fresh" ]; then
        FRESH_MODE=true
    else
        ARGS+=("$arg")
    fi
done

# Sprawdzenie argumentów
if [ ${#ARGS[@]} -lt 2 ]; then
    echo -e "${YELLOW}Użycie:${NC} $0 <plik.sql> <baza> [--fresh]"
    echo ""
    echo "Dostępne bazy: pneadm, pnedu, certgen"
    echo ""
    echo "Opcje:"
    echo "  --fresh   Usuwa bazę przed importem (gdy plik nie ma DROP TABLE)"
    echo ""
    echo "Przykłady:"
    echo "  $0 ~/Downloads/pneadm_prod.sql pneadm"
    echo "  $0 ~/Downloads/certgen.sql certgen --fresh"
    echo ""
    exit 1
fi

SQL_FILE="${ARGS[0]}"
DB_NAME="${ARGS[1]}"

# Walidacja pliku
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}Błąd: Plik nie istnieje: $SQL_FILE${NC}"
    exit 1
fi

# Walidacja nazwy bazy
case "$DB_NAME" in
    pneadm|pnedu|certgen) ;;
    *)
        echo -e "${RED}Błąd: Nieznana baza '$DB_NAME'. Użyj: pneadm, pnedu lub certgen${NC}"
        exit 1
        ;;
esac

# Pobierz hasło z .env (domyślnie: password)
DB_PASSWORD="password"
if [ -f .env ]; then
    ENV_PASS=$(grep -E "^DB_PASSWORD=" .env 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
    [ -n "$ENV_PASS" ] && DB_PASSWORD="$ENV_PASS"
fi

echo ""
echo -e "${GREEN}🚀 Szybki import bazy danych${NC}"
echo "   Plik: $SQL_FILE"
echo "   Baza: $DB_NAME"
echo "   Rozmiar: $(du -h "$SQL_FILE" | cut -f1)"
echo ""

# Sprawdź czy Sail jest dostępny
SAIL="./vendor/bin/sail"
if [ ! -x "$SAIL" ]; then
    echo -e "${RED}Błąd: Nie znaleziono Sail ($SAIL). Uruchom z katalogu pneadm-bootstrap.${NC}"
    exit 1
fi

# Sprawdź czy kontenery działają
if ! $SAIL ps 2>/dev/null | grep -q "Up"; then
    echo -e "${YELLOW}Uwaga: Kontenery mogą nie być uruchomione. Uruchamiam...${NC}"
    $SAIL up -d
    echo "Czekam na gotowość MySQL (30s)..."
    sleep 30
fi

# Tryb --fresh: usuń bazę i utwórz od nowa
if [ "$FRESH_MODE" = true ]; then
    echo -e "${YELLOW}Usuwam bazę $DB_NAME i tworzę od nowa (--fresh)...${NC}"
    $SAIL mysql -u root -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;"
    echo ""
fi

echo -e "${YELLOW}Importuję z optymalizacją (FOREIGN_KEY_CHECKS=0, UNIQUE_CHECKS=0)...${NC}"
echo ""

START_TIME=$(date +%s)

# Import z optymalizacją - pipe przez mysql z wyłączonymi sprawdzeniami
# Używamy bash -c aby wykonać SET przed SOURCE, ale SOURCE nie działa z stdin
# Dlatego używamy pipe: cat file | mysql z SETami w osobnym połączeniu

# Metoda: wykonaj SET w tym samym połączeniu co import
# MySQL nie obsługuje SET + SOURCE w jednym połączeniu z pipe, więc:
# 1. Tworzymy tymczasowy plik z prefixem SET
# 2. Albo używamy --init-command (MySQL 8+)

# Pipe z prefixem optymalizacyjnym (przyspiesza import 10x)
{
    echo "SET FOREIGN_KEY_CHECKS=0;"
    echo "SET UNIQUE_CHECKS=0;"
    cat "$SQL_FILE"
    echo "SET FOREIGN_KEY_CHECKS=1;"
    echo "SET UNIQUE_CHECKS=1;"
} | $SAIL mysql -u root -p"$DB_PASSWORD" "$DB_NAME"

IMPORT_EXIT=$?

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo ""
if [ $IMPORT_EXIT -eq 0 ]; then
    echo -e "${GREEN}✅ Import zakończony pomyślnie!${NC}"
    echo "   Czas: ${DURATION}s"
    echo ""
    echo "Sprawdź dane:"
    echo "  $SAIL mysql $DB_NAME -e \"SHOW TABLES;\""
else
    echo -e "${RED}❌ Import zakończył się błędem (kod: $IMPORT_EXIT)${NC}"
    echo ""
    echo "Sprawdź logi: $SAIL logs mysql"
    exit $IMPORT_EXIT
fi

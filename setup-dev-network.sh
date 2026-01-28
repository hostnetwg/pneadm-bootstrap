#!/bin/bash

# Skrypt do konfiguracji wspólnej sieci Docker dla środowiska developerskiego

set -e

echo "🔧 Konfiguracja wspólnej sieci Docker dla projektów PNE"
echo ""

# Sprawdź czy sieć już istnieje
if docker network ls | grep -q "pne-network"; then
    echo "✅ Sieć 'pne-network' już istnieje"
else
    echo "📦 Tworzenie sieci 'pne-network'..."
    docker network create pne-network
    echo "✅ Sieć 'pne-network' utworzona"
fi

echo ""
echo "📋 Sprawdzenie konfiguracji:"
echo ""

# Sprawdź czy kontenery są uruchomione
echo "Kontenery pneadm-bootstrap:"
cd /home/hostnet/WEB-APP/pneadm-bootstrap
if docker ps | grep -q "pneadm-bootstrap"; then
    echo "  ✅ pneadm-bootstrap jest uruchomiony"
else
    echo "  ⚠️  pneadm-bootstrap nie jest uruchomiony"
    echo "     Uruchom: cd pneadm-bootstrap && sail up -d"
fi

echo ""
echo "Kontenery pnedu:"
cd /home/hostnet/WEB-APP/pnedu
if docker ps | grep -q "pnedu"; then
    echo "  ✅ pnedu jest uruchomiony"
else
    echo "  ⚠️  pnedu nie jest uruchomiony"
    echo "     Uruchom: cd pnedu && sail up -d"
fi

echo ""
echo "🌐 Sprawdzenie sieci:"
docker network inspect pne-network --format '{{range .Containers}}{{.Name}} {{end}}' 2>/dev/null || echo "  Sieć jest pusta (to OK, jeśli kontenery nie są uruchomione)"

echo ""
echo "✅ Konfiguracja zakończona!"
echo ""
echo "📝 Następne kroki:"
echo "  1. Upewnij się, że .env w obu projektach jest skonfigurowany"
echo "  2. Uruchom kontenery:"
echo "     cd pneadm-bootstrap && sail up -d"
echo "     cd pnedu && sail up -d"
echo "  3. Sprawdź połączenie:"
echo "     cd pnedu && sail artisan tinker"
echo "     DB::connection('pneadm')->select('SELECT 1');"








# 📝 Przykładowa konfiguracja .env dla środowiska developerskiego

## pneadm-bootstrap/.env

Dodaj/zmodyfikuj następujące zmienne:

```env
# Baza danych (główna - hostuje MySQL)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pneadm
DB_USERNAME=sail
DB_PASSWORD=password

# API Token (ten sam w obu projektach)
PNEADM_API_TOKEN=dev-api-token-12345

# URL aplikacji
APP_URL=http://localhost:8083
```

---

## pnedu/.env

Dodaj/zmodyfikuj następujące zmienne:

```env
# Baza danych główna (pnedu)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pnedu
DB_USERNAME=sail
DB_PASSWORD=password

# Połączenie do wspólnej bazy pneadm (MySQL z pneadm-bootstrap)
# WAŻNE: W kontenerze Docker użyj 'mysql' (nazwa serwisu), nie '127.0.0.1'!
DB_PNEADM_HOST=mysql
DB_PNEADM_PORT=3306
DB_PNEADM_DATABASE=pneadm
DB_PNEADM_USERNAME=sail
DB_PNEADM_PASSWORD=password

# Konfiguracja API dla komunikacji z adm.pnedu.pl
# W kontenerze Docker użyj host.docker.internal (Windows/Mac)
# W produkcji użyj https://adm.pnedu.pl
PNEADM_API_URL=http://host.docker.internal:8083
PNEADM_API_TOKEN=dev-api-token-12345

# URL aplikacji
APP_URL=http://localhost:8081
```

---

## 🔑 Generowanie bezpiecznego tokena API

Dla środowiska developerskiego możesz użyć prostego tokena, ale dla produkcji wygeneruj bezpieczny:

```bash
# Generuj losowy token (32 znaki)
openssl rand -hex 32

# Lub użyj Laravel:
php artisan tinker
Str::random(32);
```

---

## ✅ Sprawdzenie konfiguracji

Po ustawieniu `.env` w obu projektach, sprawdź:

```bash
# W pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail artisan config:clear
sail artisan tinker
config('services.pneadm.api_token');

# W pnedu
cd /home/hostnet/WEB-APP/pnedu
sail artisan config:clear
sail artisan tinker
config('services.pneadm.api_url');
config('database.connections.pneadm.host');
```


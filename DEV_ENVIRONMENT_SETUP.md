# 🔧 Konfiguracja środowiska developerskiego dla Wariantu 3

## 📋 Obecna sytuacja

### Projekty:
- **pneadm-bootstrap** (adm.pnedu.pl): `http://localhost:8083`
- **pnedu** (pnedu.pl): `http://localhost:8081`

### Bazy danych (obecnie):
- **pneadm-bootstrap**: Własny kontener MySQL (port 3307), baza `pneadm`
- **pnedu**: Własny kontener MySQL (port 3306), baza `pnedu` + połączenie do `pneadm` (ale osobny kontener!)

### Problem:
- Oba projekty mają **osobne kontenery MySQL** z **osobnymi volumes**
- Zmiany w bazie `pneadm` w jednym projekcie **nie są widoczne** w drugim
- Trzeba synchronizować dane ręcznie

---

## ✅ Rozwiązania

### Wariant A: Wspólna sieć Docker + jeden kontener MySQL (ZALECANY)

**Koncepcja:** Jeden kontener MySQL używany przez oba projekty.

#### Zalety:
- ✅ Jedna baza danych - zmiany widoczne od razu
- ✅ Prostsze zarządzanie (jeden kontener)
- ✅ Mniej zasobów (jeden MySQL zamiast dwóch)
- ✅ Łatwiejsze backup'y

#### Wady:
- ⚠️ Wymaga modyfikacji `docker-compose.yml` w obu projektach
- ⚠️ Trzeba wybrać, który projekt "hostuje" MySQL

---

### Wariant B: Wspólna sieć Docker + pnedu łączy się do MySQL z pneadm-bootstrap

**Koncepcja:** MySQL w `pneadm-bootstrap`, `pnedu` łączy się do niego przez sieć Docker.

#### Zalety:
- ✅ Jedna baza danych
- ✅ Mniejsza zmiana (tylko w `pnedu`)
- ✅ Logiczne (adm.pnedu.pl "właścicielem" bazy)

#### Wady:
- ⚠️ `pnedu` zależy od `pneadm-bootstrap` (trzeba uruchomić najpierw adm)

---

### Wariant C: External network + shared MySQL container

**Koncepcja:** Osobny kontener MySQL w osobnym `docker-compose.yml`.

#### Zalety:
- ✅ Najbardziej elastyczne
- ✅ Można uruchomić MySQL niezależnie

#### Wady:
- ⚠️ Najbardziej skomplikowane
- ⚠️ Wymaga dodatkowego pliku docker-compose

---

## 🎯 Implementacja: Wariant A (Zalecany)

### Krok 1: Utwórz wspólną sieć Docker

```bash
# Utwórz external network (tylko raz)
docker network create pne-network
```

### Krok 2: Zmodyfikuj docker-compose.yml w pneadm-bootstrap

```yaml
# pneadm-bootstrap/docker-compose.yml
services:
  laravel.test:
    # ... istniejąca konfiguracja ...
    networks:
      - sail
      - pne-network  # Dodaj wspólną sieć

  mysql:
    image: 'mysql/mysql-server:8.0'
    ports:
      - '3307:3306'  # Port na hoście (można zmienić)
    command: --default-time-zone=+00:00
    environment:
      MYSQL_ROOT_PASSWORD: '${DB_PASSWORD}'
      MYSQL_ROOT_HOST: '%'
      MYSQL_DATABASE: '${DB_DATABASE}'
      MYSQL_USER: '${DB_USERNAME}'
      MYSQL_PASSWORD: '${DB_PASSWORD}'
      TZ: 'UTC'
    volumes:
      - 'pne-mysql-shared:/var/lib/mysql'  # Zmień nazwę volume
      - './vendor/laravel/sail/database/mysql/create-testing-database.sh:/docker-entrypoint-initdb.d/10-create-testing-database.sh'
    networks:
      - sail
      - pne-network  # Dodaj wspólną sieć
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-p${DB_PASSWORD}"]
      retries: 3
      timeout: 5s

networks:
  sail:
    driver: bridge
  pne-network:  # Dodaj external network
    external: true

volumes:
  pne-mysql-shared:  # Zmień nazwę volume
    driver: local
  sail-redis:
    driver: local
```

### Krok 3: Zmodyfikuj docker-compose.yml w pnedu

```yaml
# pnedu/docker-compose.yml
services:
  laravel.test:
    # ... istniejąca konfiguracja ...
    networks:
      - sail
      - pne-network  # Dodaj wspólną sieć
    depends_on:
      - mysql  # Usuń - nie potrzebujemy własnego MySQL
      # ... inne zależności ...

  # USUŃ cały blok mysql - nie potrzebujemy własnego kontenera MySQL
  # mysql:
  #   ...

networks:
  sail:
    driver: bridge
  pne-network:  # Dodaj external network
    external: true

volumes:
  # Usuń sail-mysql - używamy wspólnego volume
  sail-redis:
    driver: local
```

### Krok 4: Zaktualizuj konfigurację bazy w pnedu

```php
// pnedu/config/database.php
'pneadm' => [
    'driver' => 'mysql',
    'host' => env('DB_PNEADM_HOST', 'mysql'),  // Nazwa serwisu MySQL z pneadm-bootstrap
    'port' => env('DB_PNEADM_PORT', '3306'),
    'database' => env('DB_PNEADM_DATABASE', 'pneadm'),
    'username' => env('DB_PNEADM_USERNAME', 'sail'),
    'password' => env('DB_PNEADM_PASSWORD', 'password'),
    // ... reszta konfiguracji ...
],
```

**WAŻNE:** W Dockerze `host` powinien być `mysql` (nazwa serwisu z `pneadm-bootstrap`), nie `127.0.0.1`!

### Krok 5: Zaktualizuj .env w pnedu

```env
# pnedu/.env
# Połączenie do wspólnej bazy pneadm
DB_PNEADM_HOST=mysql  # Nazwa serwisu MySQL z pneadm-bootstrap (w Dockerze)
DB_PNEADM_PORT=3306
DB_PNEADM_DATABASE=pneadm
DB_PNEADM_USERNAME=sail
DB_PNEADM_PASSWORD=password
```

**UWAGA:** Jeśli łączysz się z hosta (np. phpMyAdmin), użyj `127.0.0.1:3307`. W kontenerze Docker użyj `mysql:3306`.

---

## 🔄 Alternatywa: Wariant B (Prostszy)

Jeśli nie chcesz modyfikować `docker-compose.yml` w `pnedu`, możesz:

### Krok 1: Dodaj external network tylko w pneadm-bootstrap

```yaml
# pneadm-bootstrap/docker-compose.yml
networks:
  sail:
    driver: bridge
  pne-network:
    external: true  # External network
```

### Krok 2: W pnedu, połącz się do MySQL z pneadm-bootstrap

```env
# pnedu/.env
# Połączenie do MySQL z pneadm-bootstrap przez Docker network
DB_PNEADM_HOST=mysql  # Nazwa serwisu MySQL z pneadm-bootstrap
DB_PNEADM_PORT=3306
DB_PNEADM_DATABASE=pneadm
DB_PNEADM_USERNAME=sail
DB_PNEADM_PASSWORD=password
```

**WAŻNE:** `pnedu` musi być w tej samej sieci Docker co `pneadm-bootstrap`!

### Krok 3: Dodaj pnedu do sieci pneadm-bootstrap

```yaml
# pnedu/docker-compose.yml
services:
  laravel.test:
    networks:
      - sail
      - pne-network  # Dodaj do sieci pneadm-bootstrap

networks:
  sail:
    driver: bridge
  pne-network:
    external: true
    name: pneadm-bootstrap_sail  # Nazwa sieci z pneadm-bootstrap
```

---

## 🌐 Konfiguracja API w środowisku dev

### Problem:
- `pnedu` (localhost:8081) musi wywołać API w `adm.pnedu.pl` (localhost:8083)
- W przeglądarce: `localhost:8081` → `localhost:8083` ✅ (działa)
- W kontenerze Docker: `laravel.test` → `localhost:8083` ❌ (nie działa - localhost to kontener, nie host)

### Rozwiązanie:

#### Opcja 1: Użyj `host.docker.internal` (Windows/Mac)

```php
// pnedu/config/services.php
'pneadm' => [
    'api_url' => env('PNEADM_API_URL', 'http://host.docker.internal:8083'),
    'api_token' => env('PNEADM_API_TOKEN'),
],
```

```env
# pnedu/.env
PNEADM_API_URL=http://host.docker.internal:8083
```

#### Opcja 2: Użyj nazwy serwisu Docker (Lepiej!)

```yaml
# pneadm-bootstrap/docker-compose.yml
services:
  laravel.test:
    container_name: pneadm-bootstrap-app  # Dodaj nazwę kontenera
    # ...
```

```php
// pnedu/config/services.php
'pneadm' => [
    'api_url' => env('PNEADM_API_URL', 'http://pneadm-bootstrap-app:80'),  // W Dockerze
    'api_token' => env('PNEADM_API_TOKEN'),
],
```

**Ale:** To nie zadziała z przeglądarki! Potrzebujemy warunkowej konfiguracji.

#### Opcja 3: Warunkowa konfiguracja (NAJLEPSZE)

```php
// pnedu/config/services.php
'pneadm' => [
    'api_url' => env('PNEADM_API_URL', function() {
        // Jeśli jesteśmy w kontenerze Docker
        if (env('LARAVEL_SAIL')) {
            // Użyj host.docker.internal (Windows/Mac) lub nazwy serwisu (Linux)
            return 'http://host.docker.internal:8083';
        }
        // Jeśli jesteśmy na hoście
        return 'http://localhost:8083';
    }),
    'api_token' => env('PNEADM_API_TOKEN'),
],
```

**Lub prostsze:**

```env
# pnedu/.env
# Dla wywołań z przeglądarki (frontend)
PNEADM_API_URL=http://localhost:8083

# Dla wywołań z kontenera (backend)
# W kodzie użyj warunkowo:
# if (env('LARAVEL_SAIL')) {
#     $url = 'http://host.docker.internal:8083';
# } else {
#     $url = env('PNEADM_API_URL');
# }
```

#### Opcja 4: Użyj zmiennej środowiskowej (NAJPROSTSZE)

```env
# pnedu/.env
# Dla środowiska dev (Docker)
PNEADM_API_URL=http://host.docker.internal:8083

# Dla produkcji
# PNEADM_API_URL=https://adm.pnedu.pl
```

```php
// pnedu/app/Services/CertificateApiClient.php
public function __construct()
{
    $this->apiUrl = env('PNEADM_API_URL', 'http://localhost:8083');
    $this->apiToken = env('PNEADM_API_TOKEN');
}
```

---

## 📝 Instrukcja wdrożenia (Wariant A)

### Krok 1: Utwórz wspólną sieć Docker

```bash
docker network create pne-network
```

### Krok 2: Zatrzymaj kontenery

```bash
# W pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail down

# W pnedu
cd /home/hostnet/WEB-APP/pnedu
sail down
```

### Krok 3: Zaktualizuj docker-compose.yml

Zastosuj zmiany z **Kroku 2 i 3** powyżej.

### Krok 4: Migruj dane (jeśli potrzebne)

Jeśli masz dane w bazie `pneadm` w kontenerze `pnedu`, musisz je przenieść:

```bash
# Backup z pnedu
cd /home/hostnet/WEB-APP/pnedu
sail mysql -e "mysqldump -u sail -ppassword pneadm > /tmp/pneadm_backup.sql"

# Restore do pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail mysql pneadm < /tmp/pneadm_backup.sql
```

### Krok 5: Uruchom kontenery

```bash
# Najpierw pneadm-bootstrap (hostuje MySQL)
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail up -d

# Potem pnedu
cd /home/hostnet/WEB-APP/pnedu
sail up -d
```

### Krok 6: Sprawdź połączenie

```bash
# W pnedu
cd /home/hostnet/WEB-APP/pnedu
sail artisan tinker

# W tinker:
DB::connection('pneadm')->select('SELECT 1');
DB::connection('pneadm')->table('certificate_templates')->count();
```

### Krok 7: Zaktualizuj konfigurację API

```env
# pnedu/.env
PNEADM_API_URL=http://host.docker.internal:8083
PNEADM_API_TOKEN=dev-api-token-12345
```

```env
# pneadm-bootstrap/.env
PNEADM_API_TOKEN=dev-api-token-12345
```

---

## ✅ Testowanie

### Test 1: Wspólna baza danych

```bash
# W pneadm-bootstrap - utwórz szablon
# Przejdź do: http://localhost:8083/admin/certificate-templates/create

# W pnedu - sprawdź czy widzi szablon
cd /home/hostnet/WEB-APP/pnedu
sail artisan tinker
DB::connection('pneadm')->table('certificate_templates')->latest()->first();
```

### Test 2: API Endpoint

```bash
# W pneadm-bootstrap
curl -X POST http://localhost:8083/api/certificates/generate \
  -H "Authorization: Bearer dev-api-token-12345" \
  -H "Content-Type: application/json" \
  -d '{"participant_id": 1}'
```

### Test 3: Generowanie certyfikatu z pnedu

1. Zaloguj się w `http://localhost:8081`
2. Przejdź do kursu
3. Kliknij "Pobierz zaświadczenie"
4. Sprawdź czy PDF się generuje

---

## 🔧 Troubleshooting

### Problem: "Connection refused" przy połączeniu do MySQL

**Rozwiązanie:**
- Sprawdź czy `pneadm-bootstrap` jest uruchomiony: `sail ps`
- Sprawdź czy oba projekty są w tej samej sieci: `docker network inspect pne-network`
- Sprawdź czy używasz poprawnej nazwy hosta: `mysql` (nie `127.0.0.1`) w kontenerze

### Problem: "API call failed" z pnedu do adm

**Rozwiązanie:**
- Sprawdź czy `PNEADM_API_URL` jest poprawny
- W Dockerze użyj `host.docker.internal:8083` (Windows/Mac) lub `172.17.0.1:8083` (Linux)
- Sprawdź czy API endpoint działa: `curl http://localhost:8083/api/certificates/generate`

### Problem: "Network not found"

**Rozwiązanie:**
```bash
docker network create pne-network
```

### Problem: Różne dane w bazach

**Rozwiązanie:**
- Upewnij się, że oba projekty używają tej samej bazy
- Sprawdź `.env` w obu projektach
- Sprawdź `config/database.php` w `pnedu` - połączenie `pneadm` powinno wskazywać na `mysql` (nazwa serwisu)

---

## 📊 Podsumowanie

### Po wdrożeniu:

✅ **Jedna baza danych** - zmiany widoczne od razu  
✅ **API działa** - `pnedu` → `adm.pnedu.pl`  
✅ **Łatwe testowanie** - wszystko lokalnie  
✅ **Prostsze zarządzanie** - jeden MySQL  

### Struktura:

```
┌─────────────────────┐
│  pneadm-bootstrap   │
│  (localhost:8083)   │
│  ┌───────────────┐  │
│  │ MySQL (mysql) │  │
│  │ Port: 3306    │  │
│  └───────┬───────┘  │
└──────────┼──────────┘
           │
           │ Docker Network (pne-network)
           │
┌──────────┼──────────┐
│  pnedu              │
│  (localhost:8081)   │
│  └──────────────────┘
│  Łączy się do:      │
│  mysql:3306         │
└─────────────────────┘
```

---

## 🎯 Rekomendacja

**Użyj Wariantu A** - jest najprostszy i najbardziej niezawodny dla środowiska developerskiego.

W produkcji:
- Oba projekty będą na tym samym serwerze lub w tej samej sieci
- API URL: `https://adm.pnedu.pl`
- Baza danych: wspólna (już jest)








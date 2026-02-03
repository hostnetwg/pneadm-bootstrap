# 🚀 Instrukcja konfiguracji środowiska developerskiego

## 📋 Krok po kroku

### Krok 1: Utwórz wspólną sieć Docker

```bash
docker network create pne-network
```

**Sprawdź czy sieć została utworzona:**
```bash
docker network ls | grep pne-network
```

---

### Krok 2: Zatrzymaj istniejące kontenery

```bash
# W pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail down

# W pnedu
cd /home/hostnet/WEB-APP/pnedu
sail down
```

---

### Krok 3: Backup danych (jeśli masz ważne dane)

Jeśli masz dane w bazie `pneadm` w kontenerze `pnedu`, zrób backup:

```bash
# Backup z pnedu (jeśli jeszcze działa)
cd /home/hostnet/WEB-APP/pnedu
sail up -d
sail mysql -e "mysqldump -u sail -ppassword pneadm" > /tmp/pneadm_backup.sql

# Zatrzymaj ponownie
sail down
```

---

### Krok 4: Zaktualizuj pliki docker-compose.yml

Pliki zostały już zaktualizowane:
- ✅ `pneadm-bootstrap/docker-compose.yml` - używa wspólnej sieci i volume
- ✅ `pnedu/docker-compose.yml` - łączy się do MySQL z pneadm-bootstrap

**Sprawdź czy pliki są poprawne:**
```bash
# W pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
cat docker-compose.yml | grep -A 2 "pne-network"

# W pnedu
cd /home/hostnet/WEB-APP/pnedu
cat docker-compose.yml | grep -A 2 "pne-network"
```

---

### Krok 5: Zaktualizuj konfigurację .env w pnedu

Dodaj/zmodyfikuj w `pnedu/.env`:

```env
# Połączenie do wspólnej bazy pneadm (MySQL z pneadm-bootstrap)
DB_PNEADM_HOST=mysql
DB_PNEADM_PORT=3306
DB_PNEADM_DATABASE=pneadm
DB_PNEADM_USERNAME=sail
DB_PNEADM_PASSWORD=password

# Konfiguracja API dla komunikacji z adm.pnedu.pl
PNEADM_API_URL=http://host.docker.internal:8083
PNEADM_API_TOKEN=dev-api-token-12345
```

**WAŻNE:** 
- `DB_PNEADM_HOST=mysql` - to nazwa serwisu MySQL z `pneadm-bootstrap`
- W kontenerze Docker użyj `mysql`, nie `127.0.0.1`!
- `PNEADM_API_URL` używa `host.docker.internal` dla komunikacji z kontenera do hosta

---

### Krok 6: Zaktualizuj konfigurację .env w pneadm-bootstrap

Dodaj w `pneadm-bootstrap/.env`:

```env
# Token API (ten sam co w pnedu)
PNEADM_API_TOKEN=dev-api-token-12345
```

---

### Krok 7: Uruchom kontenery

**WAŻNE:** Najpierw uruchom `pneadm-bootstrap` (hostuje MySQL), potem `pnedu`:

```bash
# 1. Uruchom pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail up -d

# Sprawdź czy MySQL działa
sail ps
sail mysql -e "SELECT 1"

# 2. Uruchom pnedu
cd /home/hostnet/WEB-APP/pnedu
sail up -d

# Sprawdź czy kontenery działają
sail ps
```

---

### Krok 8: Sprawdź połączenie do wspólnej bazy

```bash
# W pnedu - sprawdź połączenie do pneadm
cd /home/hostnet/WEB-APP/pnedu
sail artisan tinker
```

W tinker:
```php
// Test połączenia
DB::connection('pneadm')->select('SELECT 1');

// Sprawdź czy widzi tabele
DB::connection('pneadm')->select('SHOW TABLES');

// Sprawdź szablony certyfikatów
DB::connection('pneadm')->table('certificate_templates')->count();
```

---

### Krok 9: Restore danych (jeśli robiłeś backup)

```bash
# Jeśli masz backup z pnedu
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail mysql pneadm < /tmp/pneadm_backup.sql
```

---

### Krok 10: Sprawdź czy API działa

```bash
# Test API endpoint w pneadm-bootstrap
curl -X POST http://localhost:8083/api/certificates/generate \
  -H "Authorization: Bearer dev-api-token-12345" \
  -H "Content-Type: application/json" \
  -d '{"participant_id": 1}'
```

**Oczekiwany wynik:**
- Jeśli certyfikat istnieje: PDF binary
- Jeśli nie: JSON z błędem (to OK, sprawdzamy tylko czy endpoint działa)

---

## ✅ Testowanie

### Test 1: Wspólna baza danych

1. **Utwórz szablon w adm.pnedu.pl:**
   - Przejdź do: `http://localhost:8083/admin/certificate-templates/create`
   - Utwórz nowy szablon
   - Zapisz

2. **Sprawdź w pnedu czy widzi szablon:**
   ```bash
   cd /home/hostnet/WEB-APP/pnedu
   sail artisan tinker
   ```
   ```php
   DB::connection('pneadm')->table('certificate_templates')->latest()->first();
   ```

3. **Edycja szablonu:**
   - Edytuj szablon w `http://localhost:8083`
   - Sprawdź czy zmiany są widoczne w `pnedu` (bez restartu!)

### Test 2: Generowanie certyfikatu

1. **Zaloguj się w pnedu:**
   - `http://localhost:8081`
   - Zaloguj się jako użytkownik

2. **Wygeneruj certyfikat:**
   - Przejdź do kursu
   - Kliknij "Pobierz zaświadczenie"
   - Sprawdź czy PDF się generuje

3. **Sprawdź logi:**
   ```bash
   # W pneadm-bootstrap (API)
   cd /home/hostnet/WEB-APP/pneadm-bootstrap
   sail artisan pail
   
   # W pnedu (klient)
   cd /home/hostnet/WEB-APP/pnedu
   sail artisan pail
   ```

---

## 🔧 Troubleshooting

### Problem: "Network pne-network not found"

**Rozwiązanie:**
```bash
docker network create pne-network
```

### Problem: "Connection refused" przy połączeniu do MySQL

**Sprawdź:**
1. Czy `pneadm-bootstrap` jest uruchomiony:
   ```bash
   cd /home/hostnet/WEB-APP/pneadm-bootstrap
   sail ps
   ```

2. Czy oba projekty są w tej samej sieci:
   ```bash
   docker network inspect pne-network
   ```
   Powinny być widoczne oba kontenery: `pneadm-bootstrap-app` i `pnedu-app`

3. Czy używasz poprawnej nazwy hosta w `.env`:
   - W kontenerze: `DB_PNEADM_HOST=mysql` ✅
   - Nie: `DB_PNEADM_HOST=127.0.0.1` ❌

### Problem: "API call failed" z pnedu do adm

**Sprawdź:**
1. Czy `PNEADM_API_URL` jest poprawny:
   ```bash
   cd /home/hostnet/WEB-APP/pnedu
   sail artisan tinker
   ```
   ```php
   config('services.pneadm.api_url');
   ```

2. Czy API endpoint działa:
   ```bash
   curl http://localhost:8083/api/certificates/generate \
     -H "Authorization: Bearer dev-api-token-12345" \
     -H "Content-Type: application/json" \
     -d '{"participant_id": 1}'
   ```

3. W Dockerze użyj `host.docker.internal:8083` (Windows/Mac) lub sprawdź IP hosta (Linux)

### Problem: Różne dane w bazach

**Sprawdź:**
1. Czy oba projekty używają tej samej bazy:
   ```bash
   # W pneadm-bootstrap
   cd /home/hostnet/WEB-APP/pneadm-bootstrap
   sail mysql -e "SELECT DATABASE();"
   
   # W pnedu
   cd /home/hostnet/WEB-APP/pnedu
   sail artisan tinker
   ```
   ```php
   DB::connection('pneadm')->select('SELECT DATABASE()');
   ```

2. Sprawdź `.env` w obu projektach - powinny wskazywać na tę samą bazę

### Problem: phpMyAdmin w pnedu nie łączy się

**Rozwiązanie:**
W `pnedu/docker-compose.yml` phpMyAdmin ma:
```yaml
PMA_HOST: pneadm-mysql  # Nazwa kontenera MySQL z pneadm-bootstrap
```

Sprawdź czy kontener MySQL ma nazwę `pneadm-mysql`:
```bash
docker ps | grep mysql
```

---

## 📊 Struktura po konfiguracji

```
┌─────────────────────────────────────┐
│  pneadm-bootstrap (localhost:8083)  │
│  ┌───────────────────────────────┐  │
│  │ MySQL Container (pneadm-mysql)│  │
│  │ Port: 3306 (internal)         │  │
│  │ Port: 3307 (host)             │  │
│  │ Volume: pne-mysql-shared      │  │
│  └───────────────┬───────────────┘  │
│                  │                   │
└──────────────────┼───────────────────┘
                   │
                   │ Docker Network (pne-network)
                   │
┌──────────────────┼───────────────────┐
│  pnedu (localhost:8081)                │
│  └────────────────────────────────────┘
│  Łączy się do:                         │
│  - MySQL: mysql:3306 (w Dockerze)     │
│  - API: host.docker.internal:8083      │
└─────────────────────────────────────────┘
```

---

## 🎯 Checklist

- [ ] Sieć `pne-network` utworzona
- [ ] `docker-compose.yml` zaktualizowany w obu projektach
- [ ] `.env` zaktualizowany w `pnedu` (DB_PNEADM_* i PNEADM_API_*)
- [ ] `.env` zaktualizowany w `pneadm-bootstrap` (PNEADM_API_TOKEN)
- [ ] Kontenery uruchomione (najpierw pneadm-bootstrap, potem pnedu)
- [ ] Połączenie do bazy działa (test w tinker)
- [ ] API endpoint działa (test curl)
- [ ] Generowanie certyfikatu działa (test w przeglądarce)

---

## 📝 Notatki

- **MySQL host w Dockerze:** `mysql` (nazwa serwisu z pneadm-bootstrap)
- **MySQL host z hosta:** `127.0.0.1:3307` (port mapowany)
- **API URL w Dockerze:** `http://host.docker.internal:8083`
- **API URL z hosta:** `http://localhost:8083`
- **Wspólny volume:** `pne-mysql-shared` (przechowuje dane MySQL)

---

## 🚀 Gotowe!

Po wykonaniu wszystkich kroków:
- ✅ Wspólna baza danych działa
- ✅ Zmiany w adm.pnedu.pl są widoczne od razu w pnedu.pl
- ✅ API komunikacja działa
- ✅ Można testować generowanie certyfikatów









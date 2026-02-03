# 🗄️ Konfiguracja wspólnych baz danych w środowisku developerskim

## ✅ Co zostało skonfigurowane

### Wspólny MySQL
- **Lokalizacja**: Kontener `pneadm-mysql` w `pneadm-bootstrap`
- **Port na hoście**: `3307`
- **Port w Dockerze**: `3306`
- **Użytkownik**: `sail` / `password`

### Trzy wspólne bazy danych

1. **pneadm** - Główna baza administracyjna
   - Kursy, uczestnicy, certyfikaty, szablony
   - Używana przez: `pneadm-bootstrap` i `pnedu`

2. **certgen** - Baza certyfikatów (okres przejściowy)
   - Stare zamówienia, dane historyczne
   - Używana przez: `pneadm-bootstrap` (okres przejściowy)

3. **pnedu** - Baza użytkowników
   - Użytkownicy, sesje, cache
   - Używana przez: `pnedu`

## 🔗 Połączenia

### pneadm-bootstrap (localhost:8083)
- **Główna baza**: `pneadm` (połączenie `mysql`)
- **Baza certgen**: `certgen` (połączenie `mysql_certgen`)
- **MySQL host**: `mysql` (nazwa serwisu Docker)

### pnedu (localhost:8081)
- **Główna baza**: `pnedu` (połączenie `mysql`)
- **Baza pneadm**: `pneadm` (połączenie `pneadm`)
- **Baza certgen**: `certgen` (połączenie `certgen`)
- **MySQL host**: `mysql` (nazwa serwisu Docker z pneadm-bootstrap)

## 📊 phpMyAdmin

### pneadm-bootstrap
- **URL**: `http://localhost:8084`
- **Host**: `mysql` (kontener pneadm-mysql)
- **Widzi wszystkie bazy**: ✅ (pneadm, certgen, pnedu)

### pnedu
- **URL**: `http://localhost:8082`
- **Host**: `pneadm-mysql` (kontener z pneadm-bootstrap)
- **Widzi wszystkie bazy**: ✅ (pneadm, certgen, pnedu)

## ✅ Korzyści

1. **Jedna baza danych** - zmiany widoczne natychmiast w obu serwisach
2. **Brak duplikacji** - nie trzeba kopiować danych
3. **Łatwe zarządzanie** - jeden MySQL, łatwiejsze backup'y
4. **Spójność danych** - zawsze aktualne dane w obu serwisach

## 🔧 Konfiguracja .env

### pneadm-bootstrap/.env
```env
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pneadm
DB_USERNAME=sail
DB_PASSWORD=password

DB_SECOND_HOST=mysql
DB_SECOND_PORT=3306
DB_SECOND_DATABASE=certgen
DB_SECOND_USERNAME=sail
DB_SECOND_PASSWORD=password
```

### pnedu/.env
```env
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pnedu
DB_USERNAME=sail
DB_PASSWORD=password

DB_PNEADM_HOST=mysql
DB_PNEADM_PORT=3306
DB_PNEADM_DATABASE=pneadm
DB_PNEADM_USERNAME=sail
DB_PNEADM_PASSWORD=password

DB_CERTGEN_HOST=mysql
DB_CERTGEN_PORT=3306
DB_CERTGEN_DATABASE=certgen
DB_CERTGEN_USERNAME=sail
DB_CERTGEN_PASSWORD=password
```

## 🧪 Testowanie

### Test 1: Sprawdź dostępność baz
```bash
# W pneadm-bootstrap
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail mysql -e "SHOW DATABASES;" | grep -E "(pneadm|certgen|pnedu)"

# W pnedu
cd /home/hostnet/WEB-APP/pnedu
sail artisan tinker
DB::connection('pneadm')->select('SELECT DATABASE()');
DB::connection('certgen')->select('SELECT DATABASE()');
```

### Test 2: Zmiana w jednym serwisie widoczna w drugim
1. Utwórz/edytuj szablon w `http://localhost:8083/admin/certificate-templates`
2. Sprawdź w `http://localhost:8081` czy zmiany są widoczne (bez restartu!)

### Test 3: phpMyAdmin widzi wszystkie bazy
1. Otwórz `http://localhost:8084` (pneadm-bootstrap)
2. Sprawdź czy widzisz: pneadm, certgen, pnedu
3. Otwórz `http://localhost:8082` (pnedu)
4. Sprawdź czy widzisz: pneadm, certgen, pnedu

## 📝 Notatki

- **MySQL host w Dockerze**: `mysql` (nazwa serwisu) lub `pneadm-mysql` (nazwa kontenera)
- **MySQL host z hosta**: `127.0.0.1:3307` (port mapowany)
- **Wspólna sieć**: `pne-network` (external network)
- **Wspólny volume**: `pne-mysql-shared` (przechowuje dane MySQL)

## ✅ Status

- ✅ Wszystkie trzy bazy utworzone
- ✅ Uprawnienia użytkownika sail skonfigurowane
- ✅ Oba serwisy łączą się do wspólnego MySQL
- ✅ phpMyAdmin widzi wszystkie bazy
- ✅ Zmiany widoczne natychmiast w obu serwisach









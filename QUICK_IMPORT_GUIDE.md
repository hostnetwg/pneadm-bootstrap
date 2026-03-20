# ⚡ Szybki import SQL - Przewodnik

## 🎯 Problem
Import przez phpMyAdmin kończy się timeoutem - użyj CLI (10-50x szybsze!)

## ✅ Szybkie rozwiązanie

### Import do bazy `pneadm` (np. tabela `activity_logs`)

```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap

# Metoda 1: Bezpośredni import (najprostsze)
./vendor/bin/sail mysql pneadm < /ścieżka/do/pliku.sql

# Metoda 2: Z optymalizacją (najszybsze dla dużych plików)
./vendor/bin/sail mysql -u root -ppassword <<EOF
USE pneadm;
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SOURCE /tmp/import.sql;
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
EOF
```

### Import do bazy `certgen`

```bash
./vendor/bin/sail mysql certgen < /ścieżka/do/pliku.sql
```

### Import do bazy `pnedu`

```bash
./vendor/bin/sail mysql pnedu < /ścieżka/do/pliku.sql
```

## 📋 Krok po kroku dla Twojego przypadku

### 1. Skopiuj plik SQL do kontenera (jeśli jest lokalnie)

```bash
# Jeśli plik jest na hoście Windows/WSL
docker cp /mnt/c/Users/TwojaNazwa/Downloads/plik.sql pneadm-mysql:/tmp/import.sql

# LUB jeśli plik jest już w WSL
docker cp ~/Downloads/plik.sql pneadm-mysql:/tmp/import.sql
```

### 2. Importuj do bazy

```bash
# Dla tabeli activity_logs w bazie pneadm:
./vendor/bin/sail mysql pneadm < /ścieżka/do/pliku.sql

# LUB jeśli plik jest w kontenerze:
docker exec -i pneadm-mysql mysql -u root -ppassword pneadm < /tmp/import.sql
```

### 3. Sprawdź czy import się powiódł

```bash
./vendor/bin/sail mysql pneadm -e "SELECT COUNT(*) FROM activity_logs;"
```

## 🚀 Najszybsza metoda (copy-paste)

```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap

# 1. Skopiuj plik do kontenera (zamień na swoją ścieżkę)
docker cp /ścieżka/do/pliku.sql pneadm-mysql:/tmp/import.sql

# 2. Importuj z optymalizacją
./vendor/bin/sail mysql -u root -ppassword pneadm <<EOF
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SOURCE /tmp/import.sql;
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
EOF

# 3. Sprawdź wynik
./vendor/bin/sail mysql pneadm -e "SELECT COUNT(*) FROM activity_logs;"
```

## 💡 Wskazówki

1. **Zawsze używaj CLI dla dużych plików** - phpMyAdmin ma limity
2. **Wyłącz sprawdzanie kluczy obcych** - przyspiesza 10x
3. **Sprawdź rozmiar pliku** przed importem: `ls -lh plik.sql`
4. **Dla plików >1GB** - podziel na części lub użyj `mysql` bezpośrednio

## 🔧 Jeśli nadal masz problemy

### Problem: "MySQL server has gone away"

```bash
# Sprawdź limity MySQL
./vendor/bin/sail mysql -u root -ppassword -e "SHOW VARIABLES LIKE 'max_allowed_packet';"

# Jeśli jest mniejsze niż 512M, zrestartuj kontenery:
./vendor/bin/sail down
./vendor/bin/sail up -d
```

### Problem: Import jest bardzo wolny

```bash
# Użyj metody z wyłączonymi sprawdzeniami (najszybsze):
./vendor/bin/sail mysql -u root -ppassword pneadm <<EOF
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SET AUTOCOMMIT=0;
SOURCE /tmp/import.sql;
COMMIT;
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
SET AUTOCOMMIT=1;
EOF
```

## ✅ Po imporcie

Sprawdź czy dane są poprawne:
```bash
./vendor/bin/sail mysql pneadm -e "SELECT * FROM activity_logs LIMIT 5;"
```








# 🚀 Import dużych plików SQL - Przewodnik

## ❌ Problem
Import dużych plików SQL w phpMyAdmin kończy się błędem:
```
Limit czasu wykonania skryptu minął
Script execution time limit exceeded
```

## ✅ Rozwiązanie

### Metoda 1: Zwiększone limity w phpMyAdmin (już skonfigurowane)

Limity zostały zwiększone w `docker-compose.yml`:
- **Upload limit**: 500M
- **Max execution time**: 3600 sekund (1 godzina)
- **Memory limit**: 1024M

**Aby zastosować zmiany:**
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
./vendor/bin/sail down
./vendor/bin/sail up -d
```

### Metoda 2: Import przez CLI (NAJSZYBSZE - ZALECANE!)

Import przez terminal jest **znacznie szybszy** niż przez phpMyAdmin:

```bash
# 1. Skopiuj plik SQL do kontenera
docker cp /ścieżka/do/pliku.sql pneadm-mysql:/tmp/import.sql

# 2. Importuj bezpośrednio do MySQL
./vendor/bin/sail mysql -u root -ppassword certgen < /tmp/import.sql

# LUB z kontenera:
docker exec -i pneadm-mysql mysql -u root -ppassword certgen < /ścieżka/do/pliku.sql

# LUB jeśli plik jest już w kontenerze:
docker exec -i pneadm-mysql mysql -u root -ppassword certgen < /tmp/import.sql
```

**Przykład dla bazy `certgen`:**
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap

# Metoda A: Przez sail
./vendor/bin/sail mysql certgen < /ścieżka/do/pliku.sql

# Metoda B: Bezpośrednio przez docker
docker exec -i pneadm-mysql mysql -u root -ppassword certgen < /ścieżka/do/pliku.sql
```

### Metoda 3: Import z podziałem na części

Jeśli plik jest bardzo duży (>1GB), podziel go na mniejsze części:

```bash
# Podziel plik na części po 100MB
split -b 100M plik.sql plik_part_

# Importuj każdą część osobno
for file in plik_part_*; do
    echo "Importuję $file..."
    ./vendor/bin/sail mysql certgen < "$file"
done
```

### Metoda 4: Import z optymalizacją MySQL

Dla jeszcze szybszego importu, wyłącz sprawdzanie kluczy obcych:

```bash
# 1. Edytuj my.cnf (tymczasowo)
nano docker/mysql/my.cnf
# Odkomentuj linie:
# foreign_key_checks = 0
# unique_checks = 0

# 2. Zrestartuj MySQL
./vendor/bin/sail restart mysql

# 3. Importuj
./vendor/bin/sail mysql certgen < plik.sql

# 4. Przywróć sprawdzanie (odkomentuj w my.cnf)
# 5. Zrestartuj MySQL
./vendor/bin/sail restart mysql
```

## ⚡ Najszybsza metoda - Import przez CLI z optymalizacją

```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap

# 1. Wyłącz sprawdzanie kluczy obcych (tymczasowo)
./vendor/bin/sail mysql -u root -ppassword -e "SET FOREIGN_KEY_CHECKS=0; SET UNIQUE_CHECKS=0;"

# 2. Importuj plik
./vendor/bin/sail mysql certgen < /ścieżka/do/pliku.sql

# 3. Włącz z powrotem sprawdzanie
./vendor/bin/sail mysql -u root -ppassword -e "SET FOREIGN_KEY_CHECKS=1; SET UNIQUE_CHECKS=1;"
```

**LUB wszystko w jednej komendzie:**
```bash
./vendor/bin/sail mysql -u root -ppassword certgen <<EOF
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SOURCE /tmp/import.sql;
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
EOF
```

## 📊 Porównanie metod

| Metoda | Prędkość | Limit rozmiaru | Zalecane dla |
|--------|----------|----------------|--------------|
| phpMyAdmin | ⭐⭐ | ~500MB | Małe pliki |
| CLI (sail mysql) | ⭐⭐⭐⭐⭐ | Nieograniczony | Wszystkie pliki |
| CLI z optymalizacją | ⭐⭐⭐⭐⭐ | Nieograniczony | Bardzo duże pliki |

## 🔧 Konfiguracja MySQL (już zaktualizowana)

Limity MySQL zostały zwiększone w `docker-compose.yml`:
- `max_allowed_packet`: 512M
- `innodb_buffer_pool_size`: 1G
- `max_connections`: 200
- `wait_timeout`: 600 sekund

## 🚀 Szybki import - Przykład

```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap

# Import do bazy certgen
./vendor/bin/sail mysql certgen < ~/Downloads/email-fb.sql

# Import do bazy pneadm
./vendor/bin/sail mysql pneadm < ~/Downloads/courses.sql

# Import do bazy pnedu
./vendor/bin/sail mysql pnedu < ~/Downloads/users.sql
```

## 📝 Checklist importu

- [ ] Plik SQL jest dostępny lokalnie
- [ ] Zwiększone limity MySQL (już skonfigurowane)
- [ ] Zwiększone limity phpMyAdmin (już skonfigurowane)
- [ ] Wybrano metodę importu (CLI zalecane)
- [ ] Import wykonany
- [ ] Sprawdzono czy dane są poprawne

## 🐛 Troubleshooting

### Problem: "MySQL server has gone away"

**Rozwiązanie:**
```bash
# Zwiększ max_allowed_packet (już skonfigurowane w my.cnf)
# Lub podziel plik na mniejsze części
```

### Problem: "Out of memory"

**Rozwiązanie:**
```bash
# Zwiększ memory_limit w PHP (już skonfigurowane)
# Lub użyj importu przez CLI zamiast phpMyAdmin
```

### Problem: Import jest bardzo wolny

**Rozwiązanie:**
```bash
# Użyj metody CLI z wyłączonymi sprawdzeniami:
./vendor/bin/sail mysql -u root -ppassword -e "SET FOREIGN_KEY_CHECKS=0; SET UNIQUE_CHECKS=0;"
./vendor/bin/sail mysql certgen < plik.sql
./vendor/bin/sail mysql -u root -ppassword -e "SET FOREIGN_KEY_CHECKS=1; SET UNIQUE_CHECKS=1;"
```

## ✅ Po zastosowaniu zmian

1. **Zrestartuj kontenery:**
```bash
./vendor/bin/sail down
./vendor/bin/sail up -d
```

2. **Sprawdź limity MySQL:**
```bash
./vendor/bin/sail mysql -u root -ppassword -e "SHOW VARIABLES LIKE 'max_allowed_packet';"
./vendor/bin/sail mysql -u root -ppassword -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

3. **Przetestuj import:**
```bash
# Mały plik testowy
./vendor/bin/sail mysql certgen < test.sql
```

## 💡 Wskazówki

1. **Zawsze używaj CLI dla dużych plików** - jest znacznie szybsze
2. **Wyłącz sprawdzanie kluczy obcych** podczas importu - przyspiesza 10x
3. **Podziel bardzo duże pliki** na części jeśli import nadal trwa długo
4. **Sprawdź logi** jeśli coś nie działa: `./vendor/bin/sail logs mysql`




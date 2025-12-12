# ✅ Naprawa uprawnień do zapisu w pakiecie pne-certificate-generator

## 🐛 Problem
Błąd podczas wgrywania tła/logo w edytorze szablonów:
```
Permission denied: file_put_contents(/var/www/pne-certificate-generator/storage/certificates/backgrounds/...)
```

## ✅ Rozwiązanie

### 1. Uprawnienia w kontenerze Docker
Katalogi w pakiecie muszą mieć uprawnienia zapisu dla użytkownika `sail` (uid=1337, gid=1000):

```bash
# W kontenerze Docker
sail shell
chmod -R 775 /var/www/pne-certificate-generator/storage
chown -R sail:sail /var/www/pne-certificate-generator/storage
```

### 2. Uprawnienia na hoście (opcjonalnie)
Jeśli chcesz mieć dostęp z hosta:

```bash
# Na hoście
chmod -R 775 pne-certificate-generator/storage
chown -R $USER:$USER pne-certificate-generator/storage
```

### 3. Struktura katalogów
```
pne-certificate-generator/
└── storage/
    └── certificates/
        ├── backgrounds/  ✅ 775, sail:sail
        └── logos/        ✅ 775, sail:sail
```

## 🔍 Weryfikacja

### Sprawdź uprawnienia w kontenerze:
```bash
sail shell
ls -la /var/www/pne-certificate-generator/storage/certificates/
```

Powinno pokazać:
```
drwxrwxr-x 2 sail sail 4096 ... backgrounds/
drwxrwxr-x 2 sail sail 4096 ... logos/
```

### Test zapisu:
```bash
sail shell
touch /var/www/pne-certificate-generator/storage/certificates/backgrounds/test.txt
rm /var/www/pne-certificate-generator/storage/certificates/backgrounds/test.txt
echo "Write test: SUCCESS"
```

## ⚠️ Uwagi

1. **Docker volume mount**: Upewnij się, że w `docker-compose.yml` jest zamontowany volume:
   ```yaml
   volumes:
     - '../pne-certificate-generator:/var/www/pne-certificate-generator'
   ```

2. **Uprawnienia są dziedziczone**: Jeśli katalog `storage` ma złe uprawnienia, podkatalogi też będą miały problemy.

3. **Po zmianie uprawnień**: Może być konieczne zrestartowanie kontenera:
   ```bash
   sail restart
   ```

## ✅ Status
- ✅ Uprawnienia ustawione na 775
- ✅ Właściciel: sail:sail
- ✅ Test zapisu: SUCCESS
- ✅ Edytor szablonów może teraz zapisywać pliki w pakiecie










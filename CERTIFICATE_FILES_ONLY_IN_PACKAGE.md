# ✅ Pliki (logo, tła) TYLKO w pakiecie - bez lokalnych kopii

## 🎯 Cel
Wszystkie pliki graficzne (logo i tła) są przechowywane **TYLKO** w pakiecie `pne-certificate-generator`, bez tworzenia lokalnych kopii w `pneadm-bootstrap` ani `pnedu`.

## ✅ Wykonane zmiany

### 1. **`uploadLogo()` - zapisuje TYLKO w pakiecie**
- ✅ Usunięto zapis lokalny
- ✅ Zapisuje tylko w `pne-certificate-generator/storage/certificates/logos/`
- ✅ Rzuca wyjątek jeśli pakiet niedostępny (nie tworzy lokalnej kopii)

### 2. **`uploadBackground()` - zapisuje TYLKO w pakiecie**
- ✅ Usunięto zapis lokalny
- ✅ Zapisuje tylko w `pne-certificate-generator/storage/certificates/backgrounds/`
- ✅ Rzuca wyjątek jeśli pakiet niedostępny (nie tworzy lokalnej kopii)

### 3. **`store()` i `update()` - zapisują tła TYLKO w pakiecie**
- ✅ Usunięto zapis lokalny podczas tworzenia/aktualizacji szablonu
- ✅ Zapisuje tylko w pakiecie
- ✅ Rzuca wyjątek jeśli pakiet niedostępny

### 4. **`getAvailableLogos()` - sprawdza TYLKO pakiet**
- ✅ Usunięto sprawdzanie lokalnego storage
- ✅ Zwraca tylko pliki z pakietu

### 5. **`getAvailableBackgrounds()` - sprawdza TYLKO pakiet**
- ✅ Usunięto sprawdzanie lokalnego storage
- ✅ Zwraca tylko pliki z pakietu

### 6. **`deleteLogo()` - usuwa TYLKO z pakietu**
- ✅ Usunięto usuwanie z lokalnego storage
- ✅ Usuwa tylko z pakietu

### 7. **`deleteBackground()` - usuwa TYLKO z pakietu**
- ✅ Usunięto usuwanie z lokalnego storage
- ✅ Usuwa tylko z pakietu

## 📁 Struktura plików

### W pakiecie `pne-certificate-generator` (JEDYNE miejsce):
```
pne-certificate-generator/
└── storage/
    └── certificates/
        ├── logos/              ✅ TYLKO tutaj
        └── backgrounds/        ✅ TYLKO tutaj
```

### W projektach (BRAK lokalnych kopii):
```
pneadm-bootstrap/storage/app/public/certificates/  ❌ NIE używane
pnedu/storage/app/public/certificates/            ❌ NIE używane
```

## 🔄 Jak działa teraz

### Upload plików:
1. Użytkownik wgrywa plik przez edytor
2. **Zapisuje TYLKO w pakiecie**: `pne-certificate-generator/storage/certificates/{logos|backgrounds}/`
3. **NIE tworzy lokalnej kopii**
4. **Zapisuje ścieżkę w bazie**: `certificates/{logos|backgrounds}/filename.png`

### Pobieranie listy plików:
1. Sprawdza **TYLKO pakiet**
2. Zwraca listę plików z pakietu
3. **NIE sprawdza lokalnego storage**

### Usuwanie plików:
1. Usuwa **TYLKO z pakietu**
2. **NIE usuwa z lokalnego storage** (bo tam nie ma)

### Generowanie certyfikatu:
1. Szablony sprawdzają pakiet (priorytet)
2. Jeśli nie znajdą w pakiecie, sprawdzają lokalne (fallback dla starych plików)
3. Normalizują ścieżki (stare formaty → nowe)

## ⚠️ Wymagania

### Docker volume musi być zamontowany:
```yaml
volumes:
  - '../pne-certificate-generator:/var/www/pne-certificate-generator'
```

### Uprawnienia do zapisu:
```bash
chmod -R 775 ../pne-certificate-generator/storage
chown -R sail:sail ../pne-certificate-generator/storage
```

## 🧪 Testowanie

1. **Wgraj logo przez edytor:**
   - Sprawdź czy plik jest w pakiecie: `ls ../pne-certificate-generator/storage/certificates/logos/`
   - Sprawdź czy NIE ma lokalnie: `ls storage/app/public/certificates/logos/` (powinno być puste lub stare pliki)

2. **Usuń logo:**
   - Kliknij "Usuń" w galerii
   - Sprawdź czy plik został usunięty z pakietu

3. **Wygeneruj certyfikat:**
   - Powinien używać plików z pakietu

## ✅ Status

- ✅ Upload zapisuje TYLKO w pakiecie
- ✅ Lista plików sprawdza TYLKO pakiet
- ✅ Usuwanie usuwa TYLKO z pakietu
- ✅ Brak lokalnych kopii
- ✅ Szablony używają plików z pakietu













# 📍 Podsumowanie lokalizacji plików certyfikatów

## ✅ Odpowiedzi na pytania

### 1. Czy edytor zapisuje w `pne-certificate-generator/resources/views/certificates`?
**Teraz TAK!** ✅

**Zmiany:**
- `TemplateBuilderService::generateBladeFile()` zapisuje w pakiecie (priorytet)
- Fallback do lokalnego jeśli pakiet niedostępny
- Sprawdza różne lokalizacje pakietu (Docker volume, relatywna ścieżka, vendor)

### 2. Czy pliki (logo, tła) są w pakiecie?
**Teraz TAK!** ✅

**Zmiany:**
- `uploadLogo()` i `uploadBackground()` zapisują w pakiecie
- Pliki w: `pne-certificate-generator/storage/certificates/logos/` i `backgrounds/`
- Również zapisują lokalnie dla kompatybilności

## 📁 Struktura po migracji

### W pakiecie `pne-certificate-generator`:
```
pne-certificate-generator/
├── resources/
│   └── views/
│       └── certificates/
│           ├── default.blade.php          ✅ Podstawowe szablony
│           ├── landscape.blade.php       ✅
│           ├── minimal.blade.php         ✅
│           └── {slug}.blade.php          ✅ Generowane przez edytor
└── storage/
    └── certificates/
        ├── logos/                        ✅ Wspólne logo
        └── backgrounds/                  ✅ Wspólne tła
```

### W projektach (lokalnie - fallback/backup):
```
pneadm-bootstrap/
├── resources/views/certificates/         (backup/fallback)
└── storage/app/public/certificates/      (backup/fallback)

pnedu/
└── storage/app/public/certificates/      (backup/fallback)
```

## 🔄 Jak działa teraz

### Tworzenie szablonu:
1. Edytor w `pneadm-bootstrap` generuje szablon
2. **Zapisuje w pakiecie:** `pne-certificate-generator/resources/views/certificates/{slug}.blade.php`
3. Szablon dostępny dla obu projektów

### Upload plików:
1. Logo/tło wgrywane przez edytor
2. **Zapisuje w pakiecie:** `pne-certificate-generator/storage/certificates/{logos|backgrounds}/`
3. Również lokalnie (dla kompatybilności)
4. Oba projekty mają dostęp do tych samych plików

### Generowanie certyfikatu:
1. Sprawdza szablon w pakiecie (priorytet)
2. Używa konfiguracji z bazy danych
3. Pliki (logo, tła) dostępne z pakietu lub lokalnie (fallback)

## ⚠️ Uwagi

### Dostęp do plików z pakietu

Szablony używają `storage_path('app/public/' . $logoPath)`, co szuka w lokalnym storage.

**Możliwe rozwiązania:**

1. **Symlink (najprostsze):**
```bash
# W każdym projekcie
ln -s ../pne-certificate-generator/storage/certificates storage/app/public/certificates-package
```

2. **Aktualizacja szablonów:**
- Dodać helper sprawdzający pakiet i lokalne
- Użyć w szablonach zamiast bezpośredniej ścieżki

3. **Publikacja przez ServiceProvider:**
- Dodać publikację storage w pakiecie
- Uruchomić `vendor:publish`

## 🎯 Korzyści

- ✅ Szablony wspólne dla obu projektów
- ✅ Pliki (logo, tła) wspólne dla obu projektów
- ✅ Jedna wersja prawdy - łatwiejsze utrzymanie
- ✅ Zmiany w jednym miejscu widoczne w obu projektach

## 📝 Status

- ✅ Szablony zapisywane w pakiecie
- ✅ Logo zapisywane w pakiecie
- ✅ Tła zapisywane w pakiecie
- ✅ Metody pobierania sprawdzają pakiet
- ✅ Metody usuwania usuwają z pakietu
- ⏳ **Opcjonalne:** Konfiguracja dostępu publicznego do plików z pakietu (symlink/publikacja)










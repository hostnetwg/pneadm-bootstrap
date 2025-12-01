# ✅ Naprawa podglądu grafik z pakietu w edytorze szablonów

## 🐛 Problem
Grafiki tła/logo są zapisywane w pakiecie `pne-certificate-generator/storage/certificates/`, ale w edytorze nie ma podglądu. URL `http://localhost:8083/storage/certificates/backgrounds/...` nie działa.

## ✅ Rozwiązanie

### Utworzenie symlinków
Pliki z pakietu muszą być dostępne przez publiczny URL. Utworzono symlinki z lokalnego `public/storage/certificates/` do pakietu:

```bash
# W kontenerze Docker dla pneadm-bootstrap
sail shell
mkdir -p public/storage/certificates
ln -sf /var/www/pne-certificate-generator/storage/certificates/backgrounds public/storage/certificates/backgrounds
ln -sf /var/www/pne-certificate-generator/storage/certificates/logos public/storage/certificates/logos
```

```bash
# W kontenerze Docker dla pnedu
sail shell
mkdir -p public/storage/certificates
ln -sf /var/www/pne-certificate-generator/storage/certificates/backgrounds public/storage/certificates/backgrounds
ln -sf /var/www/pne-certificate-generator/storage/certificates/logos public/storage/certificates/logos
```

## 📁 Struktura

### W pakiecie (źródło):
```
/var/www/pne-certificate-generator/storage/certificates/
├── backgrounds/  (rzeczywiste pliki)
└── logos/        (rzeczywiste pliki)
```

### W projektach (symlinki):
```
pneadm-bootstrap/public/storage/certificates/
├── backgrounds -> /var/www/pne-certificate-generator/storage/certificates/backgrounds
└── logos -> /var/www/pne-certificate-generator/storage/certificates/logos

pnedu/public/storage/certificates/
├── backgrounds -> /var/www/pne-certificate-generator/storage/certificates/backgrounds
└── logos -> /var/www/pne-certificate-generator/storage/certificates/logos
```

## 🔍 Weryfikacja

### Sprawdź symlinki:
```bash
sail shell
ls -la public/storage/certificates/
```

Powinno pokazać:
```
lrwxrwxrwx ... backgrounds -> /var/www/pne-certificate-generator/storage/certificates/backgrounds
lrwxrwxrwx ... logos -> /var/www/pne-certificate-generator/storage/certificates/logos
```

### Sprawdź dostępność plików:
```bash
sail shell
ls public/storage/certificates/backgrounds/ | head -3
```

Powinno pokazać listę plików z pakietu.

### Test URL:
Otwórz w przeglądarce:
```
http://localhost:8083/storage/certificates/backgrounds/1764537269_1764532099-gilosz-a4-poziomy.png
```

Powinno wyświetlić obraz.

## ⚠️ Uwagi

1. **Symlinki są trwałe**: Po utworzeniu symlinki pozostają nawet po restarcie kontenera.

2. **Automatyczne tworzenie**: Jeśli symlinki znikną, można je odtworzyć używając powyższych komend.

3. **Oba projekty**: Symlinki muszą być utworzone w obu projektach (`pneadm-bootstrap` i `pnedu`), aby oba miały dostęp do plików z pakietu.

4. **URL w kodzie**: Kod używa `asset('storage/certificates/backgrounds/' . $filename)`, co automatycznie wskazuje na `public/storage/certificates/backgrounds/`, które jest symlinkiem do pakietu.

## ✅ Status
- ✅ Symlinki utworzone w `pneadm-bootstrap`
- ✅ Symlinki utworzone w `pnedu`
- ✅ Pliki dostępne przez publiczny URL
- ✅ Podgląd w edytorze działa



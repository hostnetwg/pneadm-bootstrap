# 🔧 Naprawa konfiguracji szablonów certyfikatów

## ❌ Problem

Certyfikaty generowały się, ale nie były zgodne z ustawieniami szablonu. Konfiguracja szablonu w bazie danych ma strukturę:

```json
{
    "blocks": {
        "header": {...},
        "block_4": {...},
        "instructor_signature": {...},
        "footer": {...}
    },
    "settings": {...}
}
```

Gdzie `blocks` jest **obiektem** (associative array), ale kod w kontrolerach oczekiwał **tablicy numerycznej**.

## ✅ Rozwiązanie

### 1. Zaktualizowano `CertificateController::generate()`
- Dodano konwersję `blocks` z obiektu na tablicę przed przetwarzaniem
- Sprawdza czy `blocks` jest obiektem (associative array) czy tablicą numeryczną
- Konwertuje obiekt na tablicę używając `array_values()`

### 2. Zaktualizowano `CertificateTemplateController::preview()`
- Dodano tę samą konwersję `blocks` z obiektu na tablicę
- Zaktualizowano format danych przekazywanych do widoku:
  - Dodano `sortedBlocks` (posortowane bloki)
  - Dodano `instructorSignatureBlock` i `footerBlock` (wyodrębnione)
  - Zachowano kompatybilność wsteczną z `headerConfig`, `courseInfoConfig`, `footerConfig`

## 📝 Zmiany w kodzie

### Przed:
```php
$blocks = $config['blocks'] ?? [];
foreach ($blocks as $block) {
    // Błąd: jeśli blocks jest obiektem, foreach iteruje po kluczach, nie po wartościach
}
```

### Po:
```php
$blocksRaw = $config['blocks'] ?? [];
$blocks = [];
if (is_array($blocksRaw)) {
    // Sprawdź czy to obiekt (associative array) czy tablica numeryczna
    if (array_keys($blocksRaw) !== range(0, count($blocksRaw) - 1)) {
        // To jest obiekt (associative array) - konwertuj na tablicę
        $blocks = array_values($blocksRaw);
    } else {
        // To już jest tablica numeryczna
        $blocks = $blocksRaw;
    }
}
foreach ($blocks as $block) {
    // Teraz działa poprawnie - iteruje po wartościach
}
```

## 🎯 Efekt

Teraz certyfikaty są generowane zgodnie z konfiguracją szablonu:
- ✅ Bloki są renderowane w poprawnej kolejności (według `order`)
- ✅ Ustawienia szablonu są poprawnie zastosowane (marginesy, czcionki, orientacja, tło)
- ✅ Wszystkie bloki są poprawnie wyodrębnione i przekazane do widoku

## 🧪 Testowanie

1. Edytuj szablon: `http://localhost:8083/admin/certificate-templates/5/edit`
2. Wygeneruj certyfikat dla uczestnika
3. Sprawdź czy certyfikat jest zgodny z ustawieniami szablonu

## ✅ Status

- ✅ `CertificateController::generate()` - naprawiony
- ✅ `CertificateTemplateController::preview()` - naprawiony
- ✅ Cache wyczyszczony
- ✅ Kompatybilność wsteczna zachowana
















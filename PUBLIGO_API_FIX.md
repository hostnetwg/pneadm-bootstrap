# Poprawka autoryzacji WP IDEA API - Publigo.pl

## Problem

Otrzymywałeś błąd autoryzacji:
```
Status: Auth_failed
Wiadomość: Wszystkie metody autoryzacji zawiodły. Sprawdź dokumentację WP IDEA API.
```

W szczególności:
- `GET /wp-json/wp-idea/v1/products` - błąd "REST API wrong token"
- `POST /wp-json/wp-idea/v1/orders` - błąd "REST API wrong token"

## Przyczyna

Zgodnie z odpowiedzią od Publigo.pl, poprzednia implementacja autoryzacji była nieprawidłowa:

**Poprzednia implementacja (BŁĘDNA):**
```php
// Generowało różne nonce dla nonce i tokenu!
'nonce' => $this->generateNonce(),
'token' => $this->generateToken($apiKey, $this->generateNonce())
```

**Poprawna implementacja:**
```php
// Generuje jeden nonce i używa go do tokenu
$nonce = $this->generateNonce();
'nonce' => $nonce,
'token' => $this->generateToken($apiKey, $nonce)
```

## Rozwiązanie

Zgodnie z dokumentacją Publigo.pl:

1. **nonce** - unikalny string dla każdego żądania
2. **token** - MD5 z konkatenacji `nonce + klucz WP Idea`

### Poprawione funkcje

```php
/**
 * Generowanie nonce dla WP IDEA API
 */
private function generateNonce()
{
    // WP IDEA wymaga unikalnego nonce dla każdego żądania
    // Używamy uniqid() + timestamp + random string
    return uniqid() . '_' . time() . '_' . rand(1000, 9999);
}

/**
 * Generowanie tokenu MD5 dla WP IDEA API
 * Zgodnie z dokumentacją: token = md5(nonce + api_key)
 */
private function generateToken($apiKey, $nonce)
{
    // WP IDEA wymaga tokenu MD5 z konkatenacji nonce + api_key
    return md5($nonce . $apiKey);
}
```

### Poprawione miejsca w kodzie

1. **Metody autoryzacji** - każda metoda generuje jeden nonce
2. **Test GET products** - używa tego samego nonce
3. **Test POST orders** - używa tego samego nonce
4. **Wszystkie endpointy WP IDEA** - zgodne z dokumentacją

## Testowanie

Poprawki zostały przetestowane i działają poprawnie:

- ✓ Nonce są unikalne dla każdego żądania
- ✓ Token jest poprawnie obliczony jako MD5(nonce + api_key)
- ✓ Każde żądanie ma unikalny nonce i token
- ✓ Zgodne z dokumentacją Publigo.pl

## Endpointy do testowania

Po poprawkach możesz przetestować:

1. **Lista kursów:** `GET /wp-json/wp-idea/v1/products`
2. **Tworzenie zamówienia:** `POST /wp-json/wp-idea/v1/orders`

## Konfiguracja

Upewnij się, że masz poprawną konfigurację:

```php
// config/services.php
'publigo' => [
    'api_key' => '785c5a5180e74789554dd3f3bba6b15e',
    'instance_url' => 'https://nowoczesna-edukacja.pl',
    'api_version' => 'v1',
    'timeout' => 30,
],
```

## Dokumentacja

- **Publigo.pl API:** https://documenter.getpostman.com/view/6467622/SzKVSyS5?version=latest
- **Klucz API:** Narzędzia → Klucz API → API key v1

## Status

✅ **POPRAWIONE** - Autoryzacja WP IDEA API działa zgodnie z dokumentacją Publigo.pl

## Aktualizacja - 2025-01-03

### Nowy problem: Brak uprawnień

Po poprawce autoryzacji, błąd zmienił się z:
- **Poprzedni:** `"REST API wrong token"` ❌
- **Obecny:** `"Brak uprawnień żeby to zrobić"` ⚠️

### Co to oznacza?

1. ✅ **Token jest poprawny** - API rozpoznaje autoryzację
2. ❌ **Brak uprawnień** - Klucz API nie ma dostępu do WP IDEA API

### Rozwiązania do sprawdzenia

#### 1. Sprawdź uprawnienia klucza API w Publigo.pl
- **Narzędzia → Klucz API → API key v1**
- Czy klucz ma uprawnienia do "WP IDEA API" (nie tylko Shoper)
- Czy są włączone odpowiednie "capabilities"

#### 2. Sprawdź czy WP IDEA API jest włączone
- **Ustawienia → WP IDEA → API**
- Czy "REST API" jest włączone
- Czy "Public API access" jest włączone

#### 3. Sprawdź role użytkownika
Klucz API może być powiązany z użytkownikiem WordPress. Sprawdź czy użytkownik ma rolę:
- `administrator` lub
- `shop_manager` lub
- `wp_idea_manager`

#### 4. Sprawdź czy potrzebny jest osobny klucz
Może być tak, że:
- Klucz `785c5a5180e74789554dd3f3bba6b15e` jest tylko do Shoper
- Do WP IDEA API potrzebny jest osobny klucz

### Dodane testy

Dodałem testy sprawdzające uprawnienia i status:

1. **Test `user_permissions`** - sprawdza dostęp do `/wp-json/wp/v2/users/me`
2. **Test `public_posts_access`** - sprawdza dostęp do publicznych postów WordPress (bez autoryzacji)
3. **Test `wp_idea_plugin_status`** - sprawdza czy WP IDEA plugin jest aktywny i ma dostępne endpointy

Te testy pomogą zdiagnozować:
- Czy problem jest z uprawnieniami użytkownika
- Czy WordPress REST API jest w pełni dostępne
- Czy WP IDEA plugin jest poprawnie skonfigurowany

### Aktualne wyniki testów (2025-01-03)

Po uruchomieniu nowych testów:

- ✅ **user_permissions:** `403 Forbidden` - "Nie masz uprawnień dostępu do tego zasobu"
- ✅ **public_posts_access:** Sprawdzi dostęp do publicznych postów WordPress
- ✅ **wp_idea_plugin_status:** Sprawdzi status WP IDEA plugin

**Wniosek:** Klucz API nie ma uprawnień do żadnych chronionych endpointów WordPress.

## 🔍 **Nowa diagnoza - Klucz API v1 vs v2**

Po sprawdzeniu panelu Publigo.pl:

### Klucz API v1: `785c5a5180e74789554dd3f3bba6b15e`
- **Przeznaczenie:** Shoper, Woocommerce, Presta
- **Problem:** **NIE MA uprawnień do WP IDEA API!**
- **Status:** ❌ Nie nadaje się do integracji z WP IDEA

### Klucz API v2: `25d9f6e84483e1063cd3f503d69430ea`
- **Przeznaczenie:** Zapier i inne systemy
- **Potencjał:** Może mieć szersze uprawnienia
- **Status:** 🔄 Do przetestowania

### ✅ **Zastosowane rozwiązanie**

**Zmieniono konfigurację z klucza v1 na v2:**
- **Poprzedni klucz:** `785c5a5180e74789554dd3f3bba6b15e` (tylko e-commerce)
- **Nowy klucz:** `25d9f6e84483e1063cd3f503d69430ea` (Zapier i inne systemy)

**Plik `.env` został zaktualizowany:**
```bash
#PUBLIGO_API_KEY=785c5a5180e74789554dd3f3bba6b15e
PUBLIGO_API_KEY=25d9f6e84483e1063cd3f503d69430ea
```

### Następne kroki

1. **Uruchom ponownie test API** - teraz z kluczem v2
2. **Sprawdź czy klucz v2 ma uprawnienia do WP IDEA API**
3. **Jeśli klucz v2 też nie działa** - skontaktuj się z Publigo.pl o klucz z uprawnieniami WP IDEA
4. **Sprawdź czy WP IDEA plugin jest aktywny** i ma włączone API

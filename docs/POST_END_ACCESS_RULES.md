# Dostęp po zakończeniu szkolenia

Ten dokument opisuje wspólną regułę ustawiania `participants.access_expires_at` dla klasycznych szkoleń z tabeli `courses`, szczególnie gdy klient kupuje dostęp do nagrania lub rejestruje zaświadczenie po zakończeniu wydarzenia.

## Ustawienia

### Globalne

Panel: `Ustawienia -> Zakupy pnedu.pl`.

Pola:

- `default_post_end_access_duration_value`
- `default_post_end_access_duration_unit`

Domyślna wartość to `2 months`. Jest używana, gdy szkolenie ani wariant cenowy nie mają własnego nadpisania.

### Na poziomie szkolenia

Panel: `courses/create` i `courses/{id}/edit`, sekcja `Dostęp po zakończeniu szkolenia`.

Pola:

- `post_end_access_duration_value`
- `post_end_access_duration_unit`

Jeżeli pola są puste, szkolenie korzysta z ustawienia globalnego. Jeśli są ustawione, mają wyższy priorytet niż ustawienie globalne.

### Na poziomie wariantu cenowego

Panel: wariant cenowy szkolenia, sekcja `Dostępność i dostęp po zakończeniu szkolenia`.

`availability_after_course_end` steruje widocznością wariantu na stronie publicznej:

- `always` - wariant widoczny zawsze,
- `hide_after_end` - wariant ukryty po `courses.end_date`,
- `show_after_end` - wariant widoczny dopiero po `courses.end_date`.

`post_end_access_rule` steruje dostępem po zakończeniu:

- `inherit` - użyj reguły szkolenia albo globalnej,
- `duration` - użyj okresu z wariantu,
- `unlimited` - dostęp bezterminowy (`access_expires_at = null`).

## Priorytety

Dla zakupu po zakończeniu szkolenia:

1. Reguła po zakończeniu ustawiona w wariancie cenowym.
2. Reguła ustawiona na szkoleniu.
3. Reguła globalna z `payment_display_options`.
4. Fallback techniczny: `2 months`.

Dla zakupu przed zakończeniem szkolenia nadal działa standardowa reguła wariantu `access_type` (typy 1-5).

## Punkty startowe

- Zamówienie z formularza pnedu po zakończeniu szkolenia: okres liczony od `form_orders.order_date`, a jeśli jej brak, od momentu przyznania dostępu.
- Rejestracja zaświadczenia: okres liczony od `courses.end_date`.
- Ręczne dodawanie uczestnika: pole `Data wygaśnięcia dostępu` jest domyślnie wypełniane jako `courses.end_date + okres`, ale operator może je zmienić albo wyczyścić.

## Główne pliki

- `app/Services/ParticipantAccessExpiryService.php` - centralne wyliczanie daty dostępu.
- `app/Services/FormOrderPneduProvisionService.php` - dodawanie uczestnika z zamówienia.
- `app/Http/Controllers/Api/CertificateRegistrationController.php` - publiczna rejestracja zaświadczenia.
- `app/Http/Controllers/ParticipantController.php` - ręczne dodawanie uczestnika.
- `resources/views/course-price-variants/*.blade.php` - konfiguracja widoczności wariantów i reguł po zakończeniu.
- `resources/views/settings/pnedu-purchases.blade.php` - globalna reguła.

## Uwagi dla pnedu

Aplikacja `pnedu` odczytuje `courses`, `course_price_variants` i `payment_display_options` z bazy `pneadm`. Widoczność wariantów po zakończeniu jest filtrowana po stronie publicznej przed pokazaniem wyboru wariantu i przed przyjęciem `price_variant_id` z formularza.

# Oferty Szkoleń Bez Terminu

Data utworzenia/aktualizacji: 2026-07-28  
Status: MVP wdrożone lokalnie

## Cel Funkcji

Funkcja `training_offers` wprowadza osobny katalog ofert/szablonów szkoleń bez ustalonego terminu. Oferty są zarządzane w `adm.pnedu.pl` (`pneadm`) i mogą być publikowane na `pnedu.pl` w menu `Szkolenia -> Szkolenia rad pedagogicznych`.

Oferta szkolenia nie jest jeszcze realnym szkoleniem terminowym. Realne szkolenia, uczestnicy, zamówienia, zaświadczenia i płatności nadal pozostają powiązane z tabelą `courses`.

## Różnica Między `training_offers` A `courses`

`training_offers`:

- opisuje propozycję szkolenia bez daty,
- może być widoczna publicznie jako oferta dla rad pedagogicznych,
- może mieć cenę indywidualną albo konkretną kwotę,
- nie ma uczestników, zamówień, certyfikatów ani linków do spotkania,
- w przyszłości może służyć jako szablon do utworzenia rekordu w `courses`.

`courses`:

- opisuje konkretne szkolenie z terminem,
- wymaga `start_date` i `end_date`,
- jest powiązane z uczestnikami, zamówieniami, wariantami cen, zaświadczeniami i dostępami,
- może być szkoleniem otwartym albo zamkniętym.

## Zakres MVP

W pierwszym etapie wdrożenia:

- dodana jest tabela `training_offers` w bazie `pneadm`,
- oferty są zarządzane w panelu `pneadm`,
- aktywne i opublikowane oferty są wyświetlane publicznie w `pnedu`,
- menu `Szkolenia -> Szkolenia rad pedagogicznych` prowadzi do katalogu ofert,
- każda oferta może mieć stronę szczegółową z adresem opartym o `slug`,
- strona szczegółowa ma prosty formularz zapytania wysyłający e-mail do adresu systemowego.

Publiczne widoki ofert używają struktury dopasowanej do szkoleń bez terminu. Nie powinny wyglądać jak archiwalne webinary ani konkretne wydarzenia z kalendarza. Na liście i stronie szczegółowej nacisk jest położony na temat, odbiorców, cenę, pełny opis oferty i kontakt w sprawie terminu. Pole `scope` (zakres/zagadnienia) jest przeznaczone głównie do zaświadczeń i nie jest wyświetlane publicznie, żeby nie sugerować zawężonego programu.

Uwaga redakcyjna: w publicznym katalogu nie należy używać grafik z datą, QR kodem lub komunikatem „Zapisz się”, jeśli oferta nie ma ustalonego terminu. Takie materiały mogą sugerować archiwalne wydarzenie zamiast aktualnej oferty dla placówki.

Formularz zapytania w MVP:

- działa tylko dla ofert aktywnych i opublikowanych na `pnedu`,
- wysyła e-mail z kontekstem oferty i danymi kontaktowymi,
- nie zapisuje zapytań w bazie,
- nie dodaje panelu obsługi zapytań w `pneadm`.

Poza MVP pozostają:

- zapis zapytań w bazie i panel obsługi w `pneadm`,
- wyróżnianie wybranych ofert na stronie głównej,
- automatyczne kopiowanie oferty do `courses`,
- analityka leadów i zdarzeń na ofertach.

## Pola Tabeli `training_offers`

- `title` - nazwa oferty widoczna w panelu i publicznie.
- `slug` - unikalny adres publiczny oferty w `pnedu`.
- `summary` - krótki opis używany na liście ofert.
- `description_html` - pełny opis marketingowy oferty.
- `scope` - zakres/zagadnienia szkolenia, przydatne przy późniejszych zaświadczeniach.
- `audience` - grupa odbiorców, np. rady pedagogiczne, dyrektorzy szkół, nauczyciele.
- `price_mode` - tryb ceny: `individual` albo `fixed`.
- `price_amount` - konkretna cena brutto dla trybu `fixed`.
- `instructor_id` - opcjonalny trener z tabeli `instructors`.
- `image` - grafika oferty.
- `default_course_category` - domyślna kategoria przyszłego kursu: `open` albo `closed`; dla ofert rad pedagogicznych domyślnie `closed`.
- `is_active` - status administracyjny oferty.
- `show_on_pnedu` - publikacja w katalogu ofert na `pnedu.pl`.
- `featured_on_homepage` - wyróżnienie oferty w sekcji szkoleń rad pedagogicznych na stronie głównej `pnedu.pl`.
- `sort_order` - kolejność na publicznej liście ofert.
- `meta_title` - tytuł SEO strony szczegółowej.
- `meta_description` - opis SEO strony szczegółowej.
- `internal_notes` - notatki administracyjne niewidoczne publicznie.
- `created_at`, `updated_at`, `deleted_at` - standardowe pola techniczne.

## Mapowanie Przy Przyszłym Kopiowaniu Do `courses`

Późniejszy przycisk „Utwórz szkolenie z oferty” powinien otwierać formularz tworzenia szkolenia z częścią pól wypełnioną automatycznie. Nie powinien tworzyć w pełni gotowego szkolenia bez udziału użytkownika, ponieważ `courses` wymaga daty rozpoczęcia i zakończenia.

## Tworzenie Szkolenia Z Oferty (wdrożone)

W `adm.pnedu.pl`:

- przycisk „Utwórz szkolenie z oferty” na podglądzie i liście ofert,
- trasa `GET /training-offers/{offer}/create-course`,
- formularz `courses/create` wypełniony danymi oferty,
- `courses.training_offer_id` zapisuje źródło oferty,
- grafika oferty jest tylko podglądana w formularzu i kopiowana do `courses/images` przy zapisie (jeśli zaznaczono i nie wgrano nowego pliku),
- `show_on_pnedu` dla nowego szkolenia jest domyślnie wyłączone,
- przy cenie indywidualnej i stałej `is_paid` ustawiane jest na płatne; wariant cenowy nie jest tworzony automatycznie.

Mapowanie bezpośrednie:

- `training_offers.title` -> `courses.title`
- `training_offers.summary` -> `courses.offer_summary`
- `training_offers.description_html` -> `courses.offer_description_html`
- `training_offers.scope` -> `courses.description`
- `training_offers.instructor_id` -> `courses.instructor_id`
- `training_offers.image` -> `courses.image`
- `training_offers.internal_notes` -> `courses.notatki`
- `training_offers.default_course_category` -> `courses.category`

Mapowanie częściowe:

- `training_offers.price_mode` -> wpływa na `courses.is_paid`
- `training_offers.price_amount` -> przyszły domyślny rekord w `course_price_variants.price`

Pola wymagające decyzji użytkownika przy kopiowaniu:

- `courses.start_date`
- `courses.end_date`
- `courses.type`
- `courses.show_on_pnedu`
- ewentualne dane online lub lokalizacja stacjonarna
- warianty cenowe, jeśli oferta ma konkretną cenę

Pola bez kopiowania:

- `slug`
- `show_on_pnedu`
- `sort_order`
- `meta_title`
- `meta_description`
- `is_active`

## Przepływ Danych W MVP

```text
pneadm
    -> tworzenie i edycja ofert w panelu
    -> tabela training_offers w bazie pneadm

pnedu
    -> odczyt aktywnych i opublikowanych ofert z bazy pneadm
    -> lista /szkolenia-rad-pedagogicznych
    -> wyróżnione oferty featured_on_homepage na stronie głównej
    -> ogólny formularz zapytania GET/POST /szkolenia-rad-pedagogicznych/zapytanie
    -> szczegóły /szkolenia-rad-pedagogicznych/{slug}
    -> formularz POST /szkolenia-rad-pedagogicznych/{slug}/zapytanie
    -> oba formularze wysyłają e-mail (TrainingOfferInquiryMail), bez zapisu w bazie
```

## Kontynuacja Po MVP

Kolejne etapy powinny być wdrażane osobno:

1. Zapis zapytań w bazie i lista zapytań w `pneadm`.
2. ~~Wyróżnianie wybranych ofert na stronie głównej.~~ **Wdrożone lokalnie** (`featured_on_homepage`).
3. ~~Tworzenie rekordu `courses` na podstawie oferty.~~ **Wdrożone lokalnie** (prefill + `training_offer_id` + kopiowanie grafiki przy zapisie).
4. Analityka wejść i zapytań ofertowych.

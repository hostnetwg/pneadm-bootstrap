# PNE Growth OS — model domenowy

Data utworzenia/aktualizacji: 2026-09-29
Wersja dokumentacji: 0.1
Status: model logiczny, bez tabel i migracji

## Zasady

- Nie duplikujemy istniejących modeli tylko dlatego, że Growth OS używa innego języka biznesowego.
- Nowe obiekty mają referencje do istniejących rekordów, a nie kopie całej ich zawartości.
- Nazwy techniczne muszą usuwać kolizje z istniejącym `MarketingCampaign`, `Product` i dwoma modelami `User`.
- Model fizyczny powstanie dopiero po akceptacji konkretnego etapu.
- PII pozostaje w bazach operacyjnych; graf relacji nie jest pretekstem do niekontrolowanego kopiowania danych.

## Mapa obiektów

| Obiekt Growth OS | Stan obecny | Rekomendacja |
|---|---|---|
| Topic | brak uniwersalnej taksonomii | nowy obiekt |
| Campaign | istnieje `MarketingCampaign`, ale tylko do atrybucji/linków | nowa kampania Growth, jawnie odróżniona |
| Expert | istnieje `Instructor` | profil rozszerzający `Instructor` |
| ContentAsset | treści są rozproszone | nowy indeks/agregat linkujący istniejące treści |
| Product | `Course`, `OnlineCourse`, `Product`, `TrainingOffer` | logiczna referencja do istniejących typów, bez drugiego katalogu |
| Person | wiele reprezentacji osoby | etap późniejszy; warstwa identity resolution |
| Organization | brak kanonicznej szkoły | nowy obiekt w etapie CRM B2B |
| Interaction | wiele logów i eventów | wspólny kontrakt zdarzeń, bez kopiowania PII |
| Metric | istniejące agregaty | read models nad `pne_analytics` i źródłami biznesowymi |

## Topic

Nowy obiekt opisujący potrzebę lub obszar tematyczny, niezależny od konkretnego formatu.

Przykładowe informacje:

- nazwa i opis,
- źródło pomysłu,
- uzasadnienie i aktualność,
- grupy odbiorców,
- powiązani eksperci,
- cele biznesowe,
- status: Nowy, Do analizy, Zaplanowany, W realizacji, Wykorzystany, Do ponownego wykorzystania,
- poziom pewności i data ponownej oceny.

`CourseSeries` nie zastępuje Topic. Seria grupuje szkolenia, a Topic ma opisywać potrzebę, która może prowadzić do wielu formatów i produktów.

## Growth Campaign

Centralna jednostka pracy łącząca temat, cel, eksperta, planowane treści, decyzje i wyniki.

Nie jest tym samym co `MarketingCampaign`:

- Growth Campaign — inicjatywa strategiczna i redakcyjna,
- `MarketingCampaign` — kod linku, UTM, landing target i atrybucja.

Przyszła kampania Growth może mieć zero, jeden lub wiele powiązanych rekordów `MarketingCampaign`.

Przykładowe informacje:

- nazwa robocza,
- topic,
- cel główny i cele pomocnicze,
- odbiorcy,
- ekspert wiodący i współpracujący,
- ramy czasowe,
- status,
- hipoteza wartości,
- zestaw rekomendowanych formatów,
- zatwierdzenia,
- powiązane treści i produkty,
- metryki sukcesu odpowiednie do celu.

Rekomendowana nazwa tabeli w przyszłości: prefiks `growth_`, aby nie kolidować z `marketing_campaigns`. Nie jest to jeszcze decyzja o migracji.

## Expert

`Instructor` jest kanoniczną osobą prowadzącą i pozostaje właścicielem:

- danych kontaktowych,
- bio,
- zdjęcia,
- szkoleń,
- ofert,
- kursów online,
- ankiet i rozliczeń.

Growth OS może w przyszłości dodać profil 1:1, zawierający wyłącznie informacje Growth:

- specjalizacje i tematy,
- grupy odbiorców,
- preferowane formaty,
- zasady języka i wizerunku,
- kanały marki,
- ograniczenia approval,
- cele rozwoju marki eksperta.

Nie tworzymy drugiego niezależnego eksperta. Artykuły powinny docelowo móc wskazywać eksperta, ale dziś `Article.author_id` wskazuje operatora ADM, więc nie wolno automatycznie utożsamiać autora z `Instructor`.

## ContentAsset

Nowy indeks treści potrzebny jest dlatego, że zasoby są obecnie rozproszone:

- `Article`,
- `CourseVideo`,
- `CourseFileLink`,
- `OnlineCourseLesson` i załączniki,
- grafiki i pliki w storage,
- przyszłe transkrypcje, shorty, posty, newslettery i e-booki.

ContentAsset powinien:

- mieć typ, status, format i właściciela,
- wskazywać kampanię, temat i ekspertów,
- opcjonalnie linkować istniejący rekord przez typ i ID,
- przechowywać źródło i pochodzenie wersji,
- rozróżniać draft, zatwierdzony, opublikowany i zarchiwizowany,
- wspierać relacje „powstało z” oraz „jest fragmentem”.

Nie powinien kopiować treści artykułu lub kursu tylko po to, aby pokazać ją w bibliotece. Kopia jest uzasadniona dla wersji roboczej, transkrypcji lub snapshotu wysłanego do zewnętrznego kanału.

## Product

Istniejące pojęcia:

- `Course` — konkretne szkolenie/webinar z terminem,
- `TrainingOffer` — oferta bez terminu, głównie B2B,
- `OnlineCourse` — treść i struktura kursu nagranego,
- `Product` — katalog handlowy, obecnie m.in. kurs online i przyszły e-book,
- `ProductOffer` / `ProductPrice` — oferta i cena.

Growth OS potrzebuje logicznego „powiązanego produktu”, ale nie nowego katalogu sprzedaży. Relacja powinna wskazywać typ i ID istniejącego obiektu.

Zasada krytyczna: nie zmieniać znaczenia `form_orders.product_id`, które dla zamówień szkoleń oznacza `courses.id`.

## Person

Obecne reprezentacje:

- konto `pnedu.users`,
- `Participant`,
- `FormOrderParticipant`,
- `OnlineCourseEnrollment`,
- `OrderItemRecipient`,
- dane zamawiającego i kontaktowe w `FormOrder`,
- subskrybent w Sendy.

Nie ma globalnego ID osoby ani bezpiecznego automatycznego scalenia. E-mail jest używany operacyjnie, ale może się zmieniać i jest PII.

Rekomendacja:

- nie wdrażać Person w pierwszych etapach,
- najpierw spisać przypadki użycia i podstawę prawną,
- rozdzielić identity resolution od profilu marketingowego,
- zachować możliwość błędu, ręcznego rozdzielenia i audytu połączeń.

## Organization

Dziś szkoła lub firma jest zwykle snapshotem w `form_orders` (`buyer_*`, `recipient_*`, NIP, profil klienta). GUS i RSPO pomagają w lookup/importach, ale nie tworzą master data.

Nowa Organization ma sens dopiero w CRM szkół. Powinna obsługiwać:

- szkołę, organ prowadzący, placówkę lub inną organizację,
- osoby kontaktowe i role,
- wiele adresów i identyfikatorów,
- historię zapytań, ofert i szkoleń,
- ręczne scalanie duplikatów,
- źródła i aktualność danych.

Nie należy automatycznie backfillować organizacji ze wszystkich zamówień bez preview, reguł dopasowania, backupu i zatwierdzenia.

## Interaction

Źródła interakcji już istnieją:

- `analytics_events`,
- odsłony treści i kursów,
- zamówienia i płatności,
- udział w szkoleniu,
- ankiety,
- logowania,
- aktywność operatorów,
- wysyłki i webhooks.

Growth OS powinien definiować wspólną semantykę i read model, nie kopiować wszystkich payloadów. Interakcja przechowywana dla relacji z osobą wymaga osobnej analizy RODO.

## Metric

Metryki powinny wskazywać:

- definicję biznesową,
- źródło danych,
- okno czasu,
- poziom agregacji,
- datę ostatniego przeliczenia,
- jakość/kompletność danych.

Źródła:

- agregaty `pne_analytics`,
- `marketing_campaign_stats_daily`,
- `course_page_stats_daily`,
- `revenue_records`,
- statystyki artykułów,
- w przyszłości statystyki kanałów zewnętrznych.

AI powinno otrzymywać agregaty i definicje metryk, nie surowe dane klientów.

## Relacje wysokiego poziomu

```text
Topic
  └─ GrowthCampaign
       ├─ ExpertProfile → Instructor
       ├─ ContentAsset → Article / CourseVideo / plik / draft
       ├─ ProductReference → Course / TrainingOffer / OnlineCourse / Product
       ├─ MarketingCampaign (0..n, atrybucja)
       ├─ Approval
       └─ MetricReference

ContentAsset
  ├─ derived_from → ContentAsset
  ├─ contains_topic → Topic
  └─ attributed_to → Instructor
```

## Decyzje odłożone

Przed projektem pierwszych migracji trzeba zatwierdzić:

1. fizyczną bazę danych dla tabel Growth,
2. dokładne nazwy techniczne,
3. czy ContentAsset używa relacji polimorficznej czy jawnej tabeli linków,
4. model wersjonowania draftów,
5. zakres przechowywania treści zewnętrznych,
6. retencję transkrypcji, promptów i wyników AI,
7. model Person i podstawę prawną,
8. zasady scalania Organization.

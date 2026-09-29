<?php

namespace App\Support\GrowthOS;

/**
 * Demonstracyjny scenariusz Etapu 0.2 — najbliższy webinar TIK jako case study.
 * Brak odczytu z bazy i side effectów. Dane są jawnie fikcyjne / wzorcowe.
 */
class DemoTikWebinarScenario
{
    public const CAMPAIGN_ID = 'tik-webinar-demo';

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     topic: string,
     *     goal: string,
     *     audience: string,
     *     expert: string,
     *     starts_at_label: string,
     *     status: string,
     *     formats: list<string>,
     *     notes: string
     * }
     */
    public static function campaign(): array
    {
        return [
            'id' => self::CAMPAIGN_ID,
            'name' => 'Webinar TIK: Canva AI w pracy nauczyciela',
            'topic' => 'Nowe funkcje Canva AI przydatne w przygotowaniu materiałów dydaktycznych',
            'goal' => 'Budowanie eksperckości + zapis na płatne szkolenie pogłębiające',
            'audience' => 'Nauczyciele szkół podstawowych i ponadpodstawowych, zainteresowani TIK',
            'expert' => 'Ekspert PNE (demonstracja)',
            'starts_at_label' => 'środa, 8 października 2026, godz. 20:00',
            'status' => 'Czeka na decyzje',
            'formats' => [
                'Live webinar (ClickMeeting)',
                'Mailing zapraszający',
                'Post / grafika do social media',
                'Follow-up z nagraniem i CTA',
            ],
            'notes' => 'Scenariusz demonstracyjny Etapu 0.2. Nie jest powiązany z rekordem w bazie szkoleń.',
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     summary: string,
     *     reason: string,
     *     time_estimate: string,
     *     campaign_id: string,
     *     preview: array{headline: string, body: string, cta: string|null},
     *     risk: string
     * }>
     */
    public static function decisions(): array
    {
        $campaignId = self::CAMPAIGN_ID;

        return [
            [
                'id' => 'mailing-invite',
                'type' => 'Mailing',
                'title' => 'Mailing zapraszający na webinar TIK',
                'summary' => 'Wersja edukacyjna z jasnym CTA do zapisu na live.',
                'reason' => 'Cel kampanii to budowanie eksperckości i zapis. Mail bez agresywnej sprzedaży lepiej pasuje do listy nauczycieli.',
                'time_estimate' => 'ok. 3 min',
                'campaign_id' => $campaignId,
                'preview' => [
                    'headline' => 'Canva AI w pracy nauczyciela — darmowy webinar w środę o 20:00',
                    'body' => "Pokażemy 5 funkcji Canva AI, które realnie skracają przygotowanie materiałów.\n"
                        ."Bez teorii „dla informatyków” — konkretne przykłady z lekcji i rady pedagogicznej.\n\n"
                        .'Po webinarze wyślemy nagranie i krótki zestaw promptów.',
                    'cta' => 'Zapisz się na webinar',
                ],
                'risk' => 'Zatwierdzenie w prototypie nic nie wysyła. Na produkcji mailing wymagałby osobnego podglądu i wykonania.',
            ],
            [
                'id' => 'social-post',
                'type' => 'Treść / social',
                'title' => 'Post zapowiadający webinar',
                'summary' => 'Krótki post + propozycja nagłówka grafiki.',
                'reason' => 'Kanał social wzmacnia zasięg organiczny przed live i buduje rozpoznawalność eksperta.',
                'time_estimate' => 'ok. 2 min',
                'campaign_id' => $campaignId,
                'preview' => [
                    'headline' => 'Środa 20:00 — Canva AI dla nauczycieli',
                    'body' => "5 funkcji, które oszczędzają czas przy kartach pracy, prezentacjach i dyplomach.\n"
                        .'Darmowy webinar PNE. Link w bio / w komentarzu.',
                    'cta' => 'Dołącz na żywo',
                ],
                'risk' => 'Publikacja zewnętrzna nie jest uruchamiana. To tylko zatwierdzenie treści w prototypie.',
            ],
            [
                'id' => 'live-description',
                'type' => 'Opis live',
                'title' => 'Opis webinaru na stronie / w pokoju',
                'summary' => 'Krótki opis korzyści i programu spotkania.',
                'reason' => 'Uczestnik musi w 20 sekund zrozumieć, czy warto wejść na live.',
                'time_estimate' => 'ok. 2 min',
                'campaign_id' => $campaignId,
                'preview' => [
                    'headline' => 'Canva AI w pracy nauczyciela',
                    'body' => "Na spotkaniu przejdziemy przez:\n"
                        ."1) generowanie grafik do kart pracy,\n"
                        ."2) szybkie warianty materiałów dla różnych poziomów,\n"
                        ."3) dyplomy i podziękowania,\n"
                        ."4) typowe błędy przy promptach,\n"
                        .'5) bezpieczne użycie AI w szkole.',
                    'cta' => null,
                ],
                'risk' => 'Zmiana nie aktualizuje kursu w bazie. Operacyjnie opis nadal edytujesz w szkoleniu jak dotychczas.',
            ],
            [
                'id' => 'follow-up',
                'type' => 'Follow-up',
                'title' => 'Follow-up po webinarze (nagranie + CTA)',
                'summary' => 'Mail po spotkaniu: nagranie, checklista i delikatne CTA do szkolenia pogłębiającego.',
                'reason' => 'Największa wartość sprzedażowa powstaje po live, gdy uczestnik już zobaczył eksperckość.',
                'time_estimate' => 'ok. 4 min',
                'campaign_id' => $campaignId,
                'preview' => [
                    'headline' => 'Nagranie z Canva AI + 7 promptów do lekcji',
                    'body' => "Dziękujemy za udział.\n"
                        ."W załączniku / pod linkiem: nagranie i krótka checklista promptów.\n\n"
                        .'Jeśli chcesz przejść głębiej (szablony, workflow klasy, przykłady z Waszych przedmiotów) — otwieramy zapis na szkolenie pogłębiające.',
                    'cta' => 'Zobacz szkolenie pogłębiające',
                ],
                'risk' => 'CTA prowadziłoby docelowo do oferty/szkolenia. W prototypie nie ma wysyłki ani linku produkcyjnego.',
            ],
            [
                'id' => 'expert-talking-points',
                'type' => 'Brief eksperta',
                'title' => 'Punkty do omówienia przez eksperta',
                'summary' => 'Szkic briefu: ton, zakazy, przykłady „wolno / nie wolno”.',
                'reason' => 'Spójny język eksperta chroni markę PNE i skraca przygotowanie prowadzącego.',
                'time_estimate' => 'ok. 3 min',
                'campaign_id' => $campaignId,
                'preview' => [
                    'headline' => 'Brief: Canva AI — webinar TIK',
                    'body' => "Ton: praktyczny, spokojny, bez hype.\n"
                        ."Pokazuj przykłady szkolne, nie marketingowe.\n"
                        ."Unikaj obietnic „AI zrobi lekcję za Ciebie”.\n"
                        .'Zawsze wspomnij o odpowiedzialnym użyciu i weryfikacji treści.',
                    'cta' => null,
                ],
                'risk' => 'Brief nie jest wysyłany do eksperta automatycznie.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decision(string $id): ?array
    {
        foreach (self::decisions() as $decision) {
            if ($decision['id'] === $id) {
                return $decision;
            }
        }

        return null;
    }

    /**
     * @return list<array{title: string, meta: string, href: string}>
     */
    public static function attentionItems(): array
    {
        $pending = count(self::pendingDecisionIds());

        return [
            [
                'title' => 'Webinar TIK: Canva AI — decyzje przed startem',
                'meta' => $pending > 0
                    ? "Demonstracja · {$pending} do zatwierdzenia · 8.10.2026, 20:00"
                    : 'Demonstracja · wszystkie decyzje przejrzane w tej sesji',
                'href' => route('growth.inbox.index'),
            ],
            [
                'title' => 'Karta kampanii webinaru TIK',
                'meta' => 'Cel, odbiorcy, ekspert, plan formatów',
                'href' => route('growth.projects.index'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function pendingDecisionIds(): array
    {
        $done = session('growth_os.demo_decisions', []);

        if (! is_array($done)) {
            $done = [];
        }

        return collect(self::decisions())
            ->pluck('id')
            ->reject(fn (string $id) => isset($done[$id]))
            ->values()
            ->all();
    }

    public static function decisionStatus(string $id): ?string
    {
        $done = session('growth_os.demo_decisions', []);

        if (! is_array($done) || ! isset($done[$id]) || ! is_array($done[$id])) {
            return null;
        }

        $status = $done[$id]['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public static function markDecision(string $id, string $status): void
    {
        $done = session('growth_os.demo_decisions', []);
        if (! is_array($done)) {
            $done = [];
        }

        $done[$id] = [
            'status' => $status,
            'at' => now()->toIso8601String(),
        ];

        session(['growth_os.demo_decisions' => $done]);
    }
}

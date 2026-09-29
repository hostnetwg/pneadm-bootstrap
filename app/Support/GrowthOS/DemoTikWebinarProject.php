<?php

namespace App\Support\GrowthOS;

use Carbon\CarbonImmutable;

/**
 * Etap 0.3: sesyjny prototyp przygotowania webinaru TIK od zera.
 *
 * Brak bazy, API, publikacji i wysyłek. Cały stan żyje tylko w sesji HTTP.
 */
class DemoTikWebinarProject
{
    public const PROJECT_ID = 'tik-webinar-session';

    private const SESSION_PROJECT = 'growth_os.demo_tik_project';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function goals(): array
    {
        return [
            ['value' => 'education', 'label' => 'edukacja / wartość dla odbiorców'],
            ['value' => 'brand', 'label' => 'budowanie marki'],
            ['value' => 'community', 'label' => 'rozwój społeczności'],
            ['value' => 'audience', 'label' => 'pozyskanie nowych odbiorców'],
            ['value' => 'product', 'label' => 'wsparcie produktu'],
            ['value' => 'unknown', 'label' => 'jeszcze nie wiem'],
        ];
    }

    /**
     * @return list<array{id: string, label: string, anchor: string}>
     */
    public static function stages(): array
    {
        return [
            ['id' => 'direction', 'label' => 'Pomysł i kierunek', 'anchor' => 'direction'],
            ['id' => 'concept', 'label' => 'Koncepcja', 'anchor' => 'concept'],
            ['id' => 'materials', 'label' => 'Materiały', 'anchor' => 'materials'],
            ['id' => 'preparation', 'label' => 'Przygotowanie', 'anchor' => 'timeline'],
            ['id' => 'live', 'label' => 'LIVE', 'anchor' => 'timeline'],
            ['id' => 'follow_up', 'label' => 'Follow-up', 'anchor' => 'timeline'],
        ];
    }

    /**
     * @return list<array{title: string, why: string}>
     */
    public static function ideas(): array
    {
        return [
            [
                'title' => 'Canva AI w pracy nauczyciela',
                'why' => 'Nauczyciele często szukają prostych narzędzi do materiałów, kart pracy i prezentacji.',
            ],
            [
                'title' => 'AI bezpiecznie na lekcji i radzie pedagogicznej',
                'why' => 'Łączy aktualność AI z odpowiedzialnością szkoły i realnymi obawami dyrektorów.',
            ],
            [
                'title' => 'TIK, który oszczędza czas przed końcem semestru',
                'why' => 'Dobry temat operacyjny, gdy odbiorcy potrzebują szybkich rozwiązań, nie teorii.',
            ],
        ];
    }

    /**
     * @param  array{type: string, live_date: string, live_time: string, host: string, goal: string, topic?: string|null}  $data
     * @return array<string, mixed>
     */
    public static function createProject(array $data): array
    {
        $topic = trim((string) ($data['topic'] ?? ''));
        if ($topic === '') {
            $topic = self::ideas()[0]['title'];
        }

        $project = [
            'id' => self::PROJECT_ID,
            'type' => $data['type'],
            'live_date' => $data['live_date'],
            'live_time' => $data['live_time'],
            'host' => $data['host'],
            'goal' => $data['goal'],
            'topic' => $topic,
            'status' => 'PLANNING',
            'created_at' => now()->toIso8601String(),
            'completed_steps' => [],
            'direction' => [
                'why_now' => 'AI w narzędziach edukacyjnych szybko się zmienia, a nauczyciele potrzebują praktycznych przykładów bez technologicznego żargonu.',
                'audience' => 'Nauczyciele szkół podstawowych i ponadpodstawowych, dyrektorzy zainteresowani TIK.',
                'problem' => 'Jak przygotować lepsze materiały szybciej i bez poczucia, że trzeba być informatykiem.',
                'takeaway' => 'Uczestnik wychodzi z listą funkcji Canva AI i kilkoma gotowymi promptami do własnych lekcji.',
                'sell_later' => 'być może',
            ],
            'concept' => [
                'title' => $topic,
                'subtitle' => 'Praktyczny webinar TIK dla nauczycieli',
                'promise' => 'Pokażemy, jak wykorzystać Canva AI do szybszego tworzenia materiałów dydaktycznych.',
                'points' => [
                    '5 funkcji Canva AI, które realnie skracają przygotowanie materiałów.',
                    'Przykłady: karta pracy, prezentacja, dyplom, grafika do lekcji.',
                    'Typowe błędy w promptach i jak ich uniknąć.',
                    'Bezpieczne użycie AI w szkole.',
                ],
                'plan' => 'Wprowadzenie -> pokaz 5 funkcji -> mini case z lekcji -> pytania -> podsumowanie i CTA.',
                'cta' => 'Pobierz checklistę promptów i dołącz do kolejnego szkolenia pogłębiającego.',
                'lead_magnet' => 'PDF: 7 promptów Canva AI dla nauczyciela.',
                'next_product' => 'być może',
            ],
            'concept_versions' => [],
            'concept_ai_proposal' => null,
            'materials' => self::defaultMaterials(),
        ];

        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function project(): ?array
    {
        $project = session(self::SESSION_PROJECT);

        return is_array($project) ? $project : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireProject(string $id): array
    {
        $project = self::project();
        abort_if($project === null || ($project['id'] ?? null) !== $id, 404);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function saveProject(array $project): void
    {
        session([self::SESSION_PROJECT => $project]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function completeStep(string $projectId, string $step): array
    {
        $project = self::requireProject($projectId);
        abort_unless(in_array($step, ['direction', 'concept'], true), 404);

        $completed = $project['completed_steps'] ?? [];
        if (! is_array($completed)) {
            $completed = [];
        }

        $completed[$step] = now()->toIso8601String();
        $project['completed_steps'] = $completed;
        $project['status'] = $step === 'direction' ? 'PLANNING' : 'PREPARING';

        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reopenStep(string $projectId, string $step): array
    {
        $project = self::requireProject($projectId);
        abort_unless(in_array($step, ['direction', 'concept'], true), 404);

        $completed = $project['completed_steps'] ?? [];
        if (! is_array($completed)) {
            $completed = [];
        }

        unset($completed[$step]);
        $project['completed_steps'] = $completed;
        $project['status'] = isset($completed['concept']) ? 'PREPARING' : 'PLANNING';

        self::saveProject($project);

        return $project;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function conceptAiIntents(): array
    {
        return [
            ['value' => 'shorter', 'label' => 'Skróć i uprość'],
            ['value' => 'practical', 'label' => 'Bardziej praktycznie'],
            ['value' => 'directors', 'label' => 'Bardziej dla dyrektora'],
            ['value' => 'less_sales', 'label' => 'Mniej sprzedażowo'],
            ['value' => 'expand', 'label' => 'Rozbuduj program'],
        ];
    }

    /**
     * @param  array{
     *     title: string,
     *     subtitle: string,
     *     promise: string,
     *     points: list<string>|string,
     *     plan: string,
     *     cta: string,
     *     lead_magnet: string,
     *     next_product: string
     * }  $data
     * @return array<string, mixed>
     */
    public static function updateConcept(string $projectId, array $data, string $source = 'manual'): array
    {
        $project = self::requireProject($projectId);
        $concept = self::normalizeConcept($data);

        $project = self::pushConceptVersion($project, $source === 'manual' ? 'Edycja ręczna' : 'Zmiana koncepcji');
        $project['concept'] = $concept;

        if (isset($project['completed_steps']['concept'])) {
            unset($project['completed_steps']['concept']);
            $project['status'] = 'PLANNING';
        }

        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestConceptAiProposal(string $projectId, string $intent): array
    {
        $project = self::requireProject($projectId);
        abort_unless(in_array($intent, collect(self::conceptAiIntents())->pluck('value')->all(), true), 422);

        $current = $project['concept'] ?? [];
        $intentLabel = collect(self::conceptAiIntents())->firstWhere('value', $intent)['label'] ?? $intent;

        $project['concept_ai_proposal'] = [
            'intent' => $intent,
            'intent_label' => $intentLabel,
            'created_at' => now()->toIso8601String(),
            'note' => 'Symulowana propozycja AI. Nic nie zostało nadpisane — możesz przyjąć albo odrzucić.',
            'concept' => self::simulatedConceptProposal(is_array($current) ? $current : [], $intent),
        ];

        if (isset($project['completed_steps']['concept'])) {
            unset($project['completed_steps']['concept']);
            $project['status'] = 'PLANNING';
        }

        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    public static function applyConceptAiProposal(string $projectId): array
    {
        $project = self::requireProject($projectId);
        $proposal = $project['concept_ai_proposal'] ?? null;
        abort_if(! is_array($proposal) || ! isset($proposal['concept']) || ! is_array($proposal['concept']), 404);

        $project = self::pushConceptVersion(
            $project,
            'Zastosowano propozycję AI: '.($proposal['intent_label'] ?? 'zmiana')
        );
        $project['concept'] = self::normalizeConcept($proposal['concept']);
        $project['concept_ai_proposal'] = null;

        if (isset($project['completed_steps']['concept'])) {
            unset($project['completed_steps']['concept']);
            $project['status'] = 'PLANNING';
        }

        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rejectConceptAiProposal(string $projectId): array
    {
        $project = self::requireProject($projectId);
        $project['concept_ai_proposal'] = null;
        self::saveProject($project);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private static function pushConceptVersion(array $project, string $label): array
    {
        $versions = $project['concept_versions'] ?? [];
        if (! is_array($versions)) {
            $versions = [];
        }

        if (isset($project['concept']) && is_array($project['concept'])) {
            array_unshift($versions, [
                'label' => $label,
                'at' => now()->toIso8601String(),
                'concept' => $project['concept'],
            ]);
            $versions = array_slice($versions, 0, 5);
        }

        $project['concept_versions'] = $versions;

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     promise: string,
     *     points: list<string>,
     *     plan: string,
     *     cta: string,
     *     lead_magnet: string,
     *     next_product: string
     * }
     */
    private static function normalizeConcept(array $data): array
    {
        $points = $data['points'] ?? [];
        if (is_string($points)) {
            $points = preg_split("/\r\n|\n|\r/", $points) ?: [];
        }

        $points = collect(is_array($points) ? $points : [])
            ->map(fn ($point) => trim((string) $point))
            ->filter()
            ->values()
            ->all();

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'subtitle' => trim((string) ($data['subtitle'] ?? '')),
            'promise' => trim((string) ($data['promise'] ?? '')),
            'points' => $points,
            'plan' => trim((string) ($data['plan'] ?? '')),
            'cta' => trim((string) ($data['cta'] ?? '')),
            'lead_magnet' => trim((string) ($data['lead_magnet'] ?? '')),
            'next_product' => trim((string) ($data['next_product'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private static function simulatedConceptProposal(array $current, string $intent): array
    {
        $title = (string) ($current['title'] ?? 'Webinar TIK');
        $points = is_array($current['points'] ?? null) ? $current['points'] : [];

        return match ($intent) {
            'shorter' => [
                'title' => $title.' — w 45 minut',
                'subtitle' => 'Krótko, konkretnie, bez żargonu',
                'promise' => 'Jeden prosty workflow Canva AI, który oszczędza czas jeszcze dziś.',
                'points' => array_slice(array_values($points) ?: [
                    '1 funkcja, która daje największy zwrot czasu.',
                    'Przykład z karty pracy.',
                    'Co zrobić po webinarze w 10 minut.',
                ], 0, 3),
                'plan' => 'Intro 5 min -> pokaz 20 min -> pytania 10 min -> CTA 5 min.',
                'cta' => 'Pobierz krótką checklistę i wróć do lekcji.',
                'lead_magnet' => '1-stronicowa checklista promptów.',
                'next_product' => 'nie',
            ],
            'practical' => [
                'title' => str_contains($title, 'praktycznie') ? $title : $title.' — praktycznie',
                'subtitle' => 'Konkretne przykłady z lekcji, nie teoria AI',
                'promise' => 'Wyjdź z szablonami, które od razu użyjesz na jutrzejszej lekcji.',
                'points' => [
                    'Karta pracy: od pomysłu do gotowego pliku.',
                    'Prezentacja: szybkie warianty dla różnych poziomów.',
                    'Dyplom / podziękowanie bez ręcznej roboty.',
                    'Typowe błędy promptów i szybkie poprawki.',
                ],
                'plan' => 'Problem nauczyciela -> pokaz na żywo -> wspólna mini-praca -> checklista.',
                'cta' => 'Pobierz szablony i checklistę promptów.',
                'lead_magnet' => 'Pakiet 3 szablonów Canva AI + 7 promptów.',
                'next_product' => (string) ($current['next_product'] ?? 'być może'),
            ],
            'directors' => [
                'title' => 'Canva AI dla szkoły: bezpiecznie i z oszczędnością czasu',
                'subtitle' => 'Webinar TIK dla dyrektorów i nauczycieli wiodących',
                'promise' => 'Pokażemy, jak wdrożyć AI w szkole bez chaosu i bez ryzyka wizerunkowego.',
                'points' => [
                    'Gdzie AI realnie pomaga nauczycielom.',
                    'Jakie ryzyka warto wykluczyć od razu.',
                    'Jak mówić o AI na radzie pedagogicznej.',
                    'Prosty standard odpowiedzialnego użycia.',
                ],
                'plan' => 'Kontekst szkoły -> przykłady -> ryzyka -> rekomendowany standard -> pytania.',
                'cta' => 'Pobierz 1-stronicowy standard użycia AI w szkole.',
                'lead_magnet' => 'Mini-standard AI dla rady pedagogicznej.',
                'next_product' => 'być może',
            ],
            'less_sales' => [
                'title' => $title,
                'subtitle' => 'Wartość edukacyjna bez nacisku sprzedażowego',
                'promise' => 'Dostaniesz praktyczne narzędzia do lekcji — bez agresywnego CTA produktowego.',
                'points' => $points ?: [
                    'Praktyczne zastosowania Canva AI.',
                    'Bezpieczne użycie w szkole.',
                    'Materiały do samodzielnej pracy.',
                ],
                'plan' => (string) ($current['plan'] ?? 'Pokaz -> przykłady -> pytania -> podsumowanie.'),
                'cta' => 'Zostaw e-mail i odbierz checklistę po spotkaniu.',
                'lead_magnet' => (string) ($current['lead_magnet'] ?? 'Checklista promptów.'),
                'next_product' => 'nie',
            ],
            default => [
                'title' => $title,
                'subtitle' => (string) ($current['subtitle'] ?? 'Rozbudowany program webinaru TIK'),
                'promise' => (string) ($current['promise'] ?? 'Pokażemy praktyczne zastosowania Canva AI.'),
                'points' => array_values(array_unique(array_merge($points, [
                    'Mini-workshop: prompt -> wynik -> poprawka.',
                    'Jak przygotować materiał na 2 poziomy trudności.',
                ]))),
                'plan' => 'Wprowadzenie -> pokaz 5 funkcji -> mini-workshop -> Q&A -> follow-up.',
                'cta' => (string) ($current['cta'] ?? 'Pobierz materiały po webinarze.'),
                'lead_magnet' => (string) ($current['lead_magnet'] ?? 'PDF z promptami.'),
                'next_product' => (string) ($current['next_product'] ?? 'być może'),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function material(string $projectId, string $materialId): array
    {
        $project = self::requireProject($projectId);

        foreach ($project['materials'] ?? [] as $material) {
            if (($material['id'] ?? null) === $materialId) {
                return $material;
            }
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateMaterialStatus(string $projectId, string $materialId, string $status): array
    {
        $project = self::requireProject($projectId);
        abort_unless(array_key_exists($status, self::materialStatusLabels()), 422);

        foreach ($project['materials'] as $index => $material) {
            if (($material['id'] ?? null) === $materialId) {
                $project['materials'][$index]['status'] = $status;
                $project['materials'][$index]['updated_at'] = now()->toIso8601String();
                self::saveProject($project);

                return $project['materials'][$index];
            }
        }

        abort(404);
    }

    /**
     * @return list<array<string, string>>
     */
    public static function inboxItems(): array
    {
        $project = self::project();
        if ($project === null) {
            return [];
        }

        $items = [];
        $completed = $project['completed_steps'] ?? [];

        if (! isset($completed['direction'])) {
            $items[] = [
                'type' => 'Kierunek',
                'title' => 'Pomysł i kierunek webinaru',
                'summary' => 'Zatwierdź temat, odbiorców, problem i wartość dla uczestnika.',
                'status' => 'Do decyzji',
                'href' => route('growth.projects.show', $project['id']).'#direction',
            ];
        } elseif (is_array($project['concept_ai_proposal'] ?? null)) {
            $items[] = [
                'type' => 'Koncepcja',
                'title' => 'Propozycja AI do koncepcji',
                'summary' => 'AI przygotowało wariant. Przyjmij albo odrzuć — bez automatycznego nadpisania.',
                'status' => 'Do decyzji',
                'href' => route('growth.projects.show', $project['id']).'#concept',
            ];
        } elseif (! isset($completed['concept'])) {
            $items[] = [
                'type' => 'Koncepcja',
                'title' => 'Koncepcja webinaru',
                'summary' => 'Edytuj, poproś AI o wariant albo zatwierdź koncepcję.',
                'status' => 'Do decyzji',
                'href' => route('growth.projects.show', $project['id']).'#concept',
            ];
        }

        foreach ($project['materials'] ?? [] as $material) {
            if (($material['status'] ?? null) !== 'REVIEW') {
                continue;
            }

            $items[] = [
                'type' => $material['kind'] ?? 'Materiał',
                'title' => $material['name'],
                'summary' => $material['summary'],
                'status' => 'Do sprawdzenia',
                'href' => route('growth.projects.materials.show', [$project['id'], $material['id']]),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>|null  $project
     * @return array{label: string, href: string, meta: string}
     */
    public static function nextAction(?array $project): array
    {
        if ($project === null) {
            return [
                'label' => 'Zaplanuj webinar TIK',
                'href' => route('growth.projects.create'),
                'meta' => 'Zacznij od daty, prowadzącego, celu i tematu.',
            ];
        }

        $completed = $project['completed_steps'] ?? [];

        if (! isset($completed['direction'])) {
            return [
                'label' => 'Następny krok: zatwierdź kierunek',
                'href' => route('growth.projects.show', $project['id']).'#direction',
                'meta' => 'Najpierw ustal temat, odbiorcę, problem i wartość dla uczestnika.',
            ];
        }

        if (is_array($project['concept_ai_proposal'] ?? null)) {
            return [
                'label' => 'Następny krok: oceń propozycję AI',
                'href' => route('growth.projects.show', $project['id']).'#concept',
                'meta' => 'AI przygotowało wariant koncepcji. Przyjmij albo odrzuć — bez nadpisywania automatycznego.',
            ];
        }

        if (! isset($completed['concept'])) {
            return [
                'label' => 'Następny krok: dopracuj koncepcję',
                'href' => route('growth.projects.show', $project['id']).'#concept',
                'meta' => 'Edytuj ręcznie albo poproś AI o wariant, potem oznacz koncepcję jako gotową.',
            ];
        }

        foreach ($project['materials'] ?? [] as $material) {
            if (($material['status'] ?? null) === 'REVIEW') {
                return [
                    'label' => 'Następny krok: sprawdź '.$material['name'],
                    'href' => route('growth.projects.materials.show', [$project['id'], $material['id']]),
                    'meta' => 'Ten materiał czeka na Twoją decyzję.',
                ];
            }
        }

        foreach ($project['materials'] ?? [] as $material) {
            if (! in_array(($material['status'] ?? null), ['APPROVED', 'PUBLISHED'], true)) {
                return [
                    'label' => 'Następny krok: przygotuj '.$material['name'],
                    'href' => route('growth.projects.materials.show', [$project['id'], $material['id']]),
                    'meta' => 'Materiały prowadzą projekt do gotowości przed live.',
                ];
            }
        }

        return [
            'label' => 'Następny krok: przygotuj LIVE',
            'href' => route('growth.projects.show', $project['id']).'#timeline',
            'meta' => 'Materiały są gotowe w prototypie. Sprawdź checklistę czasową.',
        ];
    }

    /**
     * @return list<array{period: string, items: list<string>}>
     */
    public static function timeline(): array
    {
        return [
            ['period' => 'T-7 dni', 'items' => ['temat', 'kierunek', 'koncepcja']],
            ['period' => 'T-5 dni', 'items' => ['YouTube Live', 'grafika', 'landing']],
            ['period' => 'T-3 dni', 'items' => ['mailing główny', 'Facebook']],
            ['period' => 'T-1 dzień', 'items' => ['scenariusz', 'materiały', 'test techniczny']],
            ['period' => 'T-3 godziny', 'items' => ['przypomnienie', 'social reminder']],
            ['period' => 'LIVE', 'items' => ['webinar', 'formularz zaświadczenia']],
            ['period' => 'T+1', 'items' => ['nagranie', 'transkrypcja', 'zagadnienia do zaświadczenia']],
            ['period' => 'T+2 / T+3', 'items' => ['follow-up', 'content repurposing']],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $project
     * @return array{days_label: string, critical_label: string, critical_count: int}
     */
    public static function projectHealth(?array $project): array
    {
        if ($project === null) {
            return [
                'days_label' => 'Nie ma jeszcze zaplanowanego webinaru TIK.',
                'critical_label' => 'Utwórz pierwszy projekt, żeby zobaczyć checklistę.',
                'critical_count' => 0,
            ];
        }

        $liveAt = CarbonImmutable::parse($project['live_date'].' '.$project['live_time']);
        $days = (int) now()->startOfDay()->diffInDays($liveAt->startOfDay(), false);
        $criticalMaterials = collect($project['materials'] ?? [])
            ->whereIn('id', ['landing', 'reminder-mail', 'host-script'])
            ->reject(fn (array $material) => in_array($material['status'], ['APPROVED', 'PUBLISHED'], true))
            ->count();

        $daysLabel = match (true) {
            $days < 0 => 'Webinar jest po terminie w tym prototypie.',
            $days === 0 => 'Webinar jest dzisiaj.',
            $days === 1 => 'Do webinaru został 1 dzień.',
            default => "Do webinaru zostało {$days} dni.",
        };

        $criticalLabel = $criticalMaterials === 0
            ? 'Elementy krytyczne są gotowe w tej sesji.'
            : "{$criticalMaterials} elementy krytyczne nie są gotowe.";

        return [
            'days_label' => $daysLabel,
            'critical_label' => $criticalLabel,
            'critical_count' => $criticalMaterials,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function projectStatusLabels(): array
    {
        return [
            'DRAFT' => 'Szkic',
            'PLANNING' => 'Planowanie',
            'PREPARING' => 'Przygotowanie',
            'READY' => 'Gotowy',
            'SCHEDULED' => 'Zaplanowany',
            'LIVE' => 'LIVE',
            'FOLLOW_UP' => 'Follow-up',
            'COMPLETED' => 'Zakończony',
            'PAUSED' => 'Wstrzymany',
            'CANCELLED' => 'Anulowany',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function materialStatusLabels(): array
    {
        return [
            'NOT_STARTED' => 'Nie rozpoczęto',
            'DRAFT' => 'Draft',
            'REVIEW' => 'Do sprawdzenia',
            'APPROVED' => 'Zatwierdzone',
            'PUBLISHED' => 'Opublikowane / zaplanowane',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function materialTemplate(
        string $id,
        string $name,
        string $kind,
        string $status,
        string $summary,
        string $draft
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'kind' => $kind,
            'status' => $status,
            'summary' => $summary,
            'draft' => $draft,
            'why' => 'To element przygotowania webinaru. W prototypie nic nie jest publikowane ani wysyłane.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function defaultMaterials(): array
    {
        return [
            self::materialTemplate('youtube-description', 'Opis YouTube', 'Opis live', 'REVIEW', 'Krótki opis korzyści i programu spotkania.', 'Canva AI w pracy nauczyciela - praktyczny webinar TIK dla nauczycieli.'),
            self::materialTemplate('main-graphic', 'Grafika główna', 'Grafika', 'DRAFT', 'Nagłówek i kierunek grafiki do promocji.', 'Środa 20:00 - Canva AI dla nauczycieli - praktycznie i spokojnie.'),
            self::materialTemplate('facebook-post', 'Post Facebook', 'Social', 'DRAFT', 'Post zapowiadający webinar.', '5 funkcji Canva AI, które oszczędzają czas przy kartach pracy i prezentacjach.'),
            self::materialTemplate('main-mail', 'Mailing główny', 'Mailing', 'NOT_STARTED', 'Główny mailing zapraszający.', 'Wersja edukacyjna bez agresywnej sprzedaży, z jasnym CTA do zapisu.'),
            self::materialTemplate('reminder-mail', 'Mailing przypominający', 'Mailing', 'NOT_STARTED', 'Przypomnienie dzień przed live.', 'Krótki mail: start jutro, co uczestnik wyniesie, link do pokoju.'),
            self::materialTemplate('landing', 'Formularz zapisu / landing', 'Landing', 'NOT_STARTED', 'Strona lub formularz zapisu.', 'Nagłówek, obietnica, 3 punkty programu, prowadzący, data i CTA.'),
            self::materialTemplate('host-script', 'Scenariusz prowadzącego', 'Scenariusz', 'DRAFT', 'Plan prowadzenia webinaru.', 'Intro, przejścia między sekcjami, pytania do publiczności, końcowe CTA.'),
            self::materialTemplate('participant-material', 'Materiał dla uczestnika', 'Materiał', 'NOT_STARTED', 'PDF lub checklista po webinarze.', '7 promptów Canva AI dla nauczyciela i miejsce na własne notatki.'),
            self::materialTemplate('obs-intro', 'Intro OBS', 'Techniczne', 'NOT_STARTED', 'Plansza lub intro do live.', 'Krótka plansza startowa z tytułem, datą i marką PNE.'),
            self::materialTemplate('follow-up', 'Follow-up', 'Mailing', 'NOT_STARTED', 'Mail po webinarze.', 'Nagranie, materiał dodatkowy, najważniejsze wnioski i delikatne CTA.'),
        ];
    }
}

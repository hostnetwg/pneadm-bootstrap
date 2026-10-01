<?php

namespace App\Support\GrowthOS;

use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\User;
use App\Services\GrowthOS\AI\Data\ConceptRevisionResult;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\GrowthOperationalTasks;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use Carbon\CarbonImmutable;

/**
 * Etap 0.3: sesyjny prototyp przygotowania webinaru TIK od zera.
 *
 * Kampania, prowadzący, kierunek, koncepcja i dziesięć materiałów roboczych są zapisywane w bazie. Propozycja AI żyje w sesji HTTP.
 */
class DemoTikWebinarProject
{
    public const PROJECT_ID = 'tik-webinar-session';

    public const SESSION_PROJECT = 'growth_os.demo_tik_project';

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

        $project = self::freshWorkspace([
            'type' => $data['type'],
            'live_date' => $data['live_date'],
            'live_time' => $data['live_time'],
            'host' => $data['host'],
            'goal' => $data['goal'],
            'topic' => $topic,
            'status' => 'PLANNING',
        ]);

        $user = auth()->user();
        if ($user instanceof User) {
            $project = app(GrowthSessionConceptStore::class)->createCampaign($project, $user);
        }

        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateHost(string $projectId, string $host): array
    {
        $project = self::requireProject($projectId);
        $project['host'] = trim($host);
        self::saveProject($project);
        app(GrowthSessionConceptStore::class)->persistHost($project, $project['host']);

        return $project;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function project(): ?array
    {
        $project = session(self::SESSION_PROJECT);
        if (is_array($project)) {
            return app(GrowthSessionConceptStore::class)->overlay($project);
        }

        return self::restoreOwnedProject();
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
        app(GrowthSessionConceptStore::class)->syncStatus($project);
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
        if ($step === 'direction') {
            self::persistDirection($project);
            self::recordConceptDecision(
                $project,
                GrowthSessionConceptStore::DECISION_DIRECTION_APPROVAL,
                GrowthDecision::STATUS_APPROVED,
                'Czy kierunek webinaru jest gotowy?',
                'Kierunek zatwierdzony.',
                [],
                GrowthSessionConceptStore::DIRECTION_KEY,
            );
        }
        if ($step === 'concept') {
            self::recordConceptDecision(
                $project,
                GrowthSessionConceptStore::DECISION_CONCEPT_APPROVAL,
                GrowthDecision::STATUS_APPROVED,
                'Czy koncepcja webinaru jest gotowa?',
                'Koncepcja oznaczona jako gotowa.',
            );
        }

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

        if ($step === 'direction') {
            self::supersedeDirectionApproval($project);
        }
        if ($step === 'concept') {
            self::supersedeConceptApproval($project);
        }

        self::saveProject($project);
        if ($step === 'direction') {
            self::recordConceptDecision(
                $project,
                GrowthSessionConceptStore::DECISION_DIRECTION_APPROVAL,
                GrowthDecision::STATUS_CHANGES_REQUESTED,
                'Czy cofnąć zatwierdzenie kierunku?',
                'Cofnięto zatwierdzenie kierunku.',
                [],
                GrowthSessionConceptStore::DIRECTION_KEY,
            );
        }
        if ($step === 'concept') {
            self::recordConceptDecision(
                $project,
                GrowthSessionConceptStore::DECISION_CONCEPT_APPROVAL,
                GrowthDecision::STATUS_CHANGES_REQUESTED,
                'Czy cofnąć zatwierdzenie koncepcji?',
                'Cofnięto zatwierdzenie koncepcji.',
            );
        }

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
            self::supersedeConceptApproval($project);
        }

        self::persistConcept($project);
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
            self::supersedeConceptApproval($project);
        }

        self::saveProject($project);

        return $project;
    }

    /**
     * Store a validated real-AI proposal without changing the current concept.
     *
     * @return array<string, mixed>
     */
    public static function storeConceptAiProposal(
        string $projectId,
        string $intent,
        string $intentLabel,
        ConceptRevisionResult $result,
    ): array {
        $project = self::requireProject($projectId);
        $current = is_array($project['concept'] ?? null) ? $project['concept'] : [];

        $project['concept_ai_proposal'] = [
            'intent' => $intent,
            'intent_label' => $intentLabel,
            'created_at' => now()->toIso8601String(),
            'note' => 'Propozycja prawdziwego AI. Bieżąca koncepcja pozostaje bez zmian do chwili wybrania „Zastosuj”.',
            'source' => 'real_ai',
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'schema_version' => $result->schemaVersion,
            'request_id' => $result->requestId,
            'changed_fields' => $result->changedFields,
            'change_summary' => $result->changeSummary,
            'concept' => [
                ...$result->concept,
                'next_product' => (string) ($current['next_product'] ?? ''),
            ],
        ];

        if (isset($project['completed_steps']['concept'])) {
            unset($project['completed_steps']['concept']);
            $project['status'] = 'PLANNING';
            self::supersedeConceptApproval($project);
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

        if (isset($proposal['concept']['audience'])) {
            $project['direction']['audience'] = trim((string) $proposal['concept']['audience']);
            if (isset($project['completed_steps']['direction'])) {
                unset($project['completed_steps']['direction']);
                self::supersedeDirectionApproval($project);
            }
            self::persistDirection($project);
        }

        $project['concept'] = self::normalizeConcept($proposal['concept']);
        $project['concept_ai_proposal'] = null;

        if (isset($project['completed_steps']['concept'])) {
            unset($project['completed_steps']['concept']);
            $project['status'] = 'PLANNING';
            self::supersedeConceptApproval($project);
        }

        self::persistConcept($project);
        self::saveProject($project);
        self::recordConceptDecision(
            $project,
            GrowthSessionConceptStore::DECISION_CONCEPT_AI_APPLY,
            GrowthDecision::STATUS_APPROVED,
            'Czy zastosować propozycję AI?',
            'Zastosowano propozycję AI: '.($proposal['intent_label'] ?? 'zmiana'),
            [
                'intent' => (string) ($proposal['intent'] ?? ''),
                'intent_label' => (string) ($proposal['intent_label'] ?? ''),
            ],
        );

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rejectConceptAiProposal(string $projectId): array
    {
        $project = self::requireProject($projectId);
        $proposal = $project['concept_ai_proposal'] ?? null;
        $project['concept_ai_proposal'] = null;
        self::saveProject($project);
        self::recordConceptDecision(
            $project,
            GrowthSessionConceptStore::DECISION_CONCEPT_AI_REJECT,
            GrowthDecision::STATUS_REJECTED,
            'Czy odrzucić propozycję AI?',
            'Odrzucono propozycję AI: '.(is_array($proposal) ? ($proposal['intent_label'] ?? 'zmiana') : 'zmiana'),
            [
                'intent' => is_array($proposal) ? (string) ($proposal['intent'] ?? '') : '',
                'intent_label' => is_array($proposal) ? (string) ($proposal['intent_label'] ?? '') : '',
            ],
        );

        return $project;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function restoreOwnedProject(): ?array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $campaign = app(GrowthSessionConceptStore::class)->latestOwnedCampaign($user);
        if (! $campaign instanceof GrowthCampaign) {
            return null;
        }

        $topic = trim((string) ($campaign->working_topic ?: $campaign->name));
        $project = self::freshWorkspace([
            'type' => $campaign->type,
            'live_date' => $campaign->live_at?->toDateString() ?? now()->toDateString(),
            'live_time' => $campaign->live_at?->format('H:i') ?? '20:00',
            'host' => '—',
            'goal' => $campaign->goal ?: 'unknown',
            'topic' => $topic !== '' ? $topic : self::ideas()[0]['title'],
            'status' => 'PLANNING',
            'growth_campaign_id' => $campaign->id,
        ]);
        $project = app(GrowthSessionConceptStore::class)->overlay($project);
        session([self::SESSION_PROJECT => $project]);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function freshWorkspace(array $fields): array
    {
        $topic = trim((string) ($fields['topic'] ?? ''));

        return [
            'id' => self::PROJECT_ID,
            'type' => $fields['type'] ?? 'Webinar TIK',
            'live_date' => $fields['live_date'] ?? now()->toDateString(),
            'live_time' => $fields['live_time'] ?? '20:00',
            'host' => $fields['host'] ?? '—',
            'goal' => $fields['goal'] ?? 'unknown',
            'topic' => $topic,
            'status' => $fields['status'] ?? 'PLANNING',
            'growth_campaign_id' => $fields['growth_campaign_id'] ?? null,
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
            'material_ai_proposal' => null,
            'materials' => self::defaultMaterials(),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $meta
     */
    private static function recordConceptDecision(
        array $project,
        string $type,
        string $status,
        string $question,
        string $decision,
        array $meta = [],
        ?string $artifactKey = null,
    ): void {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        app(GrowthSessionConceptStore::class)->recordConceptDecision(
            $project,
            $user,
            $type,
            $status,
            $question,
            $decision,
            $meta,
            $artifactKey,
        );
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function persistDirection(array $project): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        app(GrowthSessionConceptStore::class)->persistDirection($project, $user);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function supersedeDirectionApproval(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        app(GrowthSessionConceptStore::class)->supersedeApprovedDirection((int) $campaignId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function updateDirection(string $projectId, array $data): array
    {
        $project = self::requireProject($projectId);
        $direction = $project['direction'] ?? [];
        if (! is_array($direction)) {
            $direction = [];
        }

        $direction['why_now'] = trim((string) ($data['why_now'] ?? ''));
        $direction['audience'] = trim((string) ($data['audience'] ?? ''));
        $direction['problem'] = trim((string) ($data['problem'] ?? ''));
        $direction['takeaway'] = trim((string) ($data['takeaway'] ?? ''));
        $direction['sell_later'] = trim((string) ($data['sell_later'] ?? ''));
        $project['direction'] = $direction;

        if (isset($project['completed_steps']['direction'])) {
            unset($project['completed_steps']['direction']);
            self::supersedeDirectionApproval($project);
        }

        self::persistDirection($project);
        self::saveProject($project);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function supersedeConceptApproval(array $project): void
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return;
        }

        app(GrowthSessionConceptStore::class)->supersedeApprovedConcept((int) $campaignId);
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<array{decision: string, status: string, decided_at: string, actor: string}>
     */
    public static function conceptDecisions(array $project): array
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return [];
        }

        return GrowthDecision::query()
            ->with('decidedBy')
            ->where('growth_campaign_id', (int) $campaignId)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (GrowthDecision $decision): array {
                return [
                    'decision' => (string) $decision->decision,
                    'status' => $decision->status,
                    'decided_at' => $decision->decided_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '',
                    'actor' => (string) ($decision->decidedBy?->name ?? ''),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function persistConcept(array $project): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        app(GrowthSessionConceptStore::class)->persistConcept($project, $user);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function persistMaterial(array $project, string $materialId): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        app(GrowthSessionConceptStore::class)->persistMaterial($project, $user, $materialId);
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
    public static function updateMaterialStatus(string $projectId, string $materialId, string $status, ?string $draft = null): array
    {
        $project = self::requireProject($projectId);
        abort_unless(array_key_exists($status, self::materialStatusLabels()), 422);

        foreach ($project['materials'] as $index => $material) {
            if (($material['id'] ?? null) === $materialId) {
                $project['materials'][$index]['status'] = $status;
                if ($draft !== null) {
                    $project['materials'][$index]['draft'] = $draft;
                }
                $project['materials'][$index]['updated_at'] = now()->toIso8601String();
                self::saveProject($project);
                self::persistMaterial($project, $materialId);

                return $project['materials'][$index];
            }
        }

        abort(404);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function canDraftMaterialWithAi(array $project): bool
    {
        $completed = is_array($project['completed_steps'] ?? null) ? $project['completed_steps'] : [];

        return isset($completed['direction'], $completed['concept']);
    }

    /**
     * Allow-listed context for the AI material draft. No host, no other materials, no personal data.
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    public static function materialAiContext(array $project, string $materialId, bool $emojis = true, string $instruction = ''): array
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];
        $goal = (string) ($project['goal'] ?? '');

        return [
            'campaign' => [
                'working_topic' => (string) ($project['topic'] ?? ''),
                'goal' => (string) (collect(self::goals())->firstWhere('value', $goal)['label'] ?? $goal),
                'live_date' => (string) ($project['live_date'] ?? ''),
                'live_time' => (string) ($project['live_time'] ?? ''),
                'timezone' => (string) config('app.timezone'),
                'host_name' => self::aiHostName($project),
            ],
            'direction' => self::fingerprintDirection($direction),
            'concept' => self::fingerprintConcept($concept),
            'current_draft' => self::materialDraft($project, $materialId),
            'style' => ['emojis' => $emojis],
            'instruction' => trim($instruction),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array{direction: string, concept: string, material: string, host: string}
     */
    public static function materialAiFingerprint(array $project, string $materialId): array
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];

        return [
            'direction' => self::hash(self::fingerprintDirection($direction)),
            'concept' => self::hash(self::fingerprintConcept($concept)),
            'material' => self::hash(['draft' => self::materialDraft($project, $materialId)]),
            'host' => self::hash(['host_name' => self::aiHostName($project)]),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function aiHostName(array $project): string
    {
        $host = trim((string) ($project['host'] ?? ''));

        return $host === '—' ? '' : $host;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestMaterialAiProposal(
        string $projectId,
        string $materialId,
        bool $emojis = true,
        string $instruction = '',
    ): array {
        $project = self::requireProject($projectId);
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];
        $instruction = trim($instruction);

        $project['material_ai_proposal'] = [
            'material_key' => $materialId,
            'draft' => self::simulatedYoutubeDescription($project, $concept, $emojis),
            'change_summary' => 'Symulacja lokalna: szkic złożony z zatwierdzonej koncepcji (tytuł, termin, obietnica, program, CTA).'
                .($instruction !== '' ? ' Symulacja nie interpretuje dodatkowej instrukcji — uwzględni ją prawdziwe AI.' : ''),
            'instruction' => $instruction,
            'source' => 'simulation',
            'provider' => null,
            'model' => null,
            'prompt_version' => null,
            'schema_version' => null,
            'fingerprint' => self::materialAiFingerprint($project, $materialId),
            'created_at' => now()->toIso8601String(),
            'note' => 'Symulowana propozycja AI. Obecny szkic pozostaje bez zmian do chwili wybrania „Zastosuj”.',
        ];

        self::saveProject($project);

        return $project;
    }

    /**
     * Store a validated real-AI draft without changing the current material.
     *
     * @return array<string, mixed>
     */
    public static function storeMaterialAiProposal(
        string $projectId,
        string $materialId,
        MaterialDraftResult $result,
        string $instruction = '',
    ): array {
        $project = self::requireProject($projectId);

        $project['material_ai_proposal'] = [
            'material_key' => $materialId,
            'draft' => $result->draft,
            'change_summary' => $result->changeSummary,
            'instruction' => trim($instruction),
            'source' => 'real_ai',
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'schema_version' => $result->schemaVersion,
            'fingerprint' => self::materialAiFingerprint($project, $materialId),
            'created_at' => now()->toIso8601String(),
            'note' => 'Propozycja prawdziwego AI. Obecny szkic pozostaje bez zmian do chwili wybrania „Zastosuj”.',
        ];

        self::saveProject($project);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>|null
     */
    public static function materialAiProposal(array $project, string $materialId): ?array
    {
        $proposal = $project['material_ai_proposal'] ?? null;

        return is_array($proposal) && ($proposal['material_key'] ?? null) === $materialId ? $proposal : null;
    }

    /**
     * @return array{ok: bool, project: array<string, mixed>}
     */
    public static function applyMaterialAiProposal(string $projectId, string $materialId): array
    {
        $project = self::requireProject($projectId);
        $proposal = self::materialAiProposal($project, $materialId);
        abort_if($proposal === null, 404);

        if (! self::canDraftMaterialWithAi($project)
            || ($proposal['fingerprint'] ?? null) !== self::materialAiFingerprint($project, $materialId)) {
            $project['material_ai_proposal'] = null;
            self::saveProject($project);

            return ['ok' => false, 'project' => $project];
        }

        foreach ($project['materials'] as $index => $material) {
            if (($material['id'] ?? null) === $materialId) {
                $project['materials'][$index]['draft'] = (string) $proposal['draft'];
                $project['materials'][$index]['status'] = 'DRAFT';
                $project['materials'][$index]['updated_at'] = now()->toIso8601String();
            }
        }

        $project['material_ai_proposal'] = null;
        self::saveProject($project);
        self::persistMaterial($project, $materialId);
        self::recordConceptDecision(
            $project,
            GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY,
            GrowthDecision::STATUS_APPROVED,
            'Czy zastosować szkic AI materiału?',
            'Zastosowano szkic AI: '.self::materialName($project, $materialId),
            self::materialAiDecisionMeta($proposal),
            $materialId,
        );

        return ['ok' => true, 'project' => $project];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rejectMaterialAiProposal(string $projectId, string $materialId): array
    {
        $project = self::requireProject($projectId);
        $proposal = self::materialAiProposal($project, $materialId);
        abort_if($proposal === null, 404);

        $project['material_ai_proposal'] = null;
        self::saveProject($project);
        self::recordConceptDecision(
            $project,
            GrowthSessionConceptStore::DECISION_MATERIAL_AI_REJECT,
            GrowthDecision::STATUS_REJECTED,
            'Czy odrzucić szkic AI materiału?',
            'Odrzucono szkic AI: '.self::materialName($project, $materialId),
            self::materialAiDecisionMeta($proposal),
            $materialId,
        );

        return $project;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array{material_key: string, prompt_version: string, source: string}
     */
    private static function materialAiDecisionMeta(array $proposal): array
    {
        return [
            'material_key' => (string) ($proposal['material_key'] ?? ''),
            'prompt_version' => (string) ($proposal['prompt_version'] ?? ''),
            'source' => (string) ($proposal['source'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function materialName(array $project, string $materialId): string
    {
        foreach ($project['materials'] ?? [] as $material) {
            if (($material['id'] ?? null) === $materialId) {
                return (string) ($material['name'] ?? $materialId);
            }
        }

        return $materialId;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function materialDraft(array $project, string $materialId): string
    {
        foreach ($project['materials'] ?? [] as $material) {
            if (($material['id'] ?? null) === $materialId) {
                return trim((string) ($material['draft'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $direction
     * @return array<string, string>
     */
    private static function fingerprintDirection(array $direction): array
    {
        return [
            'why_now' => trim((string) ($direction['why_now'] ?? '')),
            'audience' => trim((string) ($direction['audience'] ?? '')),
            'problem' => trim((string) ($direction['problem'] ?? '')),
            'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
            'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private static function fingerprintConcept(array $concept): array
    {
        $points = $concept['points'] ?? [];

        return [
            'title' => trim((string) ($concept['title'] ?? '')),
            'subtitle' => trim((string) ($concept['subtitle'] ?? '')),
            'promise' => trim((string) ($concept['promise'] ?? '')),
            'points' => array_values(array_map(
                static fn (mixed $point): string => trim((string) $point),
                is_array($points) ? $points : [],
            )),
            'plan' => trim((string) ($concept['plan'] ?? '')),
            'cta' => trim((string) ($concept['cta'] ?? '')),
            'additional_material' => trim((string) ($concept['lead_magnet'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $concept
     */
    private static function simulatedYoutubeDescription(array $project, array $concept, bool $emojis = true): string
    {
        $icon = static fn (string $emoji): string => $emojis ? $emoji.' ' : '';
        $bullet = $emojis ? '✅ ' : '• ';

        $points = collect(is_array($concept['points'] ?? null) ? $concept['points'] : [])
            ->map(fn (mixed $point): string => trim((string) $point))
            ->filter(fn (string $point): bool => $point !== '')
            ->map(fn (string $point): string => $bullet.$point)
            ->implode("\n");

        $title = trim((string) ($concept['title'] ?? ''));
        $cta = trim((string) ($concept['cta'] ?? ''));
        $host = self::aiHostName($project);

        return trim(implode("\n\n", array_filter([
            $title !== '' ? $icon('🎓').$title : '',
            trim((string) ($concept['subtitle'] ?? '')),
            $icon('📅').'Termin: '.($project['live_date'] ?? '').', godz. '.($project['live_time'] ?? '').'.',
            $host !== '' ? $icon('🎤').'Prowadzący: '.$host : '',
            trim((string) ($concept['promise'] ?? '')),
            $points !== '' ? $icon('📌')."Program:\n".$points : '',
            $cta !== '' ? $icon('👉').$cta : '',
        ])));
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
     * @param  array<string, mixed>  $project
     * @return array<string, \App\Models\GrowthOS\GrowthTask>
     */
    public static function operationalTasks(array $project): array
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return [];
        }

        return \App\Models\GrowthOS\GrowthTask::query()
            ->where('growth_campaign_id', (int) $campaignId)
            ->whereIn('key', GrowthOperationalTasks::keys())
            ->get()
            ->keyBy('key')
            ->all();
    }

    /**
     * @return list<array{period: string, items: list<array{label: string, task_key: string|null}>}>
     */
    public static function timeline(): array
    {
        return [
            ['period' => 'T-7 dni', 'items' => [
                self::timelineItem('temat'),
                self::timelineItem('kierunek'),
                self::timelineItem('koncepcja'),
            ]],
            ['period' => 'T-5 dni', 'items' => [
                self::timelineItem('YouTube Live', GrowthOperationalTasks::KEY_YOUTUBE_LIVE),
                self::timelineItem('grafika'),
                self::timelineItem('landing'),
            ]],
            ['period' => 'T-3 dni', 'items' => [
                self::timelineItem('mailing główny'),
                self::timelineItem('Facebook'),
            ]],
            ['period' => 'T-1 dzień', 'items' => [
                self::timelineItem('scenariusz'),
                self::timelineItem('materiały'),
                self::timelineItem('Test techniczny', GrowthOperationalTasks::KEY_TECHNICAL_TEST),
            ]],
            ['period' => 'T-3 godziny', 'items' => [
                self::timelineItem('przypomnienie'),
                self::timelineItem('Social reminder', GrowthOperationalTasks::KEY_SOCIAL_REMINDER),
            ]],
            ['period' => 'LIVE', 'items' => [
                self::timelineItem('Webinar', GrowthOperationalTasks::KEY_LIVE_WEBINAR),
                self::timelineItem('Formularz zaświadczenia', GrowthOperationalTasks::KEY_CERTIFICATE_FORM),
            ]],
            ['period' => 'T+1', 'items' => [
                self::timelineItem('Nagranie', GrowthOperationalTasks::KEY_RECORDING),
                self::timelineItem('Transkrypcja', GrowthOperationalTasks::KEY_TRANSCRIPTION),
                self::timelineItem('Zagadnienia do zaświadczenia', GrowthOperationalTasks::KEY_CERTIFICATE_TOPICS),
            ]],
            ['period' => 'T+2 / T+3', 'items' => [
                self::timelineItem('follow-up'),
                self::timelineItem('Content repurposing', GrowthOperationalTasks::KEY_CONTENT_REPURPOSING),
            ]],
        ];
    }

    /**
     * @return array{label: string, task_key: string|null}
     */
    private static function timelineItem(string $label, ?string $taskKey = null): array
    {
        return [
            'label' => $label,
            'task_key' => $taskKey,
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
            ? 'Elementy krytyczne są gotowe.'
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
     * @return array<string, string>
     */
    public static function materialStatusBadgeClasses(): array
    {
        return [
            'NOT_STARTED' => 'bg-light text-secondary border',
            'DRAFT' => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
            'REVIEW' => 'text-bg-warning',
            'APPROVED' => 'text-bg-success',
            'PUBLISHED' => 'text-bg-primary',
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

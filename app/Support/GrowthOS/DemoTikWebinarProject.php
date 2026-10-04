<?php

namespace App\Support\GrowthOS;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\GrowthOS\GrowthArtifactVersion;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\User;
use App\Services\GrowthOS\AI\Data\ConceptRevisionResult;
use App\Services\GrowthOS\AI\Data\DirectionPlanningResult;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\AI\GrowthImageService;
use App\Services\GrowthOS\AI\Support\PneVoice;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthOperationalTasks;
use App\Services\GrowthOS\GrowthSessionConceptStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Etap 0.3: sesyjny prototyp przygotowania webinaru TIK od zera.
 *
 * Kampania, prowadzący, kierunek, koncepcja i dziesięć materiałów roboczych są zapisywane w bazie. Propozycja AI żyje w sesji HTTP.
 */
class DemoTikWebinarProject
{
    public const PROJECT_ID = 'tik-webinar-session';

    public const MATERIAL_SKIPPED = 'SKIPPED';

    public const SESSION_PROJECT = 'growth_os.demo_tik_project';

    public const SESSION_DIRECTION_PLAN = 'growth_os.direction_planning_proposal';

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
     * @param  array{type: string, live_date: string, live_time: string, host: string, goal: string, topic?: string|null, host_instructor_id?: int|null, voice_instructor_id?: int|null}  $data
     * @return array<string, mixed>
     */
    public static function createProject(array $data, bool $useDirectionProposal = false): array
    {
        $topic = trim((string) ($data['topic'] ?? ''));
        $proposal = $useDirectionProposal ? self::directionPlanningProposal() : null;
        $applyProposal = $proposal !== null
            && ($proposal['fingerprint'] ?? null) === self::directionPlanningFingerprint(
                (string) $data['type'],
                (string) $data['goal'],
                $topic,
            );

        $project = self::freshWorkspace([
            'type' => $data['type'],
            'live_date' => $data['live_date'],
            'live_time' => $data['live_time'],
            'host' => $data['host'],
            'host_instructor_id' => $data['host_instructor_id'] ?? null,
            'voice_instructor_id' => $data['voice_instructor_id'] ?? null,
            'goal' => $data['goal'],
            'topic' => $topic,
            'status' => 'PLANNING',
            'direction' => $applyProposal ? ($proposal['direction'] ?? null) : null,
        ]);

        $user = auth()->user();
        if ($user instanceof User) {
            $project = app(GrowthSessionConceptStore::class)->createCampaign($project, $user);
            if ($applyProposal) {
                app(GrowthSessionConceptStore::class)->persistDirection($project, $user);
            }
        }

        self::saveProject($project);
        if ($useDirectionProposal) {
            session()->forget(self::SESSION_DIRECTION_PLAN);
        }

        return $project;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function directionPlanningProposal(): ?array
    {
        $proposal = session(self::SESSION_DIRECTION_PLAN);

        return is_array($proposal) ? $proposal : null;
    }

    /**
     * @param  array{type: string, goal: string, topic: string, live_date?: string}  $fields
     * @return array<string, mixed>
     */
    public static function storeDirectionPlanningProposal(
        array $fields,
        DirectionPlanningResult $result,
        string $mode,
        bool $simulation,
    ): array {
        $previous = self::directionPlanningProposal();
        $proposal = [
            'working_topic' => $result->workingTopic,
            'direction' => $result->direction,
            'title_suggestions' => $result->titleSuggestions,
            'change_summary' => $result->changeSummary,
            'sources' => $simulation ? [] : $result->researchSources,
            'web_search_used' => $simulation ? false : $result->webSearchUsed,
            'source' => $simulation ? 'simulation' : 'real_ai',
            'provider' => $simulation ? null : $result->provider,
            'model' => $simulation ? null : $result->model,
            'prompt_version' => $simulation ? null : $result->promptVersion,
            'schema_version' => $simulation ? null : $result->schemaVersion,
            'mode' => DirectionPlanningTask::mode($mode),
            'iteration_count' => $mode === DirectionPlanningTask::MODE_GENERATE
                ? 0
                : (int) ($previous['iteration_count'] ?? 0) + 1,
            'fingerprint' => self::directionPlanningFingerprint($fields['type'], $fields['goal'], $fields['topic']),
            'created_at' => now()->toIso8601String(),
        ];

        session([self::SESSION_DIRECTION_PLAN => $proposal]);

        return $proposal;
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeSimulatedDirectionPlanningProposal(array $fields, string $mode, string $instruction = ''): array
    {
        $topic = trim((string) $fields['topic']);
        $goal = (string) (collect(self::goals())->firstWhere('value', $fields['goal'])['label'] ?? $fields['goal']);
        $previous = self::directionPlanningProposal();
        $direction = is_array($previous['direction'] ?? null) ? $previous['direction'] : self::emptyDirection();
        if ($mode === DirectionPlanningTask::MODE_GENERATE || $direction['why_now'] === '') {
            $direction = [
                'why_now' => 'Temat „'.$topic.'” może być aktualny dla celu: '.$goal.'. To szkic startowy, nie research.',
                'audience' => 'Nauczyciele i dyrektorzy, którzy pracują z tym tematem w szkole.',
                'problem' => 'Przygotowanie i sprawdzenie materiałów zajmuje dużo czasu, a trudno oddzielić to, co naprawdę pomaga na lekcji.',
                'takeaway' => 'Uczestnik zobaczy praktyczny sposób pracy z tematem i będzie mógł odnieść go do własnej lekcji.',
                'sell_later' => 'być może',
            ];
        }
        if ($instruction !== '' && $mode === DirectionPlanningTask::MODE_ITERATE) {
            $direction['problem'] = trim($direction['problem'].' '.$instruction);
        }

        $result = new DirectionPlanningResult(
            workingTopic: $topic,
            direction: $direction,
            titleSuggestions: [],
            changeSummary: 'Symulacja lokalna na podstawie tematu i celu.',
            provider: 'simulation',
            model: '',
            promptVersion: DirectionPlanningTask::PROMPT_VERSION,
            schemaVersion: DirectionPlanningTask::SCHEMA_VERSION,
            requestId: '',
            researchSources: [],
            webSearchUsed: false,
        );

        return self::storeDirectionPlanningProposal($fields, $result, $mode, true);
    }

    public static function directionPlanningFingerprint(string $type, string $goal, string $topic): array
    {
        return [
            'type' => hash('sha256', trim($type)),
            'goal' => hash('sha256', trim($goal)),
            'topic' => hash('sha256', trim($topic)),
        ];
    }

    /**
     * @return array{why_now: string, audience: string, problem: string, takeaway: string, sell_later: string}
     */
    public static function emptyDirection(): array
    {
        return [
            'why_now' => '',
            'audience' => '',
            'problem' => '',
            'takeaway' => '',
            'sell_later' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function directionPlanningContext(array $fields, string $mode, string $instruction = ''): array
    {
        $context = [
            'today' => now()->toDateString(),
            'type' => (string) $fields['type'],
            'topic' => trim((string) $fields['topic']),
            'goal' => (string) (collect(self::goals())->firstWhere('value', $fields['goal'])['label'] ?? $fields['goal']),
            'live_date' => (string) ($fields['live_date'] ?? ''),
            'instruction' => trim($instruction),
        ];

        if ($mode !== DirectionPlanningTask::MODE_GENERATE) {
            $previous = self::directionPlanningProposal();
            $context['previous_proposal'] = is_array($previous) ? $previous : [];
        }

        return $context;
    }

    public static function directionIsApproved(array $project): bool
    {
        return isset($project['completed_steps']['direction']);
    }

    public static function directionFieldsFingerprint(array $direction): string
    {
        $normalized = [
            'why_now' => trim((string) ($direction['why_now'] ?? '')),
            'audience' => trim((string) ($direction['audience'] ?? '')),
            'problem' => trim((string) ($direction['problem'] ?? '')),
            'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
            'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
        ];

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $direction
     * @return array<string, mixed>
     */
    public static function directionWorkspaceContext(array $project, array $direction, string $instruction = ''): array
    {
        $proposal = is_array($project['direction_ai_proposal'] ?? null) ? $project['direction_ai_proposal'] : [];
        $goalValue = (string) ($project['goal'] ?? '');

        return [
            'today' => now()->toDateString(),
            'type' => (string) ($project['type'] ?? ''),
            'topic' => trim((string) ($project['topic'] ?? '')),
            'goal' => (string) (collect(self::goals())->firstWhere('value', $goalValue)['label'] ?? $goalValue),
            'live_date' => (string) ($project['live_date'] ?? ''),
            'instruction' => trim($instruction),
            'previous_proposal' => [
                'working_topic' => trim((string) ($project['topic'] ?? '')),
                'direction' => [
                    'why_now' => trim((string) ($direction['why_now'] ?? '')),
                    'audience' => trim((string) ($direction['audience'] ?? '')),
                    'problem' => trim((string) ($direction['problem'] ?? '')),
                    'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
                    'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
                ],
                'title_suggestions' => is_array($proposal['title_suggestions'] ?? null) ? $proposal['title_suggestions'] : [],
                'sources' => is_array($proposal['sources'] ?? null) ? $proposal['sources'] : [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeDirectionWorkspaceProposal(
        string $projectId,
        DirectionPlanningResult $result,
        string $mode,
        bool $simulation,
        string $savedFingerprint,
    ): array {
        $project = self::requireProject($projectId);
        $previous = is_array($project['direction_ai_proposal'] ?? null) ? $project['direction_ai_proposal'] : [];
        $proposal = [
            'working_topic' => $result->workingTopic,
            'direction' => $result->direction,
            'title_suggestions' => $result->titleSuggestions,
            'change_summary' => $result->changeSummary,
            'sources' => $simulation ? [] : $result->researchSources,
            'web_search_used' => $simulation ? false : $result->webSearchUsed,
            'source' => $simulation ? 'simulation' : 'real_ai',
            'provider' => $simulation ? null : $result->provider,
            'model' => $simulation ? null : $result->model,
            'prompt_version' => $simulation ? null : $result->promptVersion,
            'schema_version' => $simulation ? null : $result->schemaVersion,
            'mode' => DirectionPlanningTask::mode($mode),
            'iteration_count' => (int) ($previous['iteration_count'] ?? 0) + 1,
            'saved_fingerprint' => $savedFingerprint,
            'created_at' => now()->toIso8601String(),
        ];
        $project['direction_ai_proposal'] = $proposal;
        self::saveProject($project);

        return $proposal;
    }

    /**
     * @param  array<string, mixed>  $direction
     * @return array<string, mixed>
     */
    public static function storeSimulatedDirectionWorkspaceProposal(
        string $projectId,
        array $direction,
        string $mode,
        string $instruction,
        string $savedFingerprint,
    ): array {
        $project = self::requireProject($projectId);
        $topic = trim((string) ($project['topic'] ?? ''));
        $next = [
            'why_now' => trim((string) ($direction['why_now'] ?? '')),
            'audience' => trim((string) ($direction['audience'] ?? '')),
            'problem' => trim((string) ($direction['problem'] ?? '')),
            'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
            'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
        ];
        if ($next['why_now'] === '') {
            $next['why_now'] = 'Temat „'.$topic.'” może być aktualny. To szkic startowy, nie research.';
            $next['audience'] = $next['audience'] !== '' ? $next['audience'] : 'Nauczyciele, którzy pracują z tym tematem w szkole.';
            $next['problem'] = $next['problem'] !== '' ? $next['problem'] : 'Przygotowanie i sprawdzenie materiałów zajmuje dużo czasu.';
            $next['takeaway'] = $next['takeaway'] !== '' ? $next['takeaway'] : 'Uczestnik odniesie temat do własnej lekcji.';
            $next['sell_later'] = in_array($next['sell_later'], DirectionPlanningTask::SELL_LATER, true) ? $next['sell_later'] : 'być może';
        }
        if ($instruction !== '' && $mode === DirectionPlanningTask::MODE_ITERATE) {
            $next['problem'] = trim($next['problem'].' '.$instruction);
        }

        $result = new DirectionPlanningResult(
            workingTopic: $topic,
            direction: $next,
            titleSuggestions: [],
            changeSummary: 'Symulacja lokalna na podstawie obecnego kierunku.',
            provider: 'simulation',
            model: '',
            promptVersion: DirectionPlanningTask::PROMPT_VERSION,
            schemaVersion: DirectionPlanningTask::SCHEMA_VERSION,
            requestId: '',
            researchSources: [],
            webSearchUsed: false,
        );

        return self::storeDirectionWorkspaceProposal($projectId, $result, $mode, true, $savedFingerprint);
    }

    public static function applyDirectionWorkspaceProposal(string $projectId): string
    {
        $project = self::requireProject($projectId);
        $proposal = $project['direction_ai_proposal'] ?? null;
        if (! is_array($proposal) || ! is_array($proposal['direction'] ?? null)) {
            return 'missing';
        }
        if (self::directionIsApproved($project)) {
            return 'approved';
        }

        $saved = is_array($project['direction'] ?? null) ? $project['direction'] : self::emptyDirection();
        if (($proposal['saved_fingerprint'] ?? '') !== self::directionFieldsFingerprint($saved)) {
            $project['direction_ai_proposal'] = null;
            self::saveProject($project);

            return 'stale';
        }

        $project['direction'] = [
            'why_now' => trim((string) ($proposal['direction']['why_now'] ?? '')),
            'audience' => trim((string) ($proposal['direction']['audience'] ?? '')),
            'problem' => trim((string) ($proposal['direction']['problem'] ?? '')),
            'takeaway' => trim((string) ($proposal['direction']['takeaway'] ?? '')),
            'sell_later' => trim((string) ($proposal['direction']['sell_later'] ?? '')),
        ];
        $project['direction_ai_proposal'] = null;
        self::persistDirection($project);
        self::saveProject($project);

        return 'applied';
    }

    public static function rejectDirectionWorkspaceProposal(string $projectId): void
    {
        $project = self::requireProject($projectId);
        $project['direction_ai_proposal'] = null;
        self::saveProject($project);
    }

    /**
     * @param  array{host: string, host_instructor_id: int|null, voice_instructor_id: int|null}  $people
     * @return array<string, mixed>
     */
    public static function updatePeople(string $projectId, array $people): array
    {
        $project = self::requireProject($projectId);
        $project['host'] = trim($people['host']);
        $project['host_instructor_id'] = $people['host_instructor_id'];
        $project['voice_instructor_id'] = $people['voice_instructor_id'];
        self::saveProject($project);
        app(GrowthSessionConceptStore::class)->persistPeople($project);

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
        if ($step === 'direction') {
            $project['direction_ai_proposal'] = null;
        }

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
            ['value' => 'from_direction', 'label' => 'Wygeneruj na podstawie pomysłu i kierunku'],
            ['value' => 'shorter', 'label' => 'Skróć i uprość'],
            ['value' => 'practical', 'label' => 'Bardziej praktycznie'],
            ['value' => 'directors', 'label' => 'Bardziej dla dyrektora'],
            ['value' => 'less_sales', 'label' => 'Mniej sprzedażowo'],
            ['value' => 'expand', 'label' => 'Rozbuduj program'],
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function directionIsBlank(array $project): bool
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $text = trim((string) ($project['topic'] ?? ''))
            .trim((string) ($direction['why_now'] ?? ''))
            .trim((string) ($direction['audience'] ?? ''))
            .trim((string) ($direction['problem'] ?? ''))
            .trim((string) ($direction['takeaway'] ?? ''));

        return $text === '';
    }

    /**
     * @param  array<string, mixed>  $concept
     */
    public static function conceptNeedsFirstDraft(array $concept): bool
    {
        foreach (['subtitle', 'promise', 'plan', 'cta', 'lead_magnet'] as $key) {
            if (trim((string) ($concept[$key] ?? '')) !== '') {
                return false;
            }
        }

        $points = $concept['points'] ?? [];

        return ! is_array($points) || array_filter($points, static fn ($point): bool => trim((string) $point) !== '') === [];
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, string>
     */
    public static function directionDraftContext(array $project): array
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];

        return [
            'topic' => trim((string) ($project['topic'] ?? '')),
            'why_now' => trim((string) ($direction['why_now'] ?? '')),
            'audience' => trim((string) ($direction['audience'] ?? '')),
            'problem' => trim((string) ($direction['problem'] ?? '')),
            'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
            'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
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
        $concept = $intent === 'from_direction'
            ? self::simulatedConceptFromDirection($project)
            : self::simulatedConceptProposal(is_array($current) ? $current : [], $intent);

        $project['concept_ai_proposal'] = [
            'intent' => $intent,
            'intent_label' => $intentLabel,
            'created_at' => now()->toIso8601String(),
            'note' => 'Symulowana propozycja AI. Nic nie zostało nadpisane — możesz przyjąć albo odrzucić.',
            'concept' => $concept,
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
        $sellLater = trim((string) data_get($project, 'direction.sell_later', ''));
        $nextProduct = $intent === 'from_direction' && in_array($sellLater, ['nie', 'być może', 'tak'], true)
            ? $sellLater
            : (string) ($current['next_product'] ?? '');

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
                'next_product' => $nextProduct,
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

        return self::hydrateOwnedCampaign(
            app(GrowthSessionConceptStore::class)->latestOwnedCampaign($user),
        );
    }

    /**
     * @return list<array{id: int, topic: string, host: string, live_date: string, live_time: string, status: string, is_open: bool}>
     */
    public static function ownedCampaignSummaries(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $openId = (int) data_get(session(self::SESSION_PROJECT), 'growth_campaign_id', 0);

        return app(GrowthSessionConceptStore::class)->ownedCampaigns($user)->map(function (GrowthCampaign $campaign) use ($openId): array {
            $topic = trim((string) ($campaign->working_topic ?: $campaign->name));

            return [
                'id' => (int) $campaign->id,
                'topic' => $topic !== '' ? $topic : 'Webinar TIK',
                'host' => trim((string) ($campaign->host_name ?: '—')),
                'live_date' => $campaign->live_at?->toDateString() ?? '',
                'live_time' => $campaign->live_at?->format('H:i') ?? '',
                'status' => $campaign->status === GrowthCampaign::STATUS_PREPARING ? 'PREPARING' : 'PLANNING',
                'is_open' => $openId === (int) $campaign->id,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function openOwnedCampaign(int $campaignId): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 404);

        $project = self::hydrateOwnedCampaign(
            app(GrowthSessionConceptStore::class)->ownedCampaign($user, $campaignId),
        );
        abort_if($project === null, 404);

        return $project;
    }

    public static function deleteOwnedCampaign(int $campaignId): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 404);

        $campaign = app(GrowthSessionConceptStore::class)->ownedCampaign($user, $campaignId);
        abort_if($campaign === null, 404);

        app(GrowthImageService::class)->deleteForCampaign($campaign);
        $campaign->delete();

        $session = session(self::SESSION_PROJECT);
        if (is_array($session) && (int) ($session['growth_campaign_id'] ?? 0) === $campaignId) {
            session()->forget([self::SESSION_PROJECT, self::SESSION_DIRECTION_PLAN]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function hydrateOwnedCampaign(?GrowthCampaign $campaign): ?array
    {
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
            'topic' => $topic !== '' ? $topic : '',
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
            'host_instructor_id' => $fields['host_instructor_id'] ?? null,
            'voice_instructor_id' => $fields['voice_instructor_id'] ?? null,
            'goal' => $fields['goal'] ?? 'unknown',
            'topic' => $topic,
            'status' => $fields['status'] ?? 'PLANNING',
            'growth_campaign_id' => $fields['growth_campaign_id'] ?? null,
            'created_at' => now()->toIso8601String(),
            'completed_steps' => [],
            'direction' => is_array($fields['direction'] ?? null)
                ? [
                    'why_now' => trim((string) ($fields['direction']['why_now'] ?? '')),
                    'audience' => trim((string) ($fields['direction']['audience'] ?? '')),
                    'problem' => trim((string) ($fields['direction']['problem'] ?? '')),
                    'takeaway' => trim((string) ($fields['direction']['takeaway'] ?? '')),
                    'sell_later' => trim((string) ($fields['direction']['sell_later'] ?? '')),
                ]
                : self::emptyDirection(),
            'concept' => [
                'title' => $topic,
                'subtitle' => '',
                'promise' => '',
                'points' => [],
                'plan' => '',
                'cta' => '',
                'lead_magnet' => '',
                'next_product' => '',
            ],
            'concept_versions' => [],
            'concept_ai_proposal' => null,
            'direction_ai_proposal' => null,
            'material_ai_proposals' => [],
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
        $project['direction_ai_proposal'] = null;

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
    private static function persistMaterial(
        array $project,
        string $materialId,
        string $source = GrowthArtifactVersion::SOURCE_MANUAL,
        ?int $restoredFromVersion = null,
    ): void {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        app(GrowthSessionConceptStore::class)->persistMaterial($project, $user, $materialId, $source, $restoredFromVersion);
    }

    /**
     * Newest first, at most GrowthArtifactVersion::KEEP_LATEST. Empty when the project is not in the database yet.
     *
     * @param  array<string, mixed>  $project
     * @return EloquentCollection<int, GrowthArtifactVersion>
     */
    public static function materialVersions(array $project, string $materialId): EloquentCollection
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return new EloquentCollection;
        }

        return app(GrowthSessionConceptStore::class)->materialVersions((int) $campaignId, $materialId);
    }

    /**
     * Restoring writes the old text as a new version with status DRAFT; history is never rewritten.
     *
     * @return array{ok: bool, unchanged: bool}
     */
    public static function restoreMaterialVersion(string $projectId, string $materialId, int $version): array
    {
        $project = self::requireProject($projectId);
        $campaignId = $project['growth_campaign_id'] ?? null;
        $row = is_numeric($campaignId)
            ? app(GrowthSessionConceptStore::class)->materialVersion((int) $campaignId, $materialId, $version)
            : null;
        abort_if($row === null, 404);

        $draft = (string) ($row->payload['draft'] ?? '');
        if ($draft === self::materialDraft($project, $materialId)) {
            return ['ok' => false, 'unchanged' => true];
        }

        foreach ($project['materials'] as $index => $material) {
            if (($material['id'] ?? null) === $materialId) {
                $project['materials'][$index]['draft'] = $draft;
                $project['materials'][$index]['status'] = 'DRAFT';
                $project['materials'][$index]['updated_at'] = now()->toIso8601String();
            }
        }

        self::saveProject($project);
        self::persistMaterial($project, $materialId, GrowthArtifactVersion::SOURCE_RESTORE, $row->version);

        return ['ok' => true, 'unchanged' => false];
    }

    /**
     * Newest first, at most GrowthArtifactImage::KEEP_LATEST. Empty when the project is not in the database yet.
     *
     * @param  array<string, mixed>  $project
     * @return EloquentCollection<int, GrowthArtifactImage>
     */
    public static function materialImages(array $project, string $materialId): EloquentCollection
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return new EloquentCollection;
        }

        return app(GrowthSessionConceptStore::class)->materialImages((int) $campaignId, $materialId);
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function requireMaterialImage(array $project, string $materialId, int $imageId): GrowthArtifactImage
    {
        $campaignId = $project['growth_campaign_id'] ?? null;
        $image = is_numeric($campaignId)
            ? app(GrowthSessionConceptStore::class)->materialImage((int) $campaignId, $materialId, $imageId)
            : null;
        abort_if($image === null, 404);

        return $image;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function materialImageArtifact(array $project, string $materialId): ?GrowthArtifact
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return app(GrowthSessionConceptStore::class)->ensureMaterialArtifact($project, $user, $materialId);
    }

    /**
     * Headline line of the saved graphic brief, falling back to the concept title.
     *
     * @param  array<string, mixed>  $project
     */
    public static function graphicHeadline(array $project): string
    {
        $draft = self::materialDraft($project, MaterialDraftTask::GRAPHIC_MATERIAL_KEY);
        if (preg_match('/^Nagłówek:[ \t]*(.+)$/mu', $draft, $match) === 1 && trim($match[1]) !== '') {
            return trim($match[1]);
        }

        return trim((string) data_get($project, 'concept.title', $project['topic'] ?? ''));
    }

    /**
     * Image description from the saved graphic brief, falling back to its visual direction.
     *
     * @param  array<string, mixed>  $project
     */
    public static function graphicImageDescription(array $project): string
    {
        $draft = self::materialDraft($project, MaterialDraftTask::GRAPHIC_MATERIAL_KEY);

        foreach ([self::IMAGE_DESCRIPTION_LABEL, 'Kierunek wizualny:'] as $label) {
            if (preg_match('/^'.preg_quote($label, '/').'\R(.+?)(?:\R\R|\z)/msu', $draft, $match) === 1 && trim($match[1]) !== '') {
                return mb_substr(trim($match[1]), 0, GraphicImageTask::MAX_PROMPT_CHARS);
            }
        }

        return '';
    }

    /**
     * Visual direction paragraph of the saved graphic brief.
     *
     * @param  array<string, mixed>  $project
     */
    public static function graphicVisualDirection(array $project): string
    {
        $draft = self::materialDraft($project, MaterialDraftTask::GRAPHIC_MATERIAL_KEY);
        if (preg_match('/^Kierunek wizualny:\R(.+?)(?:\R\R|\z)/msu', $draft, $match) === 1) {
            return trim($match[1]);
        }

        return '';
    }

    public const IMAGE_DESCRIPTION_LABEL = 'Opis obrazu dla AI (bez tekstu na obrazie):';

    /**
     * Replace only the image-description section of a graphic brief.
     */
    public static function replaceGraphicImageDescription(string $draft, string $description): string
    {
        $block = self::IMAGE_DESCRIPTION_LABEL."\n".trim($description);
        $pattern = '/^'.preg_quote(self::IMAGE_DESCRIPTION_LABEL, '/').'\R.+?(?=\R\R|\z)/msu';

        if (preg_match($pattern, $draft) === 1) {
            return (string) preg_replace($pattern, $block, $draft, 1);
        }

        $draft = rtrim($draft);

        return $draft === '' ? $block : $draft."\n\n".$block;
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array{mode?: string, text?: string}  $work
     * @return array<string, mixed>
     */
    public static function imageDescriptionAiContext(array $project, array $work = [], string $instruction = ''): array
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];

        return [
            'campaign' => [
                'working_topic' => (string) ($project['topic'] ?? ''),
                'live_label' => self::liveLabel($project),
            ],
            'direction' => self::fingerprintDirection($direction),
            'concept' => [
                'title' => trim((string) ($concept['title'] ?? '')),
                'promise' => trim((string) ($concept['promise'] ?? '')),
                'plan' => trim((string) ($concept['plan'] ?? '')),
            ],
            'visual_direction' => self::graphicVisualDirection($project),
            'mode' => MaterialDraftTask::aiMode($work['mode'] ?? null),
            'text' => trim((string) ($work['text'] ?? '')),
            'instruction' => trim($instruction),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, string>
     */
    public static function imageDescriptionAiFingerprint(array $project): array
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];

        return [
            'topic' => self::hash(['topic' => (string) ($project['topic'] ?? '')]),
            'direction' => self::hash(self::fingerprintDirection($direction)),
            'concept' => self::hash(self::fingerprintConcept($concept)),
            'description' => self::hash(['description' => self::graphicImageDescription($project)]),
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>|null
     */
    public static function imageDescriptionAiProposal(array $project): ?array
    {
        $proposal = $project['image_description_ai_proposal'] ?? null;

        return is_array($proposal) ? $proposal : null;
    }

    /**
     * @param  array{mode?: string, text?: string}  $work
     * @return array<string, mixed>
     */
    public static function requestImageDescriptionAiProposal(string $projectId, array $work = [], string $instruction = ''): array
    {
        $project = self::requireProject($projectId);
        $mode = MaterialDraftTask::aiMode($work['mode'] ?? null);
        $text = trim((string) ($work['text'] ?? ''));
        $topic = trim((string) ($project['topic'] ?? ''));
        $description = $mode === MaterialDraftTask::MODE_GENERATE
            ? 'Ilustracja do webinaru „'.$topic.'”: spokojna scena z pracy odbiorców, miękkie światło dzienne, bez tekstu i logotypów.'
            : self::simulatedRevision($text);

        $project['image_description_ai_proposal'] = self::imageDescriptionProposalRecord(
            $project,
            $description,
            $mode === MaterialDraftTask::MODE_GENERATE
                ? 'Symulacja lokalna: opis złożony z tematu webinaru. Prawdziwe AI uwzględni kierunek i koncepcję.'
                : 'Symulacja lokalna: opis z uporządkowanymi odstępami. Prawdziwe AI uwzględni Twoją uwagę.',
            $instruction,
            'simulation',
            null,
            null,
            null,
            null,
            $mode,
            $text,
        );
        self::saveProject($project);

        return $project;
    }

    /**
     * @param  array{mode?: string, text?: string}  $work
     * @return array<string, mixed>
     */
    public static function storeImageDescriptionAiProposal(
        string $projectId,
        MaterialDraftResult $result,
        array $work = [],
        string $instruction = '',
    ): array {
        $project = self::requireProject($projectId);
        $project['image_description_ai_proposal'] = self::imageDescriptionProposalRecord(
            $project,
            $result->draft,
            $result->changeSummary,
            $instruction,
            'real_ai',
            $result->provider,
            $result->model,
            $result->promptVersion,
            $result->schemaVersion,
            MaterialDraftTask::aiMode($work['mode'] ?? null),
            trim((string) ($work['text'] ?? '')),
        );
        self::saveProject($project);

        return $project;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function iterableImageDescriptionAiProposal(string $projectId): ?array
    {
        $project = self::requireProject($projectId);
        $proposal = self::imageDescriptionAiProposal($project);
        if ($proposal === null) {
            return null;
        }

        if (($proposal['fingerprint'] ?? null) !== self::imageDescriptionAiFingerprint($project)) {
            unset($project['image_description_ai_proposal']);
            self::saveProject($project);

            return null;
        }

        return $proposal;
    }

    /**
     * @return array{ok: bool}
     */
    public static function applyImageDescriptionAiProposal(string $projectId): array
    {
        $project = self::requireProject($projectId);
        $proposal = self::imageDescriptionAiProposal($project);
        abort_if($proposal === null, 404);
        $materialId = MaterialDraftTask::GRAPHIC_MATERIAL_KEY;

        if (! self::canDraftMaterialWithAi($project)
            || ($proposal['fingerprint'] ?? null) !== self::imageDescriptionAiFingerprint($project)) {
            unset($project['image_description_ai_proposal']);
            self::saveProject($project);

            return ['ok' => false];
        }

        foreach ($project['materials'] as $index => $material) {
            if (($material['id'] ?? null) === $materialId) {
                $project['materials'][$index]['draft'] = self::replaceGraphicImageDescription(
                    (string) ($material['draft'] ?? ''),
                    (string) $proposal['description'],
                );
                $project['materials'][$index]['status'] = 'DRAFT';
                $project['materials'][$index]['updated_at'] = now()->toIso8601String();
            }
        }

        unset($project['image_description_ai_proposal']);
        self::saveProject($project);
        self::persistMaterial($project, $materialId, GrowthArtifactVersion::SOURCE_AI_APPLY);
        self::recordConceptDecision(
            $project,
            GrowthSessionConceptStore::DECISION_MATERIAL_AI_APPLY,
            GrowthDecision::STATUS_APPROVED,
            'Czy zastosować opis obrazu z AI?',
            'Zastosowano opis obrazu AI w briefie grafiki głównej',
            [
                'material_key' => $materialId,
                'scope' => 'image_description',
                'prompt_version' => (string) ($proposal['prompt_version'] ?? ''),
                'source' => (string) ($proposal['source'] ?? ''),
                'ai_mode' => (string) ($proposal['mode'] ?? ''),
            ],
            $materialId,
        );

        return ['ok' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rejectImageDescriptionAiProposal(string $projectId): array
    {
        $project = self::requireProject($projectId);
        $proposal = self::imageDescriptionAiProposal($project);
        abort_if($proposal === null, 404);

        unset($project['image_description_ai_proposal']);
        self::saveProject($project);
        self::recordConceptDecision(
            $project,
            GrowthSessionConceptStore::DECISION_MATERIAL_AI_REJECT,
            GrowthDecision::STATUS_REJECTED,
            'Czy odrzucić opis obrazu z AI?',
            'Odrzucono opis obrazu AI',
            [
                'material_key' => MaterialDraftTask::GRAPHIC_MATERIAL_KEY,
                'scope' => 'image_description',
                'source' => (string) ($proposal['source'] ?? ''),
            ],
            MaterialDraftTask::GRAPHIC_MATERIAL_KEY,
        );

        return $project;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private static function imageDescriptionProposalRecord(
        array $project,
        string $description,
        string $changeSummary,
        string $instruction,
        string $source,
        ?string $provider,
        ?string $model,
        ?string $promptVersion,
        ?string $schemaVersion,
        string $mode,
        string $text,
    ): array {
        $previous = self::imageDescriptionAiProposal($project);
        $iterate = $mode === MaterialDraftTask::MODE_ITERATE && $previous !== null;

        return [
            'description' => $description,
            'change_summary' => $changeSummary,
            'instruction' => trim($instruction),
            'source' => $source,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'schema_version' => $schemaVersion,
            'fingerprint' => self::imageDescriptionAiFingerprint($project),
            'created_at' => now()->toIso8601String(),
            'note' => $source === 'real_ai'
                ? 'Propozycja prawdziwego AI. Opis w generatorze i brief zmieniają się dopiero po „Zastosuj”.'
                : 'Symulowana propozycja AI. Opis w generatorze i brief zmieniają się dopiero po „Zastosuj”.',
            'mode' => $mode,
            'iteration_count' => $iterate ? (int) ($previous['iteration_count'] ?? 0) + 1 : 0,
            'compare_description' => $mode === MaterialDraftTask::MODE_GENERATE
                ? self::graphicImageDescription($project)
                : $text,
        ];
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
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private static function simulatedConceptFromDirection(array $project): array
    {
        $direction = self::directionDraftContext($project);
        $topic = $direction['topic'] !== '' ? $direction['topic'] : 'Webinar';
        $sellLater = in_array($direction['sell_later'], ['nie', 'być może', 'tak'], true)
            ? $direction['sell_later']
            : 'być może';

        return [
            'title' => $topic,
            'subtitle' => 'Szkic koncepcji na podstawie kierunku',
            'promise' => $direction['takeaway'] !== '' ? $direction['takeaway'] : 'Uczestnik odniesie temat do własnej pracy.',
            'points' => array_values(array_filter([
                $direction['problem'] !== '' ? $direction['problem'] : null,
                $direction['takeaway'] !== '' ? $direction['takeaway'] : null,
                'Jeden przykład do własnej lekcji.',
            ])),
            'plan' => 'Wprowadzenie, przykład, krótka praca własna i pytania.',
            'cta' => 'Zabierz jeden własny przykład do wypróbowania.',
            'lead_magnet' => 'Krótka checklista po webinarze.',
            'next_product' => $sellLater,
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
                if ($draft !== null && ! self::isMaterialSkipped($material)) {
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
     * Allow-listed context for the AI material draft. The only other materials ever included are approved ones listed in
     * MaterialDraftTask::sourceMaterialKeys (DEC-026, DEC-029, DEC-032, DEC-033). No personal data.
     *
     * @param  array<string, mixed>  $project
     * @param  array{emojis?: bool, hashtags?: bool}  $style
     * @param  array{mode?: string, text?: string}  $work
     * @return array<string, mixed>
     */
    public static function materialAiContext(
        array $project,
        string $materialId,
        array $style = [],
        string $instruction = '',
        array $work = [],
    ): array {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];
        $goal = (string) ($project['goal'] ?? '');

        $context = [
            'campaign' => [
                'working_topic' => (string) ($project['topic'] ?? ''),
                'goal' => (string) (collect(self::goals())->firstWhere('value', $goal)['label'] ?? $goal),
                'live_date' => (string) ($project['live_date'] ?? ''),
                'live_time' => (string) ($project['live_time'] ?? ''),
                'timezone' => (string) config('app.timezone'),
                'host_name' => self::aiHostName($project),
                'live_label' => self::liveLabel($project),
            ],
            'direction' => self::fingerprintDirection($direction),
            'concept' => self::fingerprintConcept($concept),
            'source_materials' => self::approvedSourceMaterials($project, $materialId),
            'current_draft' => self::materialDraft($project, $materialId),
            'style' => [
                'emojis' => (bool) ($style['emojis'] ?? true),
                'hashtags' => (bool) ($style['hashtags'] ?? true),
                'elements' => self::graphicElements($style),
                'length' => MaterialDraftTask::mailLength($style['length'] ?? null),
                'timing' => MaterialDraftTask::reminderTiming($style['timing'] ?? null),
                'duration_minutes' => MaterialDraftTask::hostScriptDuration($style['duration_minutes'] ?? null),
                'html' => (bool) ($style['html'] ?? false),
            ],
            'instruction' => trim($instruction),
        ];

        if (MaterialDraftTask::usesVoice($materialId)) {
            $voice = GrowthPeople::voice($project);
            $context['voice'] = ['name' => $voice['name'], 'profile' => $voice['profile']];
        }

        if (MaterialDraftTask::usesWorkModes($materialId)) {
            $context['work'] = [
                'mode' => MaterialDraftTask::aiMode($work['mode'] ?? null),
                'text' => trim((string) ($work['text'] ?? '')),
            ];
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, string>
     */
    public static function materialAiFingerprint(array $project, string $materialId): array
    {
        $direction = is_array($project['direction'] ?? null) ? $project['direction'] : [];
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];

        $fingerprint = [
            'direction' => self::hash(self::fingerprintDirection($direction)),
            'concept' => self::hash(self::fingerprintConcept($concept)),
            'material' => self::hash(['draft' => self::materialDraft($project, $materialId)]),
            'host' => self::hash(['host_name' => self::aiHostName($project)]),
        ];

        if (MaterialDraftTask::sourceMaterialKeys($materialId) !== []) {
            $fingerprint['source_materials'] = self::hash(self::approvedSourceMaterials($project, $materialId));
        }

        if (MaterialDraftTask::usesVoice($materialId)) {
            $voice = GrowthPeople::voice($project);
            $fingerprint['voice'] = self::hash([
                'voice_instructor_id' => $voice['instructor_id'],
                'status' => $voice['status'],
                'name' => $voice['name'],
                'profile' => hash('sha256', $voice['profile']),
                'pne_voice' => PneVoice::VERSION,
            ]);
        }

        return $fingerprint;
    }

    /**
     * Polish date with weekday, computed here so the model never has to work out the day of the week.
     *
     * @param  array<string, mixed>  $project
     */
    public static function liveLabel(array $project): string
    {
        $date = trim((string) ($project['live_date'] ?? ''));
        $time = trim((string) ($project['live_time'] ?? ''));
        if ($date === '') {
            return '';
        }

        try {
            $liveAt = CarbonImmutable::parse(trim($date.' '.$time))->locale('pl');
        } catch (\Throwable) {
            return trim($date.' '.$time);
        }

        return $time !== ''
            ? $liveAt->translatedFormat('l, j F Y, \g\o\d\z. H:i')
            : $liveAt->translatedFormat('l, j F Y');
    }

    /**
     * @param  array{elements?: array<string, bool>}  $style
     * @return array<string, bool>
     */
    private static function graphicElements(array $style): array
    {
        $elements = [];
        foreach (array_keys(MaterialDraftTask::GRAPHIC_OPTIONAL_ELEMENTS) as $key) {
            $elements[$key] = (bool) ($style['elements'][$key] ?? true);
        }

        return $elements;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    public static function approvedYoutubeDescription(array $project): string
    {
        return self::approvedMaterialDraft($project, MaterialDraftTask::MATERIAL_KEY);
    }

    /**
     * Approved main mail without the alternative subjects, which are not part of the sent mail.
     *
     * @param  array<string, mixed>  $project
     */
    public static function approvedMainMail(array $project): string
    {
        $draft = self::approvedMaterialDraft($project, MaterialDraftTask::MAIL_MATERIAL_KEY);
        if ($draft === '') {
            return '';
        }

        $mail = MaterialDraftTask::parseMainMail($draft);

        return MaterialDraftTask::composeMainMail(
            $mail['subject'] !== '' ? [$mail['subject']] : [],
            $mail['preheader'],
            $mail['body'],
        );
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, string>
     */
    public static function approvedSourceMaterials(array $project, string $materialId): array
    {
        $sources = [];
        foreach (MaterialDraftTask::sourceMaterialKeys($materialId) as $source) {
            $sources[$source] = match ($source) {
                'main_mail' => self::approvedMainMail($project),
                default => self::approvedYoutubeDescription($project),
            };
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private static function approvedMaterialDraft(array $project, string $materialId): string
    {
        foreach ($project['materials'] ?? [] as $material) {
            if (($material['id'] ?? null) === $materialId) {
                return in_array($material['status'] ?? null, ['APPROVED', 'PUBLISHED'], true)
                    ? trim((string) ($material['draft'] ?? ''))
                    : '';
            }
        }

        return '';
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
     * @param  array{emojis?: bool, hashtags?: bool}  $style
     * @return array<string, mixed>
     */
    public static function requestMaterialAiProposal(
        string $projectId,
        string $materialId,
        array $style = [],
        string $instruction = '',
        array $work = [],
    ): array {
        $project = self::requireProject($projectId);
        $concept = is_array($project['concept'] ?? null) ? $project['concept'] : [];
        $instruction = trim($instruction);
        $emojis = (bool) ($style['emojis'] ?? ! MaterialDraftTask::usesVoice($materialId));
        $mode = MaterialDraftTask::aiMode($work['mode'] ?? null);
        $text = trim((string) ($work['text'] ?? ''));

        if (MaterialDraftTask::usesWorkModes($materialId) && $mode !== MaterialDraftTask::MODE_GENERATE) {
            $revised = self::simulatedRevision($text);
            if ($materialId === MaterialDraftTask::MAIL_MATERIAL_KEY && ($style['html'] ?? false)) {
                $revised = MailHtmlFormatter::formatComposedDraft($revised);
            }
            $project['material_ai_proposals'][$materialId] = [
                'material_key' => $materialId,
                'draft' => $revised,
                'change_summary' => match (true) {
                    MaterialDraftTask::usesVoice($materialId) && $mode === MaterialDraftTask::MODE_REFINE => 'Symulacja lokalna: Twój szkic z uporządkowanymi odstępami. Prawdziwe AI zredaguje go Twoim głosem.',
                    MaterialDraftTask::usesVoice($materialId) => 'Symulacja lokalna: poprzednia propozycja z uporządkowanymi odstępami. Prawdziwe AI uwzględni Twoją uwagę.',
                    $materialId === MaterialDraftTask::GRAPHIC_MATERIAL_KEY && $mode === MaterialDraftTask::MODE_REFINE => 'Symulacja lokalna: Twój brief z uporządkowanymi odstępami. Prawdziwe AI poprawi go według instrukcji.',
                    $materialId === MaterialDraftTask::GRAPHIC_MATERIAL_KEY => 'Symulacja lokalna: poprzednia propozycja briefu z uporządkowanymi odstępami. Prawdziwe AI uwzględni Twoją uwagę.',
                    $mode === MaterialDraftTask::MODE_REFINE => 'Symulacja lokalna: Twój szkic z uporządkowanymi odstępami. Prawdziwe AI poprawi go według instrukcji.',
                    default => 'Symulacja lokalna: poprzednia propozycja z uporządkowanymi odstępami. Prawdziwe AI uwzględni Twoją uwagę.',
                },
                'instruction' => $instruction,
                'source' => 'simulation',
                'provider' => null,
                'model' => null,
                'prompt_version' => null,
                'schema_version' => null,
                'fingerprint' => self::materialAiFingerprint($project, $materialId),
                'created_at' => now()->toIso8601String(),
                'note' => 'Symulowana propozycja AI. Obecny szkic pozostaje bez zmian do chwili wybrania „Zastosuj”.',
                ...self::materialAiWorkMeta($project, $materialId, $mode, $text),
            ];
            self::saveProject($project);

            return $project;
        }

        $project['material_ai_proposals'][$materialId] = [
            'material_key' => $materialId,
            'draft' => match ($materialId) {
                MaterialDraftTask::FACEBOOK_MATERIAL_KEY => self::simulatedFacebookPost($project, $concept, $emojis, (bool) ($style['hashtags'] ?? true)),
                MaterialDraftTask::GRAPHIC_MATERIAL_KEY => self::simulatedGraphicBrief($project, $concept, self::graphicElements($style)),
                MaterialDraftTask::MAIL_MATERIAL_KEY => self::simulatedMainMail($project, $concept, $emojis, MaterialDraftTask::mailLength($style['length'] ?? null), (bool) ($style['html'] ?? false)),
                MaterialDraftTask::REMINDER_MATERIAL_KEY => self::simulatedReminderMail(
                    $project,
                    $concept,
                    $emojis,
                    MaterialDraftTask::mailLength($style['length'] ?? null),
                    MaterialDraftTask::reminderTiming($style['timing'] ?? null),
                ),
                MaterialDraftTask::HOST_SCRIPT_MATERIAL_KEY => self::simulatedHostScript(
                    $project,
                    $concept,
                    MaterialDraftTask::hostScriptDuration($style['duration_minutes'] ?? null),
                ),
                default => self::simulatedYoutubeDescription($project, $concept, $emojis),
            },
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
            ...self::materialAiWorkMeta($project, $materialId, $mode, $text),
        ];

        self::saveProject($project);

        return $project;
    }

    /**
     * Mode details kept with a voice-material proposal (DEC-036). Not part of the fingerprint: they describe the request,
     * while the fingerprint describes the saved project state.
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private static function materialAiWorkMeta(array $project, string $materialId, string $mode, string $text): array
    {
        if (! MaterialDraftTask::usesWorkModes($materialId)) {
            return [];
        }

        $previous = self::materialAiProposal($project, $materialId);
        $iterate = $mode === MaterialDraftTask::MODE_ITERATE && $previous !== null;
        $meta = [
            'mode' => $mode,
            'iteration_count' => $iterate ? (int) ($previous['iteration_count'] ?? 0) + 1 : 0,
            'compare_draft' => $mode === MaterialDraftTask::MODE_GENERATE ? self::materialDraft($project, $materialId) : $text,
            'restore_draft' => match (true) {
                $iterate => (string) ($previous['restore_draft'] ?? ''),
                $mode === MaterialDraftTask::MODE_REFINE => $text,
                default => '',
            },
            'text_hash' => $text !== '' ? hash('sha256', $text) : null,
        ];

        if (MaterialDraftTask::usesVoice($materialId)) {
            $meta['voice_instructor_id'] = GrowthPeople::voice($project)['instructor_id'];
        }

        return $meta;
    }

    /**
     * Previous proposal that iterate may build on; a stale one is removed so iterate never works on an outdated base.
     *
     * @return array<string, mixed>|null
     */
    public static function iterableMaterialAiProposal(string $projectId, string $materialId): ?array
    {
        $project = self::requireProject($projectId);
        $proposal = self::materialAiProposal($project, $materialId);
        if ($proposal === null) {
            return null;
        }

        if (($proposal['fingerprint'] ?? null) !== self::materialAiFingerprint($project, $materialId)) {
            unset($project['material_ai_proposals'][$materialId]);
            self::saveProject($project);

            return null;
        }

        return $proposal;
    }

    private static function simulatedRevision(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($text)) ?: [];

        return implode("\n\n", array_map(
            static fn (string $paragraph): string => trim((string) preg_replace('/[ \t]+/u', ' ', $paragraph)),
            $paragraphs,
        ));
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
        array $work = [],
    ): array {
        $project = self::requireProject($projectId);

        $project['material_ai_proposals'][$materialId] = [
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
            ...self::materialAiWorkMeta(
                $project,
                $materialId,
                MaterialDraftTask::aiMode($work['mode'] ?? null),
                trim((string) ($work['text'] ?? '')),
            ),
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
        $proposal = $project['material_ai_proposals'][$materialId] ?? null;

        return is_array($proposal) ? $proposal : null;
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
            unset($project['material_ai_proposals'][$materialId]);
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

        unset($project['material_ai_proposals'][$materialId]);
        self::saveProject($project);
        self::persistMaterial($project, $materialId, GrowthArtifactVersion::SOURCE_AI_APPLY);
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

        unset($project['material_ai_proposals'][$materialId]);
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
     * @return array<string, string|int|null>
     */
    private static function materialAiDecisionMeta(array $proposal): array
    {
        $meta = [
            'material_key' => (string) ($proposal['material_key'] ?? ''),
            'prompt_version' => (string) ($proposal['prompt_version'] ?? ''),
            'source' => (string) ($proposal['source'] ?? ''),
        ];

        if (isset($proposal['mode'])) {
            $meta['ai_mode'] = (string) $proposal['mode'];
            $meta['iteration_count'] = (int) ($proposal['iteration_count'] ?? 0);
            $meta['communication_voice_instructor_id'] = is_int($proposal['voice_instructor_id'] ?? null)
                ? $proposal['voice_instructor_id']
                : null;
        }

        return $meta;
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
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $concept
     * @param  array<string, bool>  $elements
     */
    private static function simulatedGraphicBrief(array $project, array $concept, array $elements): string
    {
        $topic = trim((string) ($project['topic'] ?? ''));
        $title = $topic !== '' ? $topic : trim((string) ($concept['title'] ?? ''));

        return MaterialDraftTask::composeGraphicBrief(
            [
                'headline' => $title,
                'subtitle' => trim((string) ($concept['subtitle'] ?? '')),
                'cta' => 'Zapisz się',
                'visual_direction' => 'Spokojna, profesjonalna kolorystyka dla edukacji: granat, biel i jeden ciepły akcent. Motyw: nauczyciel przy laptopie z materiałami lekcyjnymi. Najważniejsze elementy w środku kadru, z miejscem na nagłówek, żeby kompozycja działała w obu formatach.',
                'image_prompt' => 'Jasne, nowoczesne biurko nauczyciela z laptopem, kolorowymi kartami pracy i kubkiem kawy, miękkie światło dzienne. Bez żadnego tekstu, liter, cyfr i logotypów.',
                'alt_text' => 'Grafika zapowiadająca webinar „'.$title.'”.',
            ],
            self::liveLabel($project),
            self::aiHostName($project),
            $elements,
        );
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $concept
     */
    private static function simulatedMainMail(array $project, array $concept, bool $emojis, string $length, bool $html = false): string
    {
        $icon = static fn (string $emoji): string => $emojis ? $emoji.' ' : '';
        $title = trim((string) ($concept['title'] ?? $project['topic'] ?? ''));
        $host = self::aiHostName($project);
        $points = collect(is_array($concept['points'] ?? null) ? $concept['points'] : [])
            ->map(fn (mixed $point): string => trim((string) $point))
            ->filter(fn (string $point): bool => $point !== '')
            ->take($length === 'long' ? 6 : 3)
            ->map(fn (string $point): string => '• '.$point)
            ->implode("\n");
        $plan = trim((string) ($concept['plan'] ?? ''));
        $extra = trim((string) ($concept['additional_material'] ?? ''));

        $body = implode("\n\n", array_filter([
            'Dzień dobry,',
            'zapraszamy Państwa na webinar „'.$title.'”. '.trim((string) ($concept['promise'] ?? '')),
            $points !== '' ? $icon('📌')."Czego się Państwo dowiedzą:\n".$points : '',
            $length === 'long' && $plan !== '' ? 'Plan spotkania: '.$plan : '',
            $length === 'long' && $extra !== '' ? 'Po webinarze otrzymają Państwo: '.$extra : '',
            $icon('📅').'Termin: '.self::liveLabel($project),
            $host !== '' ? 'Prowadzący: '.$host : '',
            "Zapisz się:\n".MaterialDraftTask::LINK_PLACEHOLDER,
            "Z pozdrowieniami,\n".($host !== '' ? $host."\n" : '').'Zespół PNE',
        ]));

        if ($html) {
            $body = MailHtmlFormatter::format($body);
        }

        return MaterialDraftTask::composeMainMail(
            ['Zaproszenie: '.$title, $title.' — webinar dla nauczycieli', 'Praktyczny webinar: '.$title],
            'Termin: '.self::liveLabel($project).'. Udział wymaga zapisu.',
            $body,
        );
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $concept
     */
    private static function simulatedReminderMail(array $project, array $concept, bool $emojis, string $length, string $timing): string
    {
        $icon = static fn (string $emoji): string => $emojis ? $emoji.' ' : '';
        $title = trim((string) ($concept['title'] ?? $project['topic'] ?? ''));
        $host = self::aiHostName($project);
        $when = $timing === 'same_day' ? 'dziś' : 'jutro';
        $points = collect(is_array($concept['points'] ?? null) ? $concept['points'] : [])
            ->map(fn (mixed $point): string => trim((string) $point))
            ->filter(fn (string $point): bool => $point !== '')
            ->take($length === 'long' ? 5 : 3)
            ->map(fn (string $point): string => '• '.$point)
            ->implode("\n");
        $plan = trim((string) ($concept['plan'] ?? ''));
        $extra = trim((string) ($concept['additional_material'] ?? ''));

        $body = implode("\n\n", array_filter([
            'Dzień dobry,',
            'przypominamy, że już '.$when.' odbędzie się webinar „'.$title.'”.',
            $points !== '' ? "Najważniejsze tematy:\n".$points : '',
            $length === 'long' && $plan !== '' ? 'Plan spotkania: '.$plan : '',
            $length === 'long' && $extra !== '' ? 'Po webinarze otrzymają Państwo: '.$extra : '',
            $icon('📅').'Termin: '.self::liveLabel($project),
            $host !== '' ? 'Prowadzący: '.$host : '',
            $icon('👉')."Jeśli są Państwo zapisani, zapraszamy do pokoju webinaru:\n".MaterialDraftTask::ROOM_LINK_PLACEHOLDER,
            "Jeśli jeszcze się Państwo nie zapisali, można to zrobić tutaj:\n".MaterialDraftTask::LINK_PLACEHOLDER,
            "Z pozdrowieniami,\n".($host !== '' ? $host."\n" : '').'Zespół PNE',
        ]));

        return MaterialDraftTask::composeMainMail(
            [
                ucfirst($when).' webinar: '.$title,
                'Przypomnienie: '.$title,
                'Do zobaczenia '.$when.' na webinarze',
            ],
            'Termin: '.self::liveLabel($project).'. Link do pokoju i zapisu w środku.',
            $body,
        );
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $concept
     */
    private static function simulatedHostScript(array $project, array $concept, int $minutes): string
    {
        $title = trim((string) ($concept['title'] ?? $project['topic'] ?? ''));
        $host = self::aiHostName($project);
        $points = collect(is_array($concept['points'] ?? null) ? $concept['points'] : [])
            ->map(fn (mixed $point): string => trim((string) $point))
            ->filter(fn (string $point): bool => $point !== '')
            ->take(4)
            ->values();
        if ($points->isEmpty()) {
            $points = collect([$title]);
        }

        $intro = max(3, (int) round($minutes * 0.1));
        $questions = max(5, (int) round($minutes * 0.15));
        $ending = max(3, (int) round($minutes * 0.1));
        $content = max($points->count(), $minutes - $intro - $questions - $ending);
        $blocks = [['Intro', $intro, [
            'Cel: przywitać uczestników i pokazać, co wyniosą ze spotkania.',
            "Do powiedzenia:\n- Dzień dobry Państwu, witam na webinarze „".$title."”.\n- Spotkanie jest nagrywane.".($host !== '' ? "\n- Nazywam się ".$host.'.' : '')."\n- ".trim((string) ($concept['promise'] ?? '')),
            'Pytanie na czat: Skąd Państwo dziś do nas dołączają?',
            'Przejście: Zaczynamy od pierwszego tematu.',
        ]]];
        foreach ($points as $index => $point) {
            $length = intdiv($content, $points->count()) + ($index < $content % $points->count() ? 1 : 0);
            $blocks[] = [$point, $length, array_values(array_filter([
                'Cel: omówić temat „'.$point.'” na przykładzie.',
                "Do powiedzenia:\n- Najważniejsza myśl tego bloku.\n- Pokaz na ekranie krok po kroku.",
                $index === 0 ? 'Pytanie na czat: Czy korzystali już Państwo z tego rozwiązania?' : '',
                'Przejście: Przechodzimy dalej.',
            ]))];
        }
        $blocks[] = ['Pytania i odpowiedzi', $questions, [
            'Cel: odpowiedzieć na pytania z czatu.',
            'Pytanie na czat: Co chcieliby Państwo jeszcze zobaczyć?',
            'Przejście: Zbliżamy się do końca.',
        ]];
        $extra = trim((string) ($concept['additional_material'] ?? ''));
        $blocks[] = ['Zakończenie', $ending, array_values(array_filter([
            'Cel: podsumować spotkanie i zaprosić do dalszego działania.',
            "Do powiedzenia:\n- Krótkie podsumowanie trzech najważniejszych wniosków.\n- ".trim((string) ($concept['cta'] ?? '')),
            $extra !== '' ? 'Materiał dodatkowy: '.$extra : '',
            'Dziękuję Państwu za udział.',
        ]))];

        $start = CarbonImmutable::createFromFormat('H:i', (string) ($project['live_time'] ?? '20:00'));
        $sections = [
            'Scenariusz: '.$title."\nTermin: ".self::liveLabel($project).', '.$minutes.' minut',
            "Checklista przed startem\n- Dźwięk i kamera\n- Udostępniany ekran i otwarte materiały\n- Włączone nagrywanie\n- Znaczniki linków pod ręką",
        ];
        foreach ($blocks as $index => [$name, $length, $lines]) {
            $end = $start->addMinutes($length);
            if ($index === array_key_last($blocks)) {
                $lines = array_values(array_filter($lines, fn (string $line): bool => ! str_starts_with($line, 'Przejście:')));
            }
            $sections[] = $start->format('H:i').'–'.$end->format('H:i').' '.$name."\n".implode("\n", $lines);
            $start = $end;
        }

        return implode("\n\n", $sections);
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, mixed>  $concept
     */
    private static function simulatedFacebookPost(array $project, array $concept, bool $emojis = true, bool $hashtags = true): string
    {
        $icon = static fn (string $emoji): string => $emojis ? $emoji.' ' : '';
        $bullet = $emojis ? '✅ ' : '• ';

        $points = collect(is_array($concept['points'] ?? null) ? $concept['points'] : [])
            ->map(fn (mixed $point): string => trim((string) $point))
            ->filter(fn (string $point): bool => $point !== '')
            ->take(3)
            ->map(fn (string $point): string => $bullet.$point)
            ->implode("\n");

        $title = trim((string) ($concept['title'] ?? ''));
        $promise = trim((string) ($concept['promise'] ?? ''));

        return trim(implode("\n\n", array_filter([
            $title !== '' ? $icon('🎓').'Zapraszamy na webinar „'.$title.'”.' : '',
            $promise,
            $points,
            $icon('📅').($project['live_date'] ?? '').', godz. '.($project['live_time'] ?? ''),
            $icon('👉').'Zapisz się: '.MaterialDraftTask::LINK_PLACEHOLDER,
            $hashtags ? '#nauczyciele #TIK #webinar' : '',
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
                'label' => 'Zaplanuj webinar',
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
            if (! in_array(($material['status'] ?? null), ['APPROVED', 'PUBLISHED', self::MATERIAL_SKIPPED], true)) {
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
            ->reject(fn (array $material) => in_array($material['status'], ['APPROVED', 'PUBLISHED', self::MATERIAL_SKIPPED], true))
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
            self::MATERIAL_SKIPPED => 'Nie dotyczy',
        ];
    }

    /**
     * @param  array<string, mixed>  $material
     */
    public static function isMaterialSkipped(array $material): bool
    {
        return ($material['status'] ?? null) === self::MATERIAL_SKIPPED;
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
            self::MATERIAL_SKIPPED => 'bg-light text-secondary border border-secondary-subtle',
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
            self::materialTemplate('youtube-description', 'Opis YouTube', 'Opis live', 'REVIEW', 'Krótki opis korzyści i programu spotkania.', ''),
            self::materialTemplate('main-graphic', 'Grafika główna', 'Grafika', 'DRAFT', 'Nagłówek i kierunek grafiki do promocji.', ''),
            self::materialTemplate('facebook-post', 'Post Facebook', 'Social', 'DRAFT', 'Post zapowiadający webinar.', ''),
            self::materialTemplate('main-mail', 'Mailing główny', 'Mailing', 'NOT_STARTED', 'Główny mailing zapraszający.', ''),
            self::materialTemplate('reminder-mail', 'Mailing przypominający', 'Mailing', 'NOT_STARTED', 'Przypomnienie dzień przed live.', ''),
            self::materialTemplate('landing', 'Formularz zapisu / landing', 'Landing', 'NOT_STARTED', 'Strona lub formularz zapisu.', ''),
            self::materialTemplate('host-script', 'Scenariusz prowadzącego', 'Scenariusz', 'DRAFT', 'Plan prowadzenia webinaru.', ''),
            self::materialTemplate('participant-material', 'Materiał dla uczestnika', 'Materiał', 'NOT_STARTED', 'PDF lub checklista po webinarze.', ''),
            self::materialTemplate('obs-intro', 'Intro OBS', 'Techniczne', 'NOT_STARTED', 'Plansza lub intro do live.', ''),
            self::materialTemplate('follow-up', 'Follow-up', 'Mailing', 'NOT_STARTED', 'Mail po webinarze.', ''),
        ];
    }
}

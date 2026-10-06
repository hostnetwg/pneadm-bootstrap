<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\GrowthOS\GrowthTask;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\GrowthAiService;
use App\Services\GrowthOS\AI\GrowthImageService;
use App\Services\GrowthOS\AI\Support\GraphicImageProcessor;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthOperationalTasks;
use App\Support\GrowthOS\DemoTikWebinarProject;
use App\Support\GrowthOS\GraphicLogoStore;
use App\Support\GrowthOS\GrowthAiRequestOptions;
use App\Support\GrowthOS\GrowthPeople;
use App\Services\GrowthOS\AI\Support\GrowthAiExecutionOptions;
use App\Services\GrowthOS\AI\Support\GrowthAiModelCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public const MATERIAL_AI_PRECONDITION_MESSAGE = 'Najpierw zatwierdź kierunek i koncepcję webinaru.';

    public const MATERIAL_SKIPPED_MESSAGE = 'Ten materiał jest wyłączony (status „Nie dotyczy”). Zmień status, żeby z nim pracować.';

    public const MATERIAL_AI_STALE_MESSAGE = 'Kierunek, koncepcja lub szkic zmieniły się od czasu przygotowania propozycji. Wygeneruj ją ponownie.';

    public const MATERIAL_AI_REFINE_EMPTY_MESSAGE = 'Najpierw wpisz własny szkic.';

    public const MATERIAL_AI_ITERATE_EMPTY_MESSAGE = 'Napisz, co jeszcze poprawić.';

    public const IMAGE_DESCRIPTION_REFINE_EMPTY_MESSAGE = 'Najpierw wpisz opis obrazu.';

    public const IMAGE_DESCRIPTION_ITERATE_EMPTY_MESSAGE = 'Napisz, co poprawić w opisie obrazu.';

    public const IMAGE_REVISE_EMPTY_MESSAGE = 'Napisz, co poprawić na tym obrazie.';

    public const DIRECTION_PLAN_EMPTY_TOPIC_MESSAGE = 'Najpierw wpisz temat webinaru.';

    public const DIRECTION_PLAN_ITERATE_EMPTY_MESSAGE = 'Napisz, co chcesz zmienić.';

    public const DIRECTION_PLAN_STALE_MESSAGE = 'Temat, cel lub typ zmieniły się od czasu przygotowania propozycji. Wygeneruj ją ponownie.';

    public const DIRECTION_WORKSPACE_APPROVED_MESSAGE = 'Najpierw cofnij zatwierdzenie kierunku.';

    public const DIRECTION_WORKSPACE_STALE_MESSAGE = 'Kierunek zmienił się od czasu przygotowania propozycji. Wygeneruj ją ponownie.';

    public const DIRECTION_PLAN_RESEARCH_MESSAGE = GrowthAiException::RESEARCH_FAILED_MESSAGE;

    public const PROJECT_DELETED_MESSAGE = 'Usunięto projekt webinaru. Tej operacji nie da się cofnąć.';

    public function index(): View
    {
        return view('growth-os.projects.index', [
            'campaigns' => DemoTikWebinarProject::ownedCampaignSummaries(),
            'nextAction' => DemoTikWebinarProject::nextAction(DemoTikWebinarProject::project()),
            'projectStatusLabels' => DemoTikWebinarProject::projectStatusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('growth-os.projects.create', [
            'goals' => DemoTikWebinarProject::goals(),
            'instructorOptions' => GrowthPeople::instructorOptions(),
            'directionProposal' => DemoTikWebinarProject::directionPlanningProposal(),
            'growthAiEnabled' => config('growth_ai.enabled') === true,
            'growthAiResearchLabel' => GrowthAiModelCatalog::badgeForChannel(GrowthAiModelCatalog::CHANNEL_RESEARCH),
        ]);
    }

    public function planDirection(Request $request): RedirectResponse
    {
        $goalValues = collect(DemoTikWebinarProject::goals())->pluck('value')->all();
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'goal' => ['required', Rule::in($goalValues)],
            'topic' => ['nullable', 'string', 'max:180'],
            'live_date' => ['nullable', 'date'],
            'planning_mode' => ['nullable', Rule::in([
                DirectionPlanningTask::MODE_GENERATE,
                DirectionPlanningTask::MODE_ITERATE,
                DirectionPlanningTask::MODE_REFRESH,
            ])],
            'planning_instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
            'address_form' => ['nullable', Rule::in(\App\Services\GrowthOS\AI\Support\AddressFormPolicy::values())],
            ...GrowthAiExecutionOptions::requestRules(),
        ]);

        $topic = trim((string) ($data['topic'] ?? ''));
        $mode = DirectionPlanningTask::mode($data['planning_mode'] ?? null);
        $instruction = trim((string) ($data['planning_instruction'] ?? ''));
        $back = redirect()->route('growth.projects.create')->withInput();
        $execution = GrowthAiRequestOptions::fromRequest($request);
        if ($mode === DirectionPlanningTask::MODE_REFRESH) {
            $mode = DirectionPlanningTask::MODE_ITERATE;
            $execution['web_search'] = true;
            if ($instruction === '') {
                $instruction = 'Odśwież kierunek na podstawie aktualnych informacji z sieci.';
            }
        }

        if ($topic === '') {
            return $back->with('error', self::DIRECTION_PLAN_EMPTY_TOPIC_MESSAGE);
        }

        if ($mode === DirectionPlanningTask::MODE_ITERATE && $instruction === '') {
            return $back->with('error', self::DIRECTION_PLAN_ITERATE_EMPTY_MESSAGE);
        }

        $proposal = DemoTikWebinarProject::directionPlanningProposal();
        if ($mode !== DirectionPlanningTask::MODE_GENERATE) {
            $fingerprint = DemoTikWebinarProject::directionPlanningFingerprint($data['type'], $data['goal'], $topic);
            if ($proposal === null || ($proposal['fingerprint'] ?? null) !== $fingerprint) {
                return $back->with('error', self::DIRECTION_PLAN_STALE_MESSAGE);
            }
        }

        $fields = [
            'type' => $data['type'],
            'goal' => $data['goal'],
            'topic' => $topic,
            'live_date' => (string) ($data['live_date'] ?? ''),
            'address_form' => \App\Services\GrowthOS\AI\Support\AddressFormPolicy::normalize($data['address_form'] ?? null),
            'instruction' => $instruction,
        ];
        $inherit = $mode === DirectionPlanningTask::MODE_GENERATE
            ? null
            : GrowthAiRequestOptions::inheritFromProposal($proposal);

        if (config('growth_ai.enabled') !== true) {
            DemoTikWebinarProject::storeSimulatedDirectionPlanningProposal($fields, $mode, $instruction, $execution);

            return $back->with('success', 'Przygotowano szkic kierunku (symulacja lokalna — informacje nie zostały sprawdzone w Internecie).');
        }

        try {
            $result = app(GrowthAiService::class)->planDirection(
                $request->user(),
                DemoTikWebinarProject::directionPlanningContext($fields, $mode, $instruction),
                $mode,
                $execution,
                $inherit,
            );
        } catch (GrowthAiException $exception) {
            $message = $exception->errorType === 'web_search_missing' || $exception->userMessage === GrowthAiException::RESEARCH_FAILED_MESSAGE
                ? GrowthAiException::RESEARCH_FAILED_MESSAGE
                : ($exception->userMessage === GrowthAiException::INVALID_RESPONSE_MESSAGE
                    ? 'Nie udało się przygotować poprawnej propozycji AI. Możesz przygotować kierunek ręcznie.'
                    : $exception->userMessage);

            return $back->with('error', $message);
        }

        DemoTikWebinarProject::storeDirectionPlanningProposal($fields, $result, $mode, false);

        return $back->with('success', 'AI przygotowało propozycję kierunku. Projekt jeszcze nie powstał — nic nie zatwierdzono.');
    }

    public function store(Request $request): RedirectResponse
    {
        $goalValues = collect(DemoTikWebinarProject::goals())->pluck('value')->all();

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'live_date' => ['required', 'date'],
            'live_time' => ['required', 'date_format:H:i'],
            'goal' => ['required', Rule::in($goalValues)],
            'topic' => ['nullable', 'string', 'max:180'],
            ...GrowthPeople::rules(),
        ], GrowthPeople::messages());

        $useProposal = $request->boolean('use_direction_ai_proposal');
        $proposal = DemoTikWebinarProject::directionPlanningProposal();
        $applied = $useProposal && $proposal !== null
            && ($proposal['fingerprint'] ?? null) === DemoTikWebinarProject::directionPlanningFingerprint(
                $data['type'],
                $data['goal'],
                trim((string) ($data['topic'] ?? '')),
            );

        $project = DemoTikWebinarProject::createProject([...$data, ...GrowthPeople::fromInput($data)], $useProposal);

        $redirect = redirect()
            ->route('growth.projects.show', $project['id']);

        if ($useProposal && ! $applied) {
            return $redirect->with('error', self::DIRECTION_PLAN_STALE_MESSAGE)
                ->with('success', 'Utworzono projekt webinaru. Propozycja AI nie została użyta, bo temat, cel lub typ się zmieniły.');
        }

        return $redirect->with('success', $applied
            ? 'Utworzono projekt webinaru. Kierunek z AI jest szkicem — zatwierdź go świadomie w workspace.'
            : 'Utworzono projekt webinaru. Kampania i prowadzący są zapisane.');
    }

    public function open(int $campaign): RedirectResponse
    {
        DemoTikWebinarProject::openOwnedCampaign($campaign);

        return redirect()->route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID);
    }

    public function destroy(int $campaign): RedirectResponse
    {
        DemoTikWebinarProject::deleteOwnedCampaign($campaign);

        return redirect()
            ->route('growth.projects.index')
            ->with('success', self::PROJECT_DELETED_MESSAGE);
    }

    public function updateHost(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate(GrowthPeople::rules(), GrowthPeople::messages());

        $outcome = DemoTikWebinarProject::updatePeople($project, GrowthPeople::fromInput($data));
        $redirect = redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano prowadzącego, głos komunikacji i formę zwrotu.')
            ->withFragment('project-host');

        if ($outcome['address_form_changed'] && $outcome['has_approved_materials']) {
            $from = \App\Services\GrowthOS\AI\Support\AddressFormPolicy::label($outcome['previous_address_form']);
            $to = \App\Services\GrowthOS\AI\Support\AddressFormPolicy::label($outcome['next_address_form']);

            return $redirect->with(
                'warning',
                "Zmieniłeś formę zwrotu z „{$from}” na „{$to}”. Zatwierdzone materiały nie zostały automatycznie zmienione i mogą wymagać ponownego sprawdzenia."
            );
        }

        return $redirect;
    }

    public function updateSchedule(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'live_date' => ['required', 'date'],
            'live_time' => ['required', 'date_format:H:i'],
        ]);

        DemoTikWebinarProject::updateSchedule($project, [
            'live_date' => $data['live_date'],
            'live_time' => $data['live_time'],
        ]);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano datę i godzinę webinaru.')
            ->withFragment('project-schedule');
    }

    public function updateLinks(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'registration_url' => \App\Support\GrowthOS\GrowthMailUrls::registrationRules(),
            'youtube_live_url' => \App\Support\GrowthOS\GrowthMailUrls::youtubeRules(),
        ], [
            'registration_url.url' => 'Link do zapisów musi być poprawnym adresem URL.',
            'registration_url.regex' => 'Link do zapisów musi zaczynać się od https://.',
            'youtube_live_url.url' => 'Link do YouTube musi być poprawnym adresem URL.',
            'youtube_live_url.regex' => 'Link do YouTube musi zaczynać się od https://.',
        ]);

        DemoTikWebinarProject::updateLinks($project, [
            'registration_url' => $data['registration_url'] ?? null,
            'youtube_live_url' => $data['youtube_live_url'] ?? null,
        ]);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano linki webinaru.')
            ->withFragment('project-links');
    }

    public function show(string $project): View
    {
        $item = DemoTikWebinarProject::requireProject($project);

        return view('growth-os.projects.show', [
            'project' => $item,
            'health' => DemoTikWebinarProject::projectHealth($item),
            'nextAction' => DemoTikWebinarProject::nextAction($item),
            'stages' => DemoTikWebinarProject::stages(),
            'timeline' => DemoTikWebinarProject::timeline(),
            'projectStatusLabels' => DemoTikWebinarProject::projectStatusLabels(),
            'materialStatusLabels' => DemoTikWebinarProject::materialStatusLabels(),
            'conceptAiIntents' => DemoTikWebinarProject::conceptAiIntents(),
            'growthAiEnabled' => config('growth_ai.enabled') === true,
            'growthAiGeneralLabel' => GrowthAiModelCatalog::badgeForChannel(GrowthAiModelCatalog::CHANNEL_GENERAL),
            'growthAiResearchLabel' => GrowthAiModelCatalog::badgeForChannel(GrowthAiModelCatalog::CHANNEL_RESEARCH),
            'conceptDecisions' => DemoTikWebinarProject::conceptDecisions($item),
            'operationalTasks' => DemoTikWebinarProject::operationalTasks($item),
            'instructorOptions' => GrowthPeople::instructorOptions([
                $item['host_instructor_id'] ?? null,
                $item['voice_instructor_id'] ?? null,
            ]),
            'voice' => GrowthPeople::voice($item),
        ]);
    }

    public function completeStep(string $project, string $step): RedirectResponse
    {
        DemoTikWebinarProject::completeStep($project, $step);

        $message = $step === 'direction'
            ? 'Kierunek zatwierdzony. Decyzja została zapisana.'
            : 'Koncepcja gotowa. Decyzja została zapisana.';

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', $message)
            ->withFragment($step === 'direction' ? 'direction' : 'concept');
    }

    public function reopenStep(string $project, string $step): RedirectResponse
    {
        DemoTikWebinarProject::reopenStep($project, $step);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', $step === 'concept'
                ? 'Cofnięto zatwierdzenie koncepcji. Decyzja została zapisana.'
                : 'Cofnięto zatwierdzenie kierunku. Decyzja została zapisana.')
            ->withFragment($step);
    }

    public function updateDirection(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:180'],
            'why_now' => ['required', 'string', 'max:1500'],
            'audience' => ['required', 'string', 'max:1000'],
            'problem' => ['required', 'string', 'max:1500'],
            'takeaway' => ['required', 'string', 'max:1500'],
            'sell_later' => ['required', Rule::in(['nie', 'być może', 'tak'])],
        ]);

        DemoTikWebinarProject::updateDirection($project, $data);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano kierunek.')
            ->withFragment('direction');
    }

    public function reviseDirection(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['nullable', 'string', 'max:180'],
            'why_now' => ['nullable', 'string', 'max:1500'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'problem' => ['nullable', 'string', 'max:1500'],
            'takeaway' => ['nullable', 'string', 'max:1500'],
            'sell_later' => ['nullable', Rule::in(DirectionPlanningTask::SELL_LATER)],
            'planning_mode' => ['nullable', Rule::in([
                DirectionPlanningTask::MODE_GENERATE,
                DirectionPlanningTask::MODE_ITERATE,
                DirectionPlanningTask::MODE_REFRESH,
            ])],
            'planning_instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
            ...GrowthAiExecutionOptions::requestRules(),
        ]);

        $item = DemoTikWebinarProject::requireProject($project);
        $back = redirect()->route('growth.projects.show', $project)->withFragment('direction')->withInput();
        $mode = DirectionPlanningTask::mode($data['planning_mode'] ?? null);
        $instruction = trim((string) ($data['planning_instruction'] ?? ''));
        $execution = GrowthAiRequestOptions::fromRequest($request);
        if ($mode === DirectionPlanningTask::MODE_REFRESH) {
            $mode = DirectionPlanningTask::MODE_ITERATE;
            $execution['web_search'] = true;
            if ($instruction === '') {
                $instruction = 'Odśwież kierunek na podstawie aktualnych informacji z sieci.';
            }
        }

        if (DemoTikWebinarProject::directionIsApproved($item)) {
            return $back->with('error', self::DIRECTION_WORKSPACE_APPROVED_MESSAGE);
        }

        if ($mode === DirectionPlanningTask::MODE_ITERATE && $instruction === '') {
            return $back->with('error', self::DIRECTION_PLAN_ITERATE_EMPTY_MESSAGE);
        }

        $topic = trim((string) ($data['topic'] ?? $item['topic'] ?? ''));
        if ($topic !== '') {
            $item['topic'] = $topic;
        }

        $direction = [
            'why_now' => trim((string) ($data['why_now'] ?? '')),
            'audience' => trim((string) ($data['audience'] ?? '')),
            'problem' => trim((string) ($data['problem'] ?? '')),
            'takeaway' => trim((string) ($data['takeaway'] ?? '')),
            'sell_later' => trim((string) ($data['sell_later'] ?? '')),
        ];
        $saved = is_array($item['direction'] ?? null) ? $item['direction'] : DemoTikWebinarProject::emptyDirection();
        $savedFingerprint = DemoTikWebinarProject::directionFieldsFingerprint($saved);
        $inherit = $mode === DirectionPlanningTask::MODE_GENERATE
            ? null
            : GrowthAiRequestOptions::inheritFromProposal(
                is_array($item['direction_ai_proposal'] ?? null) ? $item['direction_ai_proposal'] : null,
            );

        if (config('growth_ai.enabled') !== true) {
            DemoTikWebinarProject::storeSimulatedDirectionWorkspaceProposal($project, $direction, $mode, $instruction, $savedFingerprint, $execution);

            return $back->with('success', $mode === DirectionPlanningTask::MODE_GENERATE
                ? 'Przygotowano nową propozycję kierunku od zera (symulacja lokalna — obecny kierunek nie został zmieniony).'
                : 'Przygotowano propozycję kierunku (symulacja lokalna — obecny kierunek nie został zmieniony).');
        }

        try {
            $result = app(GrowthAiService::class)->planDirection(
                $request->user(),
                DemoTikWebinarProject::directionWorkspaceContext($item, $direction, $instruction),
                $mode,
                $execution,
                $inherit,
            );
        } catch (GrowthAiException $exception) {
            $message = $exception->errorType === 'web_search_missing' || $exception->userMessage === GrowthAiException::RESEARCH_FAILED_MESSAGE
                ? GrowthAiException::RESEARCH_FAILED_MESSAGE
                : ($exception->userMessage === GrowthAiException::INVALID_RESPONSE_MESSAGE
                    ? 'Nie udało się przygotować poprawnej propozycji AI. Możesz przygotować kierunek ręcznie.'
                    : $exception->userMessage);

            return $back->with('error', $message);
        }

        DemoTikWebinarProject::storeDirectionWorkspaceProposal($project, $result, $mode, false, $savedFingerprint);

        return $back->with('success', $mode === DirectionPlanningTask::MODE_GENERATE
            ? 'AI przygotowało nową propozycję kierunku od zera. Obecny kierunek nie został zmieniony.'
            : 'AI przygotowało propozycję kierunku. Obecny kierunek nie został zmieniony.');
    }

    public function applyDirectionAi(string $project): RedirectResponse
    {
        $back = redirect()->route('growth.projects.show', $project)->withFragment('direction');
        $outcome = DemoTikWebinarProject::applyDirectionWorkspaceProposal($project);

        return match ($outcome) {
            'applied' => $back->with('success', 'Zastosowano propozycję AI. Kierunek jest szkicem — zatwierdź go świadomie.'),
            'approved' => $back->with('error', self::DIRECTION_WORKSPACE_APPROVED_MESSAGE),
            'stale' => $back->with('error', self::DIRECTION_WORKSPACE_STALE_MESSAGE),
            default => $back->with('error', 'Nie ma propozycji AI do zastosowania.'),
        };
    }

    public function rejectDirectionAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::rejectDirectionWorkspaceProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Odrzucono propozycję AI. Kierunek bez zmian.')
            ->withFragment('direction');
    }

    public function updateConcept(Request $request, string $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['required', 'string', 'max:220'],
            'promise' => ['required', 'string', 'max:500'],
            'points' => ['required', 'string', 'max:2000'],
            'plan' => ['required', 'string', 'max:1000'],
            'cta' => ['required', 'string', 'max:400'],
            'lead_magnet' => ['required', 'string', 'max:300'],
            'next_product' => ['required', Rule::in(['nie', 'być może', 'tak'])],
        ]);

        DemoTikWebinarProject::updateConcept($project, $data);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano koncepcję. Pozostałe etapy zostają w tej sesji.')
            ->withFragment('concept');
    }

    public function requestConceptAi(
        Request $request,
        string $project,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'intent' => ['required', Rule::in(collect(DemoTikWebinarProject::conceptAiIntents())->pluck('value')->all())],
            'instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
            ...GrowthAiExecutionOptions::requestRules(),
        ]);
        $intent = (string) $data['intent'];
        $intentLabel = (string) (
            collect(DemoTikWebinarProject::conceptAiIntents())->firstWhere('value', $intent)['label']
            ?? $intent
        );
        $ownerInstruction = trim((string) ($data['instruction'] ?? ''));
        $wantsJson = $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
        $item = DemoTikWebinarProject::requireProject($project);
        $fromDirection = $intent === 'from_direction';
        $execution = GrowthAiRequestOptions::fromRequest($request);
        $fail = function (string $message) use ($project, $wantsJson): RedirectResponse|JsonResponse {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()
                ->route('growth.projects.show', $project)
                ->with('error', $message)
                ->withFragment('concept');
        };

        if (DemoTikWebinarProject::conceptAiIntentRequiresInstruction($intent) && $ownerInstruction === '') {
            return $fail('Dla opcji „Popraw zgodnie z moimi sugestiami” wpisz sugestie w polu „Dodatkowa instrukcja”.');
        }

        if ($fromDirection && DemoTikWebinarProject::directionIsBlank($item)) {
            return $fail('Najpierw uzupełnij pomysł i kierunek. Bez tego nie ma z czego ułożyć koncepcji.');
        }

        if (config('growth_ai.enabled') !== true) {
            $updatedProject = DemoTikWebinarProject::requestConceptAiProposal($project, $intent, $ownerInstruction);

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => 'AI przygotowało propozycję (symulacja). Obecna koncepcja nie została nadpisana.',
                    'proposal_html' => $this->renderConceptAiProposal($updatedProject, $project),
                ]);
            }

            return redirect()
                ->route('growth.projects.show', $project)
                ->with('success', 'AI przygotowało propozycję (symulacja). Obecna koncepcja nie została nadpisana.')
                ->with('growth_ai_completed', true)
                ->withFragment('concept');
        }

        $growthAiService = app(GrowthAiService::class);
        if (DemoTikWebinarProject::conceptAiIntentRequiresInstruction($intent)) {
            $instruction = 'Popraw koncepcję wyłącznie zgodnie z poniższymi sugestiami użytkownika. '
                .'Nie zmieniaj pól, których sugestie nie dotyczą.'."\n\n"
                .'Sugestie:'."\n".$ownerInstruction;
        } else {
            $instruction = $intentLabel;
            if ($ownerInstruction !== '') {
                $instruction .= '. Dodatkowa instrukcja użytkownika: '.$ownerInstruction;
            }
        }

        try {
            $result = $growthAiService->reviseConcept(
                user: $request->user(),
                concept: is_array($item['concept'] ?? null) ? $item['concept'] : [],
                audience: (string) data_get($item, 'direction.audience', ''),
                instruction: $instruction,
                direction: DemoTikWebinarProject::directionDraftContext($item),
                fromDirection: $fromDirection,
                addressForm: \App\Services\GrowthOS\AI\Support\AddressFormPolicy::fromProject($item),
                execution: $execution,
            );

            $updatedProject = DemoTikWebinarProject::storeConceptAiProposal(
                projectId: $project,
                intent: $intent,
                intentLabel: $intentLabel,
                result: $result,
            );
        } catch (GrowthAiException $exception) {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $exception->userMessage,
                ], 422);
            }

            return redirect()
                ->route('growth.projects.show', $project)
                ->with('error', $exception->userMessage)
                ->withFragment('concept');
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'message' => 'AI przygotowało propozycję. Obecna koncepcja nie została nadpisana.',
                'proposal_html' => $this->renderConceptAiProposal($updatedProject, $project),
            ]);
        }

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'AI przygotowało propozycję. Obecna koncepcja nie została nadpisana.')
            ->with('growth_ai_completed', true)
            ->withFragment('concept');
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function renderConceptAiProposal(array $project, string $projectId): string
    {
        return view('growth-os.projects.partials.concept-ai-proposal', [
            'proposal' => $project['concept_ai_proposal'] ?? null,
            'projectId' => $projectId,
        ])->render();
    }

    public function applyConceptAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::applyConceptAiProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zastosowano propozycję AI i zapisano koncepcję oraz decyzję. Sprawdź ją i zatwierdź, gdy będzie gotowa.')
            ->withFragment('concept');
    }

    public function rejectConceptAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::rejectConceptAiProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Odrzucono propozycję AI. Decyzja została zapisana. Została poprzednia koncepcja.')
            ->withFragment('concept');
    }

    public function updateOperationalTask(Request $request, string $project, string $taskKey): RedirectResponse
    {
        abort_unless(in_array($taskKey, GrowthOperationalTasks::keys(), true), 404);

        $done = $request->validate([
            'done' => ['required', 'boolean'],
        ])['done'];

        $item = DemoTikWebinarProject::requireProject($project);
        $campaignId = $item['growth_campaign_id'] ?? null;
        abort_unless(is_numeric($campaignId), 404);

        $task = GrowthTask::query()
            ->where('growth_campaign_id', (int) $campaignId)
            ->where('key', $taskKey)
            ->firstOrFail();

        $task->status = $done ? GrowthTask::STATUS_DONE : GrowthTask::STATUS_TODO;
        $task->completed_at = $done ? now() : null;
        $task->save();

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', $done ? 'Zadanie oznaczono jako gotowe.' : 'Zadanie wróciło do zrobienia.')
            ->withFragment('timeline');
    }

    public function material(string $project, string $material): View
    {
        $item = DemoTikWebinarProject::material($project, $material);
        $projectItem = DemoTikWebinarProject::requireProject($project);

        $aiDraftSupported = MaterialDraftTask::supports($material);
        $isFacebookPost = $material === MaterialDraftTask::FACEBOOK_MATERIAL_KEY;
        $skipped = DemoTikWebinarProject::isMaterialSkipped($item);
        $aiDraftProposal = $aiDraftSupported ? DemoTikWebinarProject::materialAiProposal($projectItem, $material) : null;
        $mailFields = null;
        $mailFieldsFromAiProposal = false;

        if (MaterialDraftTask::isMail($material)) {
            $mailDraftSource = (string) ($item['draft'] ?? '');
            $restoredDraft = session('material_restored_draft');
            if (is_string($restoredDraft) && trim($restoredDraft) !== '') {
                $mailDraftSource = $restoredDraft;
            } elseif (is_array($aiDraftProposal) && trim((string) ($aiDraftProposal['draft'] ?? '')) !== '') {
                $mailDraftSource = (string) $aiDraftProposal['draft'];
                $mailFieldsFromAiProposal = true;
            }
            $mailFields = MaterialDraftTask::parseMainMail($mailDraftSource);
        }

        $materialEditorDraft = (string) session('material_restored_draft', $item['draft'] ?? '');
        if ($isFacebookPost) {
            $registrationUrl = $projectItem['registration_url'] ?? null;
            $materialEditorDraft = \App\Support\GrowthOS\GrowthMailUrls::withRegistrationLink(
                $materialEditorDraft,
                $registrationUrl,
            );
            if (is_array($aiDraftProposal)) {
                $aiDraftProposal['draft'] = \App\Support\GrowthOS\GrowthMailUrls::withRegistrationLink(
                    (string) ($aiDraftProposal['draft'] ?? ''),
                    $registrationUrl,
                );
            }
        }

        return view('growth-os.projects.material', [
            'project' => $projectItem,
            'material' => $item,
            'materialEditorDraft' => $materialEditorDraft,
            'mailFields' => $mailFields,
            'mailFieldsFromAiProposal' => $mailFieldsFromAiProposal,
            'materialStatusLabels' => DemoTikWebinarProject::materialStatusLabels(),
            'aiDraftSupported' => $aiDraftSupported,
            'aiDraftUsesVoice' => MaterialDraftTask::usesVoice($material),
            'aiDraftUsesWorkModes' => MaterialDraftTask::usesWorkModes($material),
            'aiDraftVoice' => MaterialDraftTask::usesVoice($material) ? GrowthPeople::voice($projectItem) : null,
            'aiDraftIsFacebookPost' => $isFacebookPost,
            'aiDraftIsGraphic' => $material === MaterialDraftTask::GRAPHIC_MATERIAL_KEY,
            'aiDraftIsMail' => MaterialDraftTask::isMail($material),
            'aiDraftIsReminder' => $material === MaterialDraftTask::REMINDER_MATERIAL_KEY,
            'aiDraftIsHostScript' => $material === MaterialDraftTask::HOST_SCRIPT_MATERIAL_KEY,
            'aiDraftMailLengths' => MaterialDraftTask::mailLengths($material),
            'aiDraftUsesMainMailSource' => in_array('main_mail', MaterialDraftTask::sourceMaterialKeys($material), true),
            'aiDraftUsesMainMail' => in_array('main_mail', MaterialDraftTask::sourceMaterialKeys($material), true)
                && DemoTikWebinarProject::approvedMainMail($projectItem) !== '',
            'aiDraftLiveLabel' => DemoTikWebinarProject::liveLabel($projectItem),
            'aiDraftUsesYoutubeSource' => MaterialDraftTask::usesYoutubeSource($material),
            'aiDraftUsesYoutubeDescription' => MaterialDraftTask::usesYoutubeSource($material)
                && DemoTikWebinarProject::approvedYoutubeDescription($projectItem) !== '',
            'materialSkipped' => $skipped,
            'aiDraftAllowed' => $aiDraftSupported && ! $skipped && DemoTikWebinarProject::canDraftMaterialWithAi($projectItem),
            'aiDraftProposal' => $aiDraftProposal,
            'aiRealEnabled' => config('growth_ai.enabled') === true,
            'aiGeneralLabel' => GrowthAiModelCatalog::badgeForChannel(GrowthAiModelCatalog::CHANNEL_GENERAL),
            'materialVersions' => DemoTikWebinarProject::materialVersions($projectItem, $material),
            'imageGeneratorEnabled' => $material === GraphicImageTask::MATERIAL_KEY,
            'imageGeneratorReady' => is_numeric($projectItem['growth_campaign_id'] ?? null),
            'imageDescription' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::graphicImageDescription($projectItem)
                : '',
            'imageHeadline' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::graphicHeadline($projectItem)
                : '',
            'imageModel' => (string) config('growth_ai.images.model'),
            'imageQuality' => (string) config('growth_ai.images.quality'),
            'imageDailyUsed' => $material === GraphicImageTask::MATERIAL_KEY
                ? app(GrowthImageService::class)->dailyUsage(auth()->user())
                : 0,
            'materialImages' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::materialImages($projectItem, $material)
                : new EloquentCollection,
            'pneLogoAvailable' => $material === GraphicImageTask::MATERIAL_KEY && GraphicLogoStore::pneAvailable(),
            'sponsorLogoReady' => $material === GraphicImageTask::MATERIAL_KEY
                && is_numeric($projectItem['growth_campaign_id'] ?? null)
                && GraphicLogoStore::hasSponsor((int) $projectItem['growth_campaign_id']),
            'imageDescriptionProposal' => $material === GraphicImageTask::MATERIAL_KEY
                ? DemoTikWebinarProject::imageDescriptionAiProposal($projectItem)
                : null,
        ]);
    }

    public function generateMaterialImage(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys(GraphicImageTask::FORMATS))],
            'image_prompt' => ['required', 'string', 'max:'.GraphicImageTask::MAX_PROMPT_CHARS],
            'include_headline' => ['nullable', 'boolean'],
            'include_pne_logo' => ['nullable', 'boolean'],
            'include_sponsor_logo' => ['nullable', 'boolean'],
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->withInput()->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->withInput()->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        $artifact = DemoTikWebinarProject::materialImageArtifact($item, $material);
        if ($artifact === null) {
            return $back->withInput()->with('error', self::IMAGE_NOT_PERSISTED_MESSAGE);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('growth_ai.images.timeout_seconds') + 60);
        }

        try {
            $image = app(GrowthImageService::class)->generate(
                user: $request->user(),
                artifact: $artifact,
                format: (string) $data['format'],
                description: (string) $data['image_prompt'],
                includeHeadline: $request->boolean('include_headline'),
                headline: DemoTikWebinarProject::graphicHeadline($item),
                liveLabel: DemoTikWebinarProject::liveLabel($item),
                includePneLogo: $request->boolean('include_pne_logo'),
                includeSponsorLogo: $request->boolean('include_sponsor_logo'),
            );
        } catch (GrowthAiException $exception) {
            return $back->withInput()->with('error', $exception->userMessage);
        }

        return $back->with('success', $image->source === GrowthArtifactImage::SOURCE_SIMULATION
            ? 'Przygotowano obraz zastępczy (symulacja lokalna, bez wywołania OpenAI).'
            : 'Wygenerowano obraz. Sprawdź go w galerii. Nic nie opublikowano.');
    }

    public function reviseMaterialImage(Request $request, string $project, string $material, int $image): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $data = $request->validate([
            'instruction' => ['required', 'string', 'max:'.GraphicImageTask::MAX_REVISE_CHARS],
        ], [
            'instruction.required' => self::IMAGE_REVISE_EMPTY_MESSAGE,
        ]);
        $source = DemoTikWebinarProject::requireMaterialImage($item, $material, $image);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('growth_ai.images.timeout_seconds') + 60);
        }

        try {
            $revised = app(GrowthImageService::class)->revise($request->user(), $source, trim((string) $data['instruction']));
        } catch (GrowthAiException $exception) {
            return $back->withInput()->with('error', $exception->userMessage);
        }

        return $back->with('success', $revised->source === GrowthArtifactImage::SOURCE_SIMULATION
            ? 'Przygotowano poprawiony obraz zastępczy (symulacja lokalna, bez wywołania OpenAI). Poprzedni obraz został w galerii.'
            : 'Poprawiono obraz według uwagi. Poprzedni obraz został w galerii. Nic nie opublikowano.');
    }

    public function requestImageDescriptionAi(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $data = $request->validate([
            'description_instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
            'mode' => ['nullable', Rule::in([MaterialDraftTask::MODE_GENERATE, MaterialDraftTask::MODE_REFINE, MaterialDraftTask::MODE_ITERATE])],
            'author_draft' => ['nullable', 'string', 'max:'.GraphicImageTask::MAX_PROMPT_CHARS],
            ...GrowthAiExecutionOptions::requestRules(),
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        $instruction = trim((string) ($data['description_instruction'] ?? ''));
        $mode = MaterialDraftTask::aiMode($data['mode'] ?? null);
        $work = ['mode' => $mode, 'text' => ''];
        $execution = GrowthAiRequestOptions::fromRequest($request);
        $inherit = null;

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->withInput()->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->withInput()->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        if ($mode === MaterialDraftTask::MODE_REFINE) {
            $work['text'] = trim((string) ($data['author_draft'] ?? ''));
            if ($work['text'] === '') {
                return $back->withInput()->with('error', self::IMAGE_DESCRIPTION_REFINE_EMPTY_MESSAGE);
            }
        }

        if ($mode === MaterialDraftTask::MODE_ITERATE) {
            if ($instruction === '') {
                return $back->withInput()->with('error', self::IMAGE_DESCRIPTION_ITERATE_EMPTY_MESSAGE);
            }
            $previous = DemoTikWebinarProject::iterableImageDescriptionAiProposal($project);
            if ($previous === null) {
                return $back->with('error', self::MATERIAL_AI_STALE_MESSAGE);
            }
            $work['text'] = (string) $previous['description'];
            $inherit = GrowthAiRequestOptions::inheritFromProposal($previous);
        }

        if (config('growth_ai.enabled') !== true) {
            DemoTikWebinarProject::requestImageDescriptionAiProposal($project, $work, $instruction);

            return $back->with('success', 'AI przygotowało opis obrazu (symulacja lokalna). Obecny opis nie został nadpisany.');
        }

        try {
            $result = app(GrowthAiService::class)->reviseImageDescription(
                $request->user(),
                DemoTikWebinarProject::imageDescriptionAiContext($item, $work, $instruction),
                $execution,
                $inherit,
            );
        } catch (GrowthAiException $exception) {
            $message = $exception->userMessage === GrowthAiException::INVALID_RESPONSE_MESSAGE
                ? 'Nie udało się przygotować poprawnego opisu obrazu. Obecny opis nie został zmieniony.'
                : $exception->userMessage;

            return $back->withInput()->with('error', $message);
        }

        DemoTikWebinarProject::storeImageDescriptionAiProposal($project, $result, $work, $instruction);

        return $back->with('success', 'AI przygotowało opis obrazu. Obecny opis nie został nadpisany.');
    }

    public function applyImageDescriptionAi(string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        $outcome = DemoTikWebinarProject::applyImageDescriptionAiProposal($project);
        if (! $outcome['ok']) {
            return $back->with('error', self::MATERIAL_AI_STALE_MESSAGE);
        }

        return $back->with('success', 'Zastosowano opis obrazu. Nagłówek i reszta briefu zostały bez zmian. Materiał ma status Draft.');
    }

    public function rejectImageDescriptionAi(string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        DemoTikWebinarProject::rejectImageDescriptionAiProposal($project);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Odrzucono propozycję opisu obrazu. Nic nie zmieniono.');
    }

    public function storeSponsorLogo(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $request->validate([
            'sponsor_logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        $campaignId = $item['growth_campaign_id'] ?? null;
        if (! is_numeric($campaignId)) {
            return $back->with('error', self::IMAGE_NOT_PERSISTED_MESSAGE);
        }

        try {
            GraphicLogoStore::storeSponsor(
                (int) $campaignId,
                (string) file_get_contents($request->file('sponsor_logo')->getRealPath()),
                app(GraphicImageProcessor::class),
            );
        } catch (GrowthAiException $exception) {
            return $back->with('error', $exception->userMessage);
        }

        return $back->with('success', 'Zapisano logo sponsora. Zaznacz je przy generowaniu, żeby trafiło na obraz.');
    }

    public function deleteSponsorLogo(string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        if (is_numeric($item['growth_campaign_id'] ?? null)) {
            GraphicLogoStore::deleteSponsor((int) $item['growth_campaign_id']);
        }

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Usunięto logo sponsora. Już wygenerowane obrazy zostają bez zmian.');
    }

    public function showSponsorLogo(string $project, string $material): StreamedResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $campaignId = $item['growth_campaign_id'] ?? null;
        abort_unless(is_numeric($campaignId), 404);
        $path = GraphicLogoStore::sponsorPath((int) $campaignId);
        $disk = Storage::disk((string) config('growth_ai.images.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'logo-sponsora.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    public function adaptMaterialImageToSquare(string $project, string $material, int $image): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $source = DemoTikWebinarProject::requireMaterialImage($item, $material, $image);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('growth_ai.images.timeout_seconds') + 60);
        }

        try {
            $square = app(GrowthImageService::class)->adaptToSquare(auth()->user(), $source);
        } catch (GrowthAiException $exception) {
            return $back->with('error', $exception->userMessage);
        }

        return $back->with('success', $square->source === GrowthArtifactImage::SOURCE_SIMULATION
            ? 'Przygotowano kwadratowy obraz zastępczy (symulacja lokalna, bez wywołania OpenAI).'
            : 'Utworzono wersję kwadratową z obrazu poziomego. Sprawdź, czy wszystkie elementy się zmieściły.');
    }

    public function resetAiDailyLimit(Request $request): RedirectResponse
    {
        app(GrowthAiService::class)->resetDailyLimit($request->user());

        return redirect()
            ->back()
            ->with('success', 'Zresetowano dzienny limit AI. Możesz znów prosić o propozycje.');
    }

    public function resetMaterialImageLimit(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless($material === GraphicImageTask::MATERIAL_KEY, 404);
        DemoTikWebinarProject::requireProject($project);
        app(GrowthImageService::class)->resetDailyLimit($request->user());

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Zresetowano dzienny limit obrazów AI. Możesz znów generować obrazy.');
    }

    public function showMaterialImage(Request $request, string $project, string $material, int $image): StreamedResponse
    {
        $row = DemoTikWebinarProject::requireMaterialImage(DemoTikWebinarProject::requireProject($project), $material, $image);
        $disk = Storage::disk($row->disk);
        abort_unless($disk->exists($row->path), 404);
        $headers = ['Content-Type' => $row->mime, 'Cache-Control' => 'private, max-age=3600'];

        return $request->boolean('download')
            ? $disk->download($row->path, $row->downloadName(), $headers)
            : $disk->response($row->path, $row->downloadName(), $headers);
    }

    public function selectMaterialImage(string $project, string $material, int $image): RedirectResponse
    {
        $row = DemoTikWebinarProject::requireMaterialImage(DemoTikWebinarProject::requireProject($project), $material, $image);
        app(GrowthImageService::class)->select($row);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Wybrano obraz jako grafikę główną. Nic nie opublikowano.');
    }

    public function deleteMaterialImage(string $project, string $material, int $image): RedirectResponse
    {
        $row = DemoTikWebinarProject::requireMaterialImage(DemoTikWebinarProject::requireProject($project), $material, $image);
        app(GrowthImageService::class)->delete($row);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Usunięto obraz z galerii.');
    }

    public function restoreMaterialVersion(string $project, string $material, int $version): RedirectResponse
    {
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        $outcome = DemoTikWebinarProject::restoreMaterialVersion($project, $material, $version);

        if ($outcome['unchanged']) {
            return $back->with('success', 'Ta wersja jest taka sama jak obecny szkic. Nic nie zmieniono.');
        }

        return $back->with('success', 'Przywrócono wersję '.$version.' jako nową wersję. Materiał ma status Draft — sprawdź go przed dalszą pracą.');
    }

    public function requestMaterialAi(Request $request, string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        $item = DemoTikWebinarProject::requireProject($project);
        $data = $request->validate([
            'instruction' => ['nullable', 'string', 'max:'.config('growth_ai.limits.max_instruction_chars')],
            'mode' => ['nullable', Rule::in([MaterialDraftTask::MODE_GENERATE, MaterialDraftTask::MODE_REFINE, MaterialDraftTask::MODE_ITERATE])],
            'author_draft' => ['nullable', 'string', 'max:20000'],
            'length' => ['nullable', Rule::in(array_keys(MaterialDraftTask::MAIL_LENGTHS))],
            'timing' => ['nullable', Rule::in(array_keys(MaterialDraftTask::REMINDER_TIMINGS))],
            'duration' => ['nullable', Rule::in([...array_map('strval', MaterialDraftTask::HOST_SCRIPT_DURATIONS), 'custom'])],
            'duration_custom' => [
                'nullable',
                'required_if:duration,custom',
                'integer',
                'min:'.MaterialDraftTask::HOST_SCRIPT_MIN_DURATION,
                'max:'.MaterialDraftTask::HOST_SCRIPT_MAX_DURATION,
            ],
            ...GrowthAiExecutionOptions::requestRules(),
        ], [
            'duration_custom.required_if' => 'Wpisz czas trwania w minutach.',
            'duration_custom.integer' => 'Czas trwania podaj jako liczbę minut.',
            'duration_custom.min' => 'Czas trwania musi wynosić co najmniej :min minut.',
            'duration_custom.max' => 'Czas trwania może wynosić najwyżej :max minut.',
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        $style = [
            'emojis' => $request->boolean('emojis', ! MaterialDraftTask::isMail($material) && ! MaterialDraftTask::usesVoice($material)),
            'hashtags' => $request->boolean('hashtags', true),
            'elements' => collect(MaterialDraftTask::GRAPHIC_OPTIONAL_ELEMENTS)
                ->mapWithKeys(fn (string $label, string $key): array => [$key => $request->boolean('elements.'.$key, true)])
                ->all(),
            'length' => MaterialDraftTask::mailLength($data['length'] ?? null),
            'timing' => MaterialDraftTask::reminderTiming($data['timing'] ?? null),
            'duration_minutes' => MaterialDraftTask::hostScriptDuration(
                ($data['duration'] ?? null) === 'custom' ? ($data['duration_custom'] ?? null) : ($data['duration'] ?? null),
            ),
        ];
        $instruction = trim((string) ($data['instruction'] ?? ''));
        $execution = GrowthAiRequestOptions::fromRequest($request);

        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->withInput()->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->withInput()->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        $work = [];
        $inherit = null;
        if (MaterialDraftTask::usesWorkModes($material)) {
            $mode = MaterialDraftTask::aiMode($data['mode'] ?? null);
            $work = ['mode' => $mode, 'text' => ''];

            if ($mode === MaterialDraftTask::MODE_REFINE) {
                $work['text'] = trim((string) ($data['author_draft'] ?? ''));
                if ($work['text'] === '') {
                    return $back->withInput()->with('error', self::MATERIAL_AI_REFINE_EMPTY_MESSAGE);
                }
            }

            if ($mode === MaterialDraftTask::MODE_ITERATE) {
                if ($instruction === '') {
                    return $back->withInput()->with('error', self::MATERIAL_AI_ITERATE_EMPTY_MESSAGE);
                }
                $previous = DemoTikWebinarProject::iterableMaterialAiProposal($project, $material);
                if ($previous === null) {
                    return $back->with('error', self::MATERIAL_AI_STALE_MESSAGE);
                }
                $work['text'] = (string) $previous['draft'];
                if (is_string($previous['address_form_effective'] ?? null)) {
                    $style['previous_address_form'] = (string) $previous['address_form_effective'];
                }
                $inherit = GrowthAiRequestOptions::inheritFromProposal($previous);
            }
        }

        if ($material === MaterialDraftTask::MAIL_MATERIAL_KEY) {
            if ($request->has('html')) {
                $style['html'] = $request->boolean('html');
            } elseif (($work['mode'] ?? '') === MaterialDraftTask::MODE_ITERATE) {
                $previous = DemoTikWebinarProject::materialAiProposal($item, $material);
                $style['html'] = (bool) ($previous['mail_html'] ?? false)
                    || str_contains((string) ($previous['draft'] ?? ''), \App\Support\GrowthOS\MailHtmlFormatter::MARKER);
            } else {
                $style['html'] = false;
            }
        } else {
            $style['html'] = false;
        }

        if (config('growth_ai.enabled') !== true) {
            DemoTikWebinarProject::requestMaterialAiProposal($project, $material, $style, $instruction, $work, $execution);

            return $back->with('success', MaterialDraftTask::isMail($material)
                ? 'AI przygotowało szkic (symulacja lokalna). Temat, preheader i treść są w polach edycji — popraw i zapisz.'
                : 'AI przygotowało szkic (symulacja lokalna). Obecny szkic nie został nadpisany.');
        }

        try {
            $result = app(GrowthAiService::class)->draftMaterial(
                $request->user(),
                $material,
                DemoTikWebinarProject::materialAiContext($item, $material, $style, $instruction, $work),
                $execution,
                $inherit,
            );
        } catch (GrowthAiException $exception) {
            $message = $exception->userMessage === GrowthAiException::INVALID_RESPONSE_MESSAGE
                ? 'Nie udało się przygotować poprawnej propozycji AI. Obecny szkic nie został zmieniony.'
                : $exception->userMessage;

            return $back->withInput()->with('error', $message);
        }

        DemoTikWebinarProject::storeMaterialAiProposal($project, $material, $result, $instruction, $work, $style);

        return $back->with('success', MaterialDraftTask::isMail($material)
            ? 'AI przygotowało szkic. Temat, preheader i treść są w polach edycji — popraw i zapisz.'
            : 'AI przygotowało szkic. Obecny szkic nie został nadpisany.');
    }

    public function applyMaterialAi(string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        if (DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material))) {
            return $back->with('error', self::MATERIAL_SKIPPED_MESSAGE);
        }

        $outcome = DemoTikWebinarProject::applyMaterialAiProposal($project, $material);

        if (! $outcome['ok']) {
            return $back->with('error', self::MATERIAL_AI_STALE_MESSAGE);
        }

        return $back->with('success', 'Zastosowano szkic AI. Materiał ma status Draft — sprawdź go przed dalszą pracą. Nic nie opublikowano.');
    }

    public function rejectMaterialAi(string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        $proposal = DemoTikWebinarProject::materialAiProposal(DemoTikWebinarProject::requireProject($project), $material);
        DemoTikWebinarProject::rejectMaterialAiProposal($project, $material);
        $restore = trim((string) ($proposal['restore_draft'] ?? ''));

        $back = redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Odrzucono szkic AI. Obecny szkic pozostał bez zmian.');

        if ($restore !== '' && $restore !== trim(DemoTikWebinarProject::material($project, $material)['draft'] ?? '')) {
            return $back
                ->with('material_restored_draft', $restore)
                ->with('success', 'Odrzucono propozycję AI. Twój niezapisany szkic wrócił do pola — zapisz go, jeżeli chcesz go zachować.');
        }

        return $back;
    }

    public function updateMaterialStatus(Request $request, string $project, string $material): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DemoTikWebinarProject::materialStatusLabels()))],
            'draft' => ['nullable', 'string', 'max:20000'],
            'mail_subject' => ['nullable', 'string', 'max:200'],
            'mail_preheader' => ['nullable', 'string', 'max:200'],
            'mail_body' => ['nullable', 'string', 'max:20000'],
            'include_paid_offer' => ['nullable', 'boolean'],
            'show_certificate' => ['nullable', 'boolean'],
            'refresh_paid_offer' => ['nullable', 'boolean'],
            'reminder_timing' => ['nullable', Rule::in(array_keys(MaterialDraftTask::REMINDER_TIMINGS))],
        ]);

        if (MaterialDraftTask::isMail($material) && $request->has('mail_body')) {
            $subject = trim(preg_replace('/\s+/u', ' ', (string) ($data['mail_subject'] ?? '')));
            $body = MaterialDraftTask::isMail($material)
                ? \App\Support\GrowthOS\MailHtmlFormatter::editorContent((string) ($data['mail_body'] ?? ''))
                : (string) ($data['mail_body'] ?? '');
            $subjects = $subject !== '' ? [$subject] : [];
            $activeProposal = DemoTikWebinarProject::materialAiProposal(
                DemoTikWebinarProject::requireProject($project),
                $material,
            );
            if (is_array($activeProposal)) {
                $proposalFields = MaterialDraftTask::parseMainMail((string) ($activeProposal['draft'] ?? ''));
                foreach ($proposalFields['alternatives'] as $alternative) {
                    if ($alternative !== '' && $alternative !== $subject) {
                        $subjects[] = $alternative;
                    }
                }
            }
            $data['draft'] = MaterialDraftTask::composeMainMail(
                $subjects,
                preg_replace('/\s+/u', ' ', (string) ($data['mail_preheader'] ?? '')),
                $body,
            );
        } elseif ($material === MaterialDraftTask::FACEBOOK_MATERIAL_KEY && array_key_exists('draft', $data)) {
            $data['draft'] = \App\Support\GrowthOS\GrowthMailUrls::withRegistrationLink(
                (string) ($data['draft'] ?? ''),
                DemoTikWebinarProject::requireProject($project)['registration_url'] ?? null,
            );
        }

        $wasSkipped = DemoTikWebinarProject::isMaterialSkipped(DemoTikWebinarProject::material($project, $material));
        $mailOptions = MaterialDraftTask::isMail($material)
            ? [
                'include_paid_offer' => $request->boolean('include_paid_offer'),
                'show_certificate' => $request->boolean('show_certificate'),
                'refresh_paid_offer' => $request->boolean('refresh_paid_offer'),
                ...($material === MaterialDraftTask::REMINDER_MATERIAL_KEY
                    ? ['reminder_timing' => MaterialDraftTask::reminderTiming($data['reminder_timing'] ?? null)]
                    : []),
            ]
            : null;
        $hadMailAiProposal = MaterialDraftTask::isMail($material)
            && DemoTikWebinarProject::materialAiProposal(DemoTikWebinarProject::requireProject($project), $material) !== null;
        $saved = DemoTikWebinarProject::updateMaterialStatus(
            $project,
            $material,
            $data['status'],
            array_key_exists('draft', $data) ? (string) $data['draft'] : null,
            null,
            $mailOptions,
        );

        if ($hadMailAiProposal) {
            DemoTikWebinarProject::clearMaterialAiProposal($project, $material);
        }

        $message = match (true) {
            DemoTikWebinarProject::isMaterialSkipped($saved) => 'Materiał wyłączony (Nie dotyczy). Nie liczy się do następnego kroku ani elementów krytycznych. Szkic został zachowany.',
            $wasSkipped => 'Materiał jest znowu aktywny. Szkic jest taki jak przed wyłączeniem.',
            $hadMailAiProposal => 'Zapisano poprawiony szkic AI. Propozycja została zamknięta. Nic nie opublikowano.',
            default => 'Status i szkic materiału zostały zapisane. Nic nie opublikowano.',
        };

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', $message);
    }
}

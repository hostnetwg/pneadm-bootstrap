<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Models\GrowthOS\GrowthTask;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\GrowthAiService;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Services\GrowthOS\GrowthOperationalTasks;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public const MATERIAL_AI_PRECONDITION_MESSAGE = 'Najpierw zatwierdź kierunek i koncepcję webinaru.';

    public const MATERIAL_AI_STALE_MESSAGE = 'Kierunek, koncepcja lub materiał zmieniły się od czasu wygenerowania szkicu. Wygeneruj nową propozycję.';

    public function index(): View
    {
        return view('growth-os.projects.index', [
            'project' => DemoTikWebinarProject::project(),
            'nextAction' => DemoTikWebinarProject::nextAction(DemoTikWebinarProject::project()),
            'projectStatusLabels' => DemoTikWebinarProject::projectStatusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('growth-os.projects.create', [
            'goals' => DemoTikWebinarProject::goals(),
            'ideas' => DemoTikWebinarProject::ideas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $goalValues = collect(DemoTikWebinarProject::goals())->pluck('value')->all();

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'live_date' => ['required', 'date'],
            'live_time' => ['required', 'date_format:H:i'],
            'host' => ['required', 'string', 'max:120'],
            'goal' => ['required', Rule::in($goalValues)],
            'topic' => ['nullable', 'string', 'max:180'],
        ]);

        $project = DemoTikWebinarProject::createProject($data);

        return redirect()
            ->route('growth.projects.show', $project['id'])
            ->with('success', 'Utworzono projekt webinaru. Kampania i prowadzący są zapisane.');
    }

    public function updateHost(Request $request, string $project): RedirectResponse
    {
        $host = $request->validate([
            'host' => ['required', 'string', 'max:120'],
        ])['host'];

        DemoTikWebinarProject::updateHost($project, $host);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano prowadzącego.')
            ->withFragment('project-host');
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
            'growthAiProvider' => (string) config('growth_ai.provider'),
            'growthAiModel' => (string) config('growth_ai.model'),
            'conceptDecisions' => DemoTikWebinarProject::conceptDecisions($item),
            'operationalTasks' => DemoTikWebinarProject::operationalTasks($item),
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
            'why_now' => ['required', 'string', 'max:1000'],
            'audience' => ['required', 'string', 'max:500'],
            'problem' => ['required', 'string', 'max:1000'],
            'takeaway' => ['required', 'string', 'max:1000'],
            'sell_later' => ['required', Rule::in(['nie', 'być może', 'tak'])],
        ]);

        DemoTikWebinarProject::updateDirection($project, $data);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zapisano kierunek.')
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
        ]);
        $intent = (string) $data['intent'];
        $intentLabel = (string) (
            collect(DemoTikWebinarProject::conceptAiIntents())->firstWhere('value', $intent)['label']
            ?? $intent
        );
        $wantsJson = $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if (config('growth_ai.enabled') !== true) {
            $updatedProject = DemoTikWebinarProject::requestConceptAiProposal($project, $intent);

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
        $item = DemoTikWebinarProject::requireProject($project);
        $instruction = $intentLabel;
        if (filled($data['instruction'] ?? null)) {
            $instruction .= '. Dodatkowa instrukcja użytkownika: '.trim((string) $data['instruction']);
        }

        try {
            $result = $growthAiService->reviseConcept(
                user: $request->user(),
                concept: is_array($item['concept'] ?? null) ? $item['concept'] : [],
                audience: (string) data_get($item, 'direction.audience', ''),
                instruction: $instruction,
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

        return view('growth-os.projects.material', [
            'project' => $projectItem,
            'material' => $item,
            'materialStatusLabels' => DemoTikWebinarProject::materialStatusLabels(),
            'aiDraftSupported' => $aiDraftSupported,
            'aiDraftIsFacebookPost' => $isFacebookPost,
            'aiDraftUsesYoutubeDescription' => $isFacebookPost
                && DemoTikWebinarProject::approvedYoutubeDescription($projectItem) !== '',
            'aiDraftAllowed' => $aiDraftSupported && DemoTikWebinarProject::canDraftMaterialWithAi($projectItem),
            'aiDraftProposal' => $aiDraftSupported ? DemoTikWebinarProject::materialAiProposal($projectItem, $material) : null,
            'aiRealEnabled' => config('growth_ai.enabled') === true,
            'aiModel' => (string) config('growth_ai.model'),
            'materialVersions' => DemoTikWebinarProject::materialVersions($projectItem, $material),
        ]);
    }

    public function restoreMaterialVersion(string $project, string $material, int $version): RedirectResponse
    {
        DemoTikWebinarProject::material($project, $material);
        $outcome = DemoTikWebinarProject::restoreMaterialVersion($project, $material, $version);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

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
        ]);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);
        $style = [
            'emojis' => $request->boolean('emojis', true),
            'hashtags' => $request->boolean('hashtags', true),
        ];
        $instruction = trim((string) ($data['instruction'] ?? ''));

        if (! DemoTikWebinarProject::canDraftMaterialWithAi($item)) {
            return $back->withInput()->with('error', self::MATERIAL_AI_PRECONDITION_MESSAGE);
        }

        if (config('growth_ai.enabled') !== true) {
            DemoTikWebinarProject::requestMaterialAiProposal($project, $material, $style, $instruction);

            return $back->with('success', 'AI przygotowało szkic (symulacja lokalna). Obecny szkic nie został nadpisany.');
        }

        try {
            $result = app(GrowthAiService::class)->draftMaterial(
                $request->user(),
                $material,
                DemoTikWebinarProject::materialAiContext($item, $material, $style, $instruction),
            );
        } catch (GrowthAiException $exception) {
            $message = $exception->userMessage === GrowthAiException::INVALID_RESPONSE_MESSAGE
                ? 'Nie udało się przygotować poprawnej propozycji AI. Obecny szkic nie został zmieniony.'
                : $exception->userMessage;

            return $back->withInput()->with('error', $message);
        }

        DemoTikWebinarProject::storeMaterialAiProposal($project, $material, $result, $instruction);

        return $back->with('success', 'AI przygotowało szkic. Obecny szkic nie został nadpisany.');
    }

    public function applyMaterialAi(string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        $outcome = DemoTikWebinarProject::applyMaterialAiProposal($project, $material);
        $back = redirect()->route('growth.projects.materials.show', [$project, $material]);

        if (! $outcome['ok']) {
            return $back->with('error', self::MATERIAL_AI_STALE_MESSAGE);
        }

        return $back->with('success', 'Zastosowano szkic AI. Materiał ma status Draft — sprawdź go przed dalszą pracą. Nic nie opublikowano.');
    }

    public function rejectMaterialAi(string $project, string $material): RedirectResponse
    {
        abort_unless(MaterialDraftTask::supports($material), 404);
        DemoTikWebinarProject::rejectMaterialAiProposal($project, $material);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Odrzucono szkic AI. Obecny szkic pozostał bez zmian.');
    }

    public function updateMaterialStatus(Request $request, string $project, string $material): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DemoTikWebinarProject::materialStatusLabels()))],
            'draft' => ['nullable', 'string', 'max:20000'],
        ]);

        DemoTikWebinarProject::updateMaterialStatus(
            $project,
            $material,
            $data['status'],
            array_key_exists('draft', $data) ? (string) $data['draft'] : null,
        );

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Status i szkic materiału zostały zapisane. Nic nie opublikowano.');
    }
}

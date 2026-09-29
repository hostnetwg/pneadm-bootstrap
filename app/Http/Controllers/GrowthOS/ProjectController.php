<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\GrowthAiService;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
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
            ->with('success', 'Utworzono projekt webinaru. Kampania jest zapisana. Prowadzący i reszta procesu zostają w tej sesji.');
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
        ]);
    }

    public function completeStep(string $project, string $step): RedirectResponse
    {
        DemoTikWebinarProject::completeStep($project, $step);

        $message = $step === 'direction'
            ? 'Kierunek zatwierdzony w tej sesji. Przejdź do koncepcji webinaru.'
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
                : 'Cofnięto zatwierdzenie w tej sesji. Etap wrócił do „Do dopracowania”.')
            ->withFragment($step);
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

    public function material(string $project, string $material): View
    {
        $item = DemoTikWebinarProject::material($project, $material);
        $projectItem = DemoTikWebinarProject::requireProject($project);

        return view('growth-os.projects.material', [
            'project' => $projectItem,
            'material' => $item,
            'materialStatusLabels' => DemoTikWebinarProject::materialStatusLabels(),
        ]);
    }

    public function updateMaterialStatus(Request $request, string $project, string $material): RedirectResponse
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(array_keys(DemoTikWebinarProject::materialStatusLabels()))],
        ])['status'];

        DemoTikWebinarProject::updateMaterialStatus($project, $material, $status);

        return redirect()
            ->route('growth.projects.materials.show', [$project, $material])
            ->with('success', 'Status materiału zmieniono tylko w tej sesji. Nic nie opublikowano.');
    }
}

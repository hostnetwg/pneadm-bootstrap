<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;
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
            'type' => ['required', 'string', 'max:80'],
            'live_date' => ['required', 'date'],
            'live_time' => ['required', 'date_format:H:i'],
            'host' => ['required', 'string', 'max:120'],
            'goal' => ['required', Rule::in($goalValues)],
            'topic' => ['nullable', 'string', 'max:180'],
        ]);

        $project = DemoTikWebinarProject::createProject($data);

        return redirect()
            ->route('growth.projects.show', $project['id'])
            ->with('success', 'Utworzono projekt webinaru TIK w tej sesji. Nic nie zapisano w bazie.');
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
        ]);
    }

    public function completeStep(string $project, string $step): RedirectResponse
    {
        DemoTikWebinarProject::completeStep($project, $step);

        $message = $step === 'direction'
            ? 'Kierunek zatwierdzony w tej sesji. Przejdź do koncepcji webinaru.'
            : 'Koncepcja gotowa w tej sesji. Możesz przygotowywać materiały.';

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
            ->with('success', 'Cofnięto zatwierdzenie w tej sesji. Etap wrócił do „Do dopracowania”.')
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
            ->with('success', 'Zapisano koncepcję w tej sesji. Nic nie opublikowano.')
            ->withFragment('concept');
    }

    public function requestConceptAi(Request $request, string $project): RedirectResponse
    {
        $intent = $request->validate([
            'intent' => ['required', Rule::in(collect(DemoTikWebinarProject::conceptAiIntents())->pluck('value')->all())],
        ])['intent'];

        DemoTikWebinarProject::requestConceptAiProposal($project, $intent);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'AI przygotowało propozycję (symulacja). Obecna koncepcja nie została nadpisana.')
            ->withFragment('concept');
    }

    public function applyConceptAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::applyConceptAiProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Zastosowano propozycję AI w tej sesji. Sprawdź koncepcję i zatwierdź, gdy będzie gotowa.')
            ->withFragment('concept');
    }

    public function rejectConceptAi(string $project): RedirectResponse
    {
        DemoTikWebinarProject::rejectConceptAiProposal($project);

        return redirect()
            ->route('growth.projects.show', $project)
            ->with('success', 'Odrzucono propozycję AI. Została poprzednia koncepcja.')
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

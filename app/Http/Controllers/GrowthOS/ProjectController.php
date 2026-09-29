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
            ->with('success', $message);
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

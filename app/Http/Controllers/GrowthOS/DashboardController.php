<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $project = DemoTikWebinarProject::project();

        return view('growth-os.dashboard', [
            'project' => $project,
            'health' => DemoTikWebinarProject::projectHealth($project),
            'nextAction' => DemoTikWebinarProject::nextAction($project),
            'inboxCount' => count(DemoTikWebinarProject::inboxItems()),
            'ideasCount' => count(DemoTikWebinarProject::ideas()),
            'projectStatusLabels' => DemoTikWebinarProject::projectStatusLabels(),
        ]);
    }
}

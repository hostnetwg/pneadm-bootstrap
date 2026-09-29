<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;

class IdeaController extends Controller
{
    public function index(): View
    {
        return view('growth-os.ideas.index', [
            'ideas' => DemoTikWebinarProject::ideas(),
            'project' => DemoTikWebinarProject::project(),
        ]);
    }
}

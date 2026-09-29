<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Contracts\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        return view('growth-os.approvals.index', [
            'project' => DemoTikWebinarProject::project(),
            'items' => DemoTikWebinarProject::inboxItems(),
        ]);
    }
}

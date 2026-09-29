<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Pozostawiony tylko jako bezpieczny shim po Etapie 0.2.
 * Etap 0.3 używa ProjectController i tras growth.projects.*.
 */
class CampaignController extends Controller
{
    public function show(string $campaign): View
    {
        return view('growth-os.campaigns.show', [
            'campaign' => $campaign,
        ]);
    }
}
